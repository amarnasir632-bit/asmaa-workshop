<?php

namespace App\Http\Controllers;

use App\Models\Cancellation;
use App\Models\FinancialCorrection;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Services\AuditLogger;
use App\Services\OrderFinancialSummary;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CancellationController extends Controller
{
    public function store(Request $request, Order $order, OrderFinancialSummary $summary, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('orders.cancel');
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:3000'],
            'cancellation_discount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/'],
        ]);

        DB::transaction(function () use ($request, $order, $data, $summary, $audit): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->operational_status === 'cancelled') {
                throw ValidationException::withMessages(['order' => 'سبق اعتماد إلغاء هذا الطلب.']);
            }
            $discount = Money::toMinorUnits($data['cancellation_discount']);
            $total = Money::toMinorUnits((string) $locked->approved_total);
            if ($discount > $total) {
                throw ValidationException::withMessages(['cancellation_discount' => 'لا يمكن أن يتجاوز خصم الإلغاء قيمة الطلب المعتمدة.']);
            }
            $stage = $locked->items()->with('productionJob')->get()->pluck('productionJob.stage')->filter()->last()
                ?? $locked->operational_status;
            $financial = $summary->for($locked);
            $refundDue = max(0, $financial['net_paid'] - $discount);
            $cancellation = Cancellation::query()->create([
                'order_id' => $locked->id, 'production_stage_snapshot' => $stage,
                'reason' => trim($data['reason']), 'cancellation_discount' => Money::fromMinorUnits($discount),
                'refund_due' => Money::fromMinorUnits($refundDue),
                'approved_by' => $request->user()->id, 'approved_at' => now(),
            ]);
            if ($locked->type === 'ready') {
                foreach ($locked->items as $item) {
                    $variantId = $item->inventory_variant_id;
                    if (! $variantId) {
                        continue;
                    }
                    $deducted = max(0, -(int) InventoryMovement::query()->where('order_item_id', $item->id)->where('movement_type', 'ready_sale')->sum('quantity_change'));
                    $delivered = (int) DB::table('delivery_items')->where('order_item_id', $item->id)->sum('quantity_delivered');
                    $release = min(max(0, (int) $item->quantity - $delivered), $deducted);
                    if ($release === 0) {
                        continue;
                    }
                    $balance = InventoryBalance::query()->where('inventory_variant_id', $variantId)->lockForUpdate()->first();
                    if (! $balance) {
                        $balance = InventoryBalance::query()->create(['inventory_variant_id' => $variantId, 'quantity_available' => 0]);
                    }
                    $balance->increment('quantity_available', $release);
                    InventoryMovement::query()->create([
                        'inventory_variant_id' => $variantId, 'quantity_change' => $release,
                        'movement_type' => 'ready_sale_cancel', 'order_item_id' => $item->id,
                        'reason' => 'إرجاع كمية غير مسلمة عند إلغاء الطلب '.$locked->number,
                        'occurred_at' => now('UTC'), 'recorded_by' => $request->user()->id,
                    ]);
                }
            }
            $locked->update(['operational_status' => 'cancelled', 'cancelled_at' => now()]);
            $audit->record('order_cancelled', $cancellation, $cancellation->id, ['order_id', 'production_stage_snapshot', 'cancellation_discount', 'refund_due'], trim($data['reason']));
        });

        return redirect()->route('orders.show', $order)->with('status', 'اعتمد المدير الإلغاء وحُفظ الخصم ومرحلة الإنتاج. يمكن اعتماد استرداد حتى المبلغ المستحق إرجاعه.');
    }

    public function correct(Request $request, Order $order, OrderFinancialSummary $summary, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('history.correct');
        $data = $request->validate([
            'cancellation_discount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/'],
            'reason' => ['required', 'string', 'max:3000'],
        ]);
        DB::transaction(function () use ($request, $order, $data, $summary, $audit): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $cancellation = $locked->cancellations()->latest('id')->lockForUpdate()->firstOrFail();
            if ($locked->operational_status !== 'cancelled') {
                throw ValidationException::withMessages(['order' => 'لا يوجد قرار إلغاء معتمد لتصحيحه.']);
            }
            $newDiscount = Money::toMinorUnits($data['cancellation_discount']);
            if ($newDiscount > Money::toMinorUnits((string) $locked->approved_total)) {
                throw ValidationException::withMessages(['cancellation_discount' => 'لا يمكن أن يتجاوز الخصم قيمة الطلب.']);
            }
            $financial = $summary->for($locked);
            $newRefundDue = max(0, $financial['confirmed'] - $financial['refunded'] - $newDiscount);
            $outstandingApproved = max(0, Money::toMinorUnits((string) $locked->refunds()->sum('approved_amount')) - $financial['refunded']);
            if ($outstandingApproved > $newRefundDue) {
                throw ValidationException::withMessages(['cancellation_discount' => 'لا يمكن خفض المبلغ القابل للاسترداد إلى أقل من الاستردادات المعتمدة غير المدفوعة.']);
            }
            $oldDiscount = Money::toMinorUnits((string) $cancellation->cancellation_discount);
            $oldRefundDue = Money::toMinorUnits((string) $cancellation->refund_due);
            $cancellation->update(['cancellation_discount' => Money::fromMinorUnits($newDiscount), 'refund_due' => Money::fromMinorUnits($newRefundDue)]);
            FinancialCorrection::query()->create([
                'order_id' => $locked->id, 'subject_type' => Cancellation::class, 'subject_id' => $cancellation->id,
                'field_name' => 'cancellation_discount', 'old_amount' => Money::fromMinorUnits($oldDiscount),
                'new_amount' => Money::fromMinorUnits($newDiscount), 'reason' => trim($data['reason']),
                'approved_by' => $request->user()->id, 'occurred_at' => now(),
            ]);
            $audit->record('cancellation_corrected', $cancellation, $cancellation->id, ['cancellation_discount', 'refund_due'], trim($data['reason']));
        });

        return back()->with('status', 'حُفظ تصحيح قرار الإلغاء مع القيمة السابقة والجديدة والسبب.');
    }
}
