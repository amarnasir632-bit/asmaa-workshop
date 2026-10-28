<?php

namespace App\Http\Controllers;

use App\Models\DeliveryItem;
use App\Models\FinancialCorrection;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\RefundTransaction;
use App\Services\AuditLogger;
use App\Services\OrderFinancialSummary;
use App\Support\LocalDateTime;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RefundController extends Controller
{
    public function approve(Request $request, Order $order, OrderFinancialSummary $summary, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('refunds.approve');
        $data = $request->validate([
            'approved_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/'],
            'reason' => ['required', 'string', 'max:3000'],
            'original_payment_id' => ['nullable', 'integer', 'exists:payments,id'],
            'returned_delivery_item_id' => ['nullable', 'integer', 'exists:delivery_items,id'],
            'returned_quantity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'returned_item_condition' => ['nullable', Rule::in(['resalable', 'damaged', 'altered'])],
            'inspection_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $order, $data, $summary, $audit): void {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $amount = Money::toMinorUnits($data['approved_amount']);
            $financial = $summary->for($lockedOrder);
            $approved = $lockedOrder->refunds()->sum('approved_amount');
            $reserved = max(0, Money::toMinorUnits((string) $approved) - $financial['refunded']);
            $available = max(0, $financial['confirmed'] - $financial['refunded'] - $reserved);
            if ($lockedOrder->operational_status === 'cancelled') {
                $cancellation = $lockedOrder->cancellations()->latest('id')->first();
                $cancellationApproved = $cancellation?->refunds()->sum('approved_amount') ?? 0;
                $cancellationRoom = $cancellation ? max(0, Money::toMinorUnits((string) $cancellation->refund_due) - Money::toMinorUnits((string) $cancellationApproved)) : 0;
                $available = min($available, $cancellationRoom);
            }
            if ($amount > $available) {
                throw ValidationException::withMessages(['approved_amount' => 'المبلغ يتجاوز المتاح للاسترداد من التحصيل المؤكد أو قرار الإلغاء.']);
            }

            $payment = null;
            if (! empty($data['original_payment_id'])) {
                $payment = Payment::query()->whereKey($data['original_payment_id'])->where('order_id', $lockedOrder->id)->lockForUpdate()->first();
                if (! $payment || ($payment->method === 'bank_transfer' && $payment->verification_status !== 'verified')) {
                    throw ValidationException::withMessages(['original_payment_id' => 'الدفعة الأصلية يجب أن تكون لهذا الطلب ومؤكدة.']);
                }
                $paymentApproved = $payment->refunds()->sum('approved_amount');
                $paymentRefunded = $payment->refunds()->sum('refunded_amount');
                $paymentAvailable = max(0, Money::toMinorUnits((string) $payment->amount) - Money::toMinorUnits((string) $paymentRefunded)
                    - max(0, Money::toMinorUnits((string) $paymentApproved) - Money::toMinorUnits((string) $paymentRefunded)));
                if ($amount > $paymentAvailable) {
                    throw ValidationException::withMessages(['original_payment_id' => 'المبلغ يتجاوز الرصيد القابل للاسترداد من الدفعة الأصلية.']);
                }
            }

            $deliveryItem = null;
            if (! empty($data['returned_delivery_item_id'])) {
                $deliveryItem = DeliveryItem::query()->with('delivery.order', 'orderItem')->whereKey($data['returned_delivery_item_id'])->lockForUpdate()->first();
                if (! $deliveryItem || $deliveryItem->delivery->order_id !== $lockedOrder->id) {
                    throw ValidationException::withMessages(['returned_delivery_item_id' => 'قطعة الإرجاع لا تتبع هذا الطلب.']);
                }
                if (empty($data['returned_quantity']) || empty($data['returned_item_condition'])) {
                    throw ValidationException::withMessages(['returned_quantity' => 'سجّل الكمية المستلمة وحالة القطعة بعد فحصها.']);
                }
                $alreadyReturned = (int) $lockedOrder->refunds()->where('returned_delivery_item_id', $deliveryItem->id)->sum('returned_quantity');
                if ($alreadyReturned + (int) $data['returned_quantity'] > $deliveryItem->quantity_delivered) {
                    throw ValidationException::withMessages(['returned_quantity' => 'كمية الإرجاع تتجاوز الكمية المسلمة سابقاً.']);
                }
            } elseif ($lockedOrder->deliveries()->exists()) {
                throw ValidationException::withMessages(['returned_delivery_item_id' => 'اختر قطعة مسلمة وسجّل استلامها وفحصها قبل اعتماد الاسترداد.']);
            }

            $refund = Refund::query()->create([
                'order_id' => $lockedOrder->id, 'cancellation_id' => $lockedOrder->cancellations()->latest('id')->value('id'), 'original_payment_id' => $payment?->id,
                'returned_delivery_item_id' => $deliveryItem?->id, 'returned_item_received_at' => $deliveryItem ? now() : null,
                'returned_quantity' => $deliveryItem ? (int) $data['returned_quantity'] : null,
                'returned_item_condition' => $data['returned_item_condition'] ?? null,
                'inspection_notes' => $data['inspection_notes'] ?? null,
                'approved_amount' => Money::fromMinorUnits($amount), 'refunded_amount' => '0.00',
                'approved_by' => $request->user()->id, 'approved_at' => now(), 'reason' => trim($data['reason']),
            ]);

            if ($deliveryItem && $data['returned_item_condition'] === 'resalable' && $lockedOrder->type === 'ready') {
                $variantId = $deliveryItem->orderItem->inventory_variant_id;
                if (! $variantId) {
                    throw ValidationException::withMessages(['returned_item_condition' => 'لا يرتبط بند الملابس الجاهزة بصنف مخزني.']);
                }
                $balance = InventoryBalance::query()->where('inventory_variant_id', $variantId)->lockForUpdate()->first();
                if (! $balance) {
                    $balance = InventoryBalance::query()->create(['inventory_variant_id' => $variantId, 'quantity_available' => 0]);
                }
                $balance->increment('quantity_available', (int) $data['returned_quantity']);
                InventoryMovement::query()->create([
                    'inventory_variant_id' => $variantId, 'quantity_change' => (int) $data['returned_quantity'],
                    'movement_type' => 'customer_return', 'order_item_id' => $deliveryItem->order_item_id,
                    'delivery_item_id' => $deliveryItem->id, 'reason' => 'مرتجع مفحوص صالح للبيع؛ الاسترداد '.$refund->id,
                    'occurred_at' => now(), 'recorded_by' => $request->user()->id,
                ]);
            }
            $audit->record('refund_approved', $refund, $refund->id, ['order_id', 'approved_amount', 'original_payment_id', 'returned_delivery_item_id', 'inspection']);
        });

        return redirect()->route('orders.show', $order)->with('status', 'اعتمد المدير الاسترداد. سجّل الدفع الفعلي في السجل عند تنفيذه.');
    }

    public function pay(Request $request, Refund $refund, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('refunds.approve');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/'],
            'method' => ['required', Rule::in(['cash', 'bank_transfer'])],
            'refunded_at' => ['required', 'date', 'before_or_equal:now'],
        ]);
        DB::transaction(function () use ($request, $refund, $data, $audit): void {
            $locked = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();
            $amount = Money::toMinorUnits($data['amount']);
            $remaining = Money::toMinorUnits((string) $locked->approved_amount) - Money::toMinorUnits((string) $locked->refunded_amount);
            if ($amount > $remaining) {
                throw ValidationException::withMessages(['amount' => 'المبلغ يتجاوز الجزء المعتمد المتبقي للاسترداد.']);
            }
            $transaction = RefundTransaction::query()->create([
                'refund_id' => $locked->id, 'amount' => Money::fromMinorUnits($amount), 'method' => $data['method'],
                'refunded_at' => LocalDateTime::toUtc($data['refunded_at']), 'recorded_by' => $request->user()->id,
            ]);
            $locked->update([
                'refunded_amount' => Money::fromMinorUnits(Money::toMinorUnits((string) $locked->refunded_amount) + $amount),
                'method' => $data['method'], 'refunded_at' => LocalDateTime::toUtc($data['refunded_at']),
            ]);
            $audit->record('refund_paid', $transaction, $transaction->id, ['refund_id', 'amount', 'method', 'refunded_at']);
            $order->touch();
        });

        return back()->with('status', 'سُجل مبلغ الاسترداد الفعلي دون تغيير سجل الدفعة الأصلية.');
    }

    public function correct(Request $request, Refund $refund, OrderFinancialSummary $summary, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('history.correct');
        $data = $request->validate([
            'approved_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/'],
            'reason' => ['required', 'string', 'max:3000'],
        ]);
        DB::transaction(function () use ($request, $refund, $data, $summary, $audit): void {
            $lockedRefund = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($lockedRefund->order_id)->lockForUpdate()->firstOrFail();
            $newAmount = Money::toMinorUnits($data['approved_amount']);
            $refunded = Money::toMinorUnits((string) $lockedRefund->refunded_amount);
            if ($newAmount < $refunded) {
                throw ValidationException::withMessages(['approved_amount' => 'لا يمكن خفض الاعتماد إلى أقل من المبلغ المصروف فعلياً.']);
            }
            $financial = $summary->for($order);
            $oldAmount = Money::toMinorUnits((string) $lockedRefund->approved_amount);
            $othersApproved = Money::toMinorUnits((string) $order->refunds()->where('id', '!=', $lockedRefund->id)->sum('approved_amount'));
            $othersRefunded = Money::toMinorUnits((string) $order->refunds()->where('id', '!=', $lockedRefund->id)->sum('refunded_amount'));
            if ($newAmount - $refunded + max(0, $othersApproved - $othersRefunded) > max(0, $financial['confirmed'] - $financial['refunded'])) {
                throw ValidationException::withMessages(['approved_amount' => 'التصحيح يتجاوز المقبوضات المؤكدة القابلة للاسترداد.']);
            }
            if ($lockedRefund->original_payment_id) {
                $payment = Payment::query()->whereKey($lockedRefund->original_payment_id)->lockForUpdate()->firstOrFail();
                $paymentApproved = Money::toMinorUnits((string) $payment->refunds()->where('id', '!=', $lockedRefund->id)->sum('approved_amount'));
                $paymentRefunded = Money::toMinorUnits((string) $payment->refunds()->sum('refunded_amount'));
                $paymentRoom = Money::toMinorUnits((string) $payment->amount) - $paymentRefunded;
                if ($newAmount - $refunded + max(0, $paymentApproved - ($paymentRefunded - $refunded)) > $paymentRoom) {
                    throw ValidationException::withMessages(['approved_amount' => 'التصحيح يتجاوز المتاح من الدفعة الأصلية.']);
                }
            }
            if ($lockedRefund->cancellation_id) {
                $cancellation = $lockedRefund->cancellation;
                $allApproved = Money::toMinorUnits((string) $order->refunds()->where('cancellation_id', $cancellation->id)->sum('approved_amount'));
                if ($allApproved - $oldAmount + $newAmount > Money::toMinorUnits((string) $cancellation->refund_due)) {
                    throw ValidationException::withMessages(['approved_amount' => 'التصحيح يتجاوز الحد المعتمد للاسترداد بعد الإلغاء.']);
                }
            }
            $lockedRefund->update(['approved_amount' => Money::fromMinorUnits($newAmount)]);
            FinancialCorrection::query()->create([
                'order_id' => $order->id, 'subject_type' => Refund::class, 'subject_id' => $lockedRefund->id,
                'field_name' => 'approved_amount', 'old_amount' => Money::fromMinorUnits($oldAmount),
                'new_amount' => Money::fromMinorUnits($newAmount), 'reason' => trim($data['reason']),
                'approved_by' => $request->user()->id, 'occurred_at' => now(),
            ]);
            $audit->record('refund_corrected', $lockedRefund, $lockedRefund->id, ['approved_amount'], trim($data['reason']));
        });

        return back()->with('status', 'حُفظ تصحيح اعتماد الاسترداد، مع بقاء كل دفعة استرداد فعلية مستقلة.');
    }
}
