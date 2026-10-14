<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductionJob;
use App\Models\TailorDispatch;
use App\Models\TailorDispatchItem;
use App\Models\TailorReceipt;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductionController extends Controller
{
    public function index(): View
    {
        Gate::authorize('production.manage');
        $jobs = ProductionJob::query()->with([
            'order.guardian', 'orderItem.child', 'orderItem.measurementSnapshot',
            'dispatches.items.receipts.receipt', 'dispatches.items.receipts',
        ])->latest('id')->paginate(20);
        $eligibleItems = OrderItem::query()->with(['order.guardian', 'child'])
            ->whereHas('order', fn (Builder $orders) => $orders->whereIn('type', ['custom', 'alteration'])->whereNotIn('operational_status', ['cancelled', 'delivered']))
            ->whereDoesntHave('productionJob')->orderByDesc('id')->limit(100)->get();

        return view('production.index', compact('jobs', 'eligibleItems'));
    }

    public function storeJob(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('production.manage');
        $data = $request->validate([
            'order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'expected_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $item = OrderItem::query()->with('order')->findOrFail($data['order_item_id']);
        if (! in_array($item->order->type, ['custom', 'alteration'], true) || in_array($item->order->operational_status, ['cancelled', 'delivered'], true)) {
            throw ValidationException::withMessages(['order_item_id' => 'لا يمكن بدء إنتاج هذا البند.']);
        }
        if ((int) $data['quantity'] !== (int) $item->quantity) {
            throw ValidationException::withMessages(['quantity' => 'يجب أن يطابق سجل الإنتاج كمية البند كاملة؛ الاستلام الجزئي يسجل في عمليات الاستلام.']);
        }
        if ($item->productionJob()->exists()) {
            throw ValidationException::withMessages(['order_item_id' => 'يوجد سجل إنتاج لهذا البند بالفعل.']);
        }

        DB::transaction(function () use ($data, $item, $audit): void {
            $job = ProductionJob::query()->create([
                'order_id' => $item->order_id,
                'order_item_id' => $item->id,
                'quantity' => $data['quantity'],
                'stage' => 'sewing',
                'expected_on' => $data['expected_on'] ?? $item->order->due_on,
                'notes' => $data['notes'] ?? null,
            ]);
            if ($item->order->operational_status === 'new') {
                $item->order->update(['operational_status' => 'in_progress']);
            }
            $audit->record('production_job_created', $job, $job->id, ['order_item_id', 'quantity', 'expected_on']);
        });

        return back()->with('status', 'تم إنشاء سجل الإنتاج.');
    }

    public function updateStage(Request $request, ProductionJob $job, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('production.manage');
        $data = $request->validate([
            'stage' => ['required', Rule::in(['not_started', 'sewing', 'with_tailor', 'waiting_completion', 'completed'])],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $oldStage = $job->stage;
        $job->update(['stage' => $data['stage']]);
        $audit->record('production_stage_changed', $job, $job->id, ['stage'], $data['reason'].' ('.$oldStage.' → '.$data['stage'].')');

        return back()->with('status', 'تم تحديث مرحلة الإنتاج مع تسجيل السبب.');
    }

    public function storeDispatch(Request $request, ProductionJob $job, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('production.manage');
        $data = $request->validate([
            'recipient_name' => ['required', 'string', 'max:160'],
            'sent_on' => ['required', 'date'],
            'quantity_sent' => ['required', 'integer', 'min:1', 'max:500'],
            'agreed_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($job, $data, $request, $audit): void {
            $job = ProductionJob::query()->whereKey($job->id)->lockForUpdate()->firstOrFail();
            if (in_array($job->order->operational_status, ['cancelled', 'delivered'], true)) {
                throw ValidationException::withMessages(['quantity_sent' => 'الطلب ملغي أو مكتمل التسليم.']);
            }
            $alreadySent = (int) TailorDispatchItem::query()->where('order_item_id', $job->order_item_id)->sum('quantity_sent');
            if ($alreadySent + $data['quantity_sent'] > $job->quantity) {
                throw ValidationException::withMessages(['quantity_sent' => 'الكمية المرسلة تتجاوز الكمية المتبقية للإنتاج.']);
            }
            $dispatch = TailorDispatch::query()->create([
                'production_job_id' => $job->id,
                'recipient_name' => trim($data['recipient_name']),
                'sent_on' => $data['sent_on'],
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);
            $dispatchItem = $dispatch->items()->create([
                'order_item_id' => $job->order_item_id,
                'quantity_sent' => $data['quantity_sent'],
                'agreed_cost' => isset($data['agreed_cost']) ? Money::fromMinorUnits(Money::toMinorUnits($data['agreed_cost'])) : null,
            ]);
            $job->update(['stage' => 'with_tailor']);
            $audit->record('tailor_dispatch_created', $dispatch, $dispatch->id, ['production_job_id', 'quantity_sent', 'agreed_cost']);
            $audit->record('tailor_dispatch_item_created', $dispatchItem, $dispatchItem->id, ['quantity_sent', 'agreed_cost']);
        });

        return back()->with('status', 'تم تسجيل إرسال الكمية إلى الترزي.');
    }

    public function storeReceipt(Request $request, TailorDispatch $dispatch, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('production.manage');
        $data = $request->validate([
            'received_on' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.dispatch_item_id' => ['required', 'integer', 'distinct', 'exists:tailor_dispatch_items,id'],
            'items.*.quantity_received' => ['required', 'integer', 'min:1', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($dispatch, $data, $request, $audit): void {
            $dispatch = TailorDispatch::query()->whereKey($dispatch->id)->lockForUpdate()->firstOrFail();
            $receipt = TailorReceipt::query()->create([
                'tailor_dispatch_id' => $dispatch->id,
                'received_on' => $data['received_on'],
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);
            foreach ($data['items'] as $index => $item) {
                $dispatchItem = $dispatch->items()->whereKey($item['dispatch_item_id'])->lockForUpdate()->first();
                if (! $dispatchItem) {
                    throw ValidationException::withMessages(["items.$index.dispatch_item_id" => 'البند لا يتبع عملية الإرسال هذه.']);
                }
                $alreadyReceived = (int) $dispatchItem->receipts()->sum('quantity_received');
                if ($alreadyReceived + $item['quantity_received'] > $dispatchItem->quantity_sent) {
                    throw ValidationException::withMessages(["items.$index.quantity_received" => 'الكمية المستلمة تتجاوز الكمية المرسلة.']);
                }
                $receiptItem = $receipt->items()->create([
                    'tailor_dispatch_item_id' => $dispatchItem->id,
                    'quantity_received' => $item['quantity_received'],
                ]);
                $audit->record('tailor_receipt_item_created', $receiptItem, $receiptItem->id, ['quantity_received']);
            }
            $audit->record('tailor_receipt_created', $receipt, $receipt->id, ['tailor_dispatch_id', 'received_on']);
            $this->recalculateReceiptStatus($dispatch->productionJob->order);
        });

        return back()->with('status', 'تم تسجيل الاستلام. الكمية المتبقية لدى الترزي حُسبت من سجلات الإرسال والاستلام.');
    }

    private function recalculateReceiptStatus(Order $order): void
    {
        $order->load('items.productionJob');
        if (in_array($order->operational_status, ['cancelled', 'delivered'], true)) {
            return;
        }
        $requiredQuantity = (int) $order->items->sum('quantity');
        $receivedQuantity = 0;
        foreach ($order->items as $item) {
            $receivedQuantity += (int) DB::table('tailor_receipt_items')
                ->join('tailor_dispatch_items', 'tailor_dispatch_items.id', '=', 'tailor_receipt_items.tailor_dispatch_item_id')
                ->where('tailor_dispatch_items.order_item_id', $item->id)
                ->sum('tailor_receipt_items.quantity_received');
        }
        if ($requiredQuantity > 0 && $receivedQuantity > 0) {
            $order->update(['operational_status' => $receivedQuantity >= $requiredQuantity ? 'fully_received' : 'partially_received']);
        }
    }
}
