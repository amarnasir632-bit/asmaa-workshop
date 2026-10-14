<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Services\AuditLogger;
use App\Services\OrderFinancialSummary;
use App\Support\LocalDateTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeliveryController extends Controller
{
    public function store(Request $request, Order $order, OrderFinancialSummary $summary, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('deliveries.create');
        $data = $request->validate([
            'delivered_at' => ['required', 'date', 'before_or_equal:now'],
            'received_by_name' => ['required', 'string', 'max:160'],
            'receipt_number' => ['nullable', 'string', 'max:80', 'unique:deliveries,receipt_number'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'distinct', 'exists:order_items,id'],
            'items.*.quantity_delivered' => ['required', 'integer', 'min:0', 'max:500'],
        ]);
        $data['items'] = array_values(array_filter($data['items'], fn (array $item): bool => (int) $item['quantity_delivered'] > 0));
        if ($data['items'] === []) {
            throw ValidationException::withMessages(['items' => 'أدخل كمية واحدة على الأقل للتسليم.']);
        }

        DB::transaction(function () use ($request, $order, $data, $summary, $audit): void {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->operational_status === 'cancelled') {
                throw ValidationException::withMessages(['order' => 'لا يمكن تسليم طلب ملغي.']);
            }
            $financial = $summary->for($lockedOrder);
            if ($financial['due'] > 0) {
                throw ValidationException::withMessages(['payment' => 'لا يمكن التسليم قبل سداد الرصيد المستحق.']);
            }
            $items = $lockedOrder->items()->whereIn('id', collect($data['items'])->pluck('order_item_id'))->lockForUpdate()->get()->keyBy('id');
            if ($items->count() !== count($data['items'])) {
                throw ValidationException::withMessages(['items' => 'توجد بنود لا تتبع هذا الطلب.']);
            }
            $requested = [];
            foreach ($data['items'] as $line) {
                $item = $items->get((int) $line['order_item_id']);
                $alreadyDelivered = (int) DB::table('delivery_items')->where('order_item_id', $item->id)->sum('quantity_delivered');
                if ($alreadyDelivered + (int) $line['quantity_delivered'] > (int) $item->quantity) {
                    throw ValidationException::withMessages(['items' => 'الكمية المسلمة تتجاوز المتبقي للبند '.$item->description_snapshot.'.']);
                }
                $requested[] = ['item' => $item, 'quantity' => (int) $line['quantity_delivered'], 'delivered_before' => $alreadyDelivered];
            }

            $stockDemand = [];
            if ($lockedOrder->type === 'ready') {
                foreach ($requested as $lineIndex => $line) {
                    $variantId = $line['item']->inventory_variant_id;
                    if (! $variantId) {
                        throw ValidationException::withMessages(['items' => 'كل بند في بيع الملابس الجاهزة يجب أن يرتبط بصنف مخزني.']);
                    }
                    $alreadyDeducted = max(0, -(int) InventoryMovement::query()->where('order_item_id', $line['item']->id)->where('movement_type', 'ready_sale')->sum('quantity_change'));
                    $deduction = max(0, $line['delivered_before'] + $line['quantity'] - $alreadyDeducted);
                    $requested[$lineIndex]['stock_deduction'] = $deduction;
                    if ($deduction > 0) {
                        $stockDemand[$variantId] = ($stockDemand[$variantId] ?? 0) + $deduction;
                    }
                }
                foreach ($stockDemand as $variantId => $quantity) {
                    $balance = InventoryBalance::query()->where('inventory_variant_id', $variantId)->lockForUpdate()->first();
                    if (! $balance || $balance->quantity_available < $quantity) {
                        throw ValidationException::withMessages(['items' => 'الرصيد المخزني غير كافٍ لإتمام التسليم.']);
                    }
                }
            }

            $delivery = Delivery::query()->create([
                'order_id' => $lockedOrder->id, 'delivered_at' => LocalDateTime::toUtc($data['delivered_at']),
                'received_by_name' => trim($data['received_by_name']), 'receipt_number' => $data['receipt_number'] ?? null,
                'recorded_by' => $request->user()->id, 'notes' => $data['notes'] ?? null,
            ]);
            foreach ($requested as $line) {
                $deliveryItem = $delivery->items()->create([
                    'order_item_id' => $line['item']->id, 'quantity_delivered' => $line['quantity'],
                ]);
                if ($lockedOrder->type === 'ready' && $line['stock_deduction'] > 0) {
                    $balance = InventoryBalance::query()->where('inventory_variant_id', $line['item']->inventory_variant_id)->lockForUpdate()->firstOrFail();
                    $balance->decrement('quantity_available', $line['stock_deduction']);
                    InventoryMovement::query()->create([
                        'inventory_variant_id' => $line['item']->inventory_variant_id,
                        'quantity_change' => -$line['stock_deduction'], 'movement_type' => 'ready_sale',
                        'order_item_id' => $line['item']->id, 'delivery_item_id' => $deliveryItem->id,
                        'reason' => 'بيع وتسليم مخزون جاهز للطلب '.$lockedOrder->number,
                        'occurred_at' => LocalDateTime::toUtc($data['delivered_at']), 'recorded_by' => $request->user()->id,
                    ]);
                }
            }

            $remaining = false;
            foreach ($lockedOrder->items as $item) {
                $delivered = (int) DB::table('delivery_items')->join('deliveries', 'delivery_items.delivery_id', '=', 'deliveries.id')
                    ->where('deliveries.order_id', $lockedOrder->id)->where('delivery_items.order_item_id', $item->id)->sum('delivery_items.quantity_delivered');
                if ($delivered < $item->quantity) {
                    $remaining = true;
                }
            }
            if (! $remaining) {
                $lockedOrder->update(['operational_status' => 'delivered']);
            }
            $audit->record('delivery_recorded', $delivery, $delivery->id, ['order_id', 'items', 'received_by_name', 'delivered_at']);
        });

        return redirect()->route('orders.show', $order)->with('status', 'تم حفظ التسليم والكميات المرتبطة به.');
    }
}
