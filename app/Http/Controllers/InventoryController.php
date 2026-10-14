<?php

namespace App\Http\Controllers;

use App\Models\InventoryAdjustment;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\InventoryVariant;
use App\Models\OpeningInventoryBatch;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('inventory.view');
        $variants = InventoryVariant::query()->with(['school', 'grade', 'garmentType', 'balance'])
            ->orderBy('is_active', 'desc')->orderBy('id')->paginate(30);
        $openingBatches = OpeningInventoryBatch::query()->with('entries.variant.garmentType')->latest('id')->limit(10)->get();
        $adjustments = InventoryAdjustment::query()->with('items.variant.garmentType')->latest('id')->limit(10)->get();
        $movements = InventoryMovement::query()->with('variant.garmentType')->latest('occurred_at')->limit(30)->get();
        $activeVariants = InventoryVariant::query()->with(['school', 'grade', 'garmentType'])
            ->where('is_active', true)->whereDoesntHave('openingInventoryEntries', fn (Builder $query) => $query->whereHas('batch', fn (Builder $batch) => $batch->where('status', 'approved')))
            ->orderBy('id')->get();

        return view('inventory.index', compact('variants', 'openingBatches', 'adjustments', 'movements', 'activeVariants'));
    }

    public function storeOpeningBatch(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('inventory.manage');
        $data = $request->validate([
            'counted_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'entries' => ['required', 'array', 'min:1', 'max:200'],
            'entries.*.inventory_variant_id' => ['required', 'integer', 'distinct', 'exists:inventory_variants,id'],
            'entries.*.quantity_counted' => ['required', 'integer', 'min:0', 'max:1000000'],
            'entries.*.difference_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $batch = DB::transaction(function () use ($data, $request, $audit): OpeningInventoryBatch {
            $batch = OpeningInventoryBatch::query()->create([
                'reference' => $this->newReference('OPEN'),
                'counted_on' => $data['counted_on'],
                'status' => 'draft',
                'created_by' => $request->user()->id,
                'notes' => $data['notes'] ?? null,
            ]);
            foreach ($data['entries'] as $entry) {
                if (! InventoryVariant::query()->whereKey($entry['inventory_variant_id'])->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages(['entries' => 'الرصيد الافتتاحي لا يقبل صنفاً موقوفاً.']);
                }
                $batch->entries()->create([
                    'inventory_variant_id' => $entry['inventory_variant_id'],
                    'quantity_counted' => $entry['quantity_counted'],
                    'difference_reason' => $entry['difference_reason'] ?? null,
                ]);
            }
            $audit->record('opening_inventory_batch_created', $batch, $batch->id, ['counted_on', 'entries']);

            return $batch;
        });

        return back()->with('status', 'حُفظت دفعة الجرد الافتتاحي كمسودة. لا تدخل الكميات في الرصيد قبل اعتماد المدير. المرجع: '.$batch->reference);
    }

    public function approveOpeningBatch(OpeningInventoryBatch $batch, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('inventory.manage');
        DB::transaction(function () use ($batch, $audit): void {
            $batch = OpeningInventoryBatch::query()->whereKey($batch->id)->lockForUpdate()->with('entries')->firstOrFail();
            if ($batch->status !== 'draft') {
                throw ValidationException::withMessages(['batch' => 'هذه الدفعة ليست مسودة قابلة للاعتماد.']);
            }
            $variantIds = $batch->entries->pluck('inventory_variant_id')->sort()->values();
            InventoryVariant::query()->whereIn('id', $variantIds)->orderBy('id')->lockForUpdate()->get();
            foreach ($batch->entries as $entry) {
                if (InventoryMovement::query()->where('inventory_variant_id', $entry->inventory_variant_id)->exists()) {
                    throw ValidationException::withMessages(['batch' => 'سبق تسجيل حركة للصنف '.$entry->variant->description.'. استخدم تصحيح مخزون معتمد.']);
                }
            }
            $managerId = auth()->id();
            foreach ($batch->entries as $entry) {
                $balance = InventoryBalance::query()->firstOrCreate(['inventory_variant_id' => $entry->inventory_variant_id], ['quantity_available' => 0]);
                $balance = InventoryBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
                $balance->update(['quantity_available' => $balance->quantity_available + $entry->quantity_counted]);
                InventoryMovement::query()->create([
                    'inventory_variant_id' => $entry->inventory_variant_id,
                    'quantity_change' => $entry->quantity_counted,
                    'movement_type' => 'opening',
                    'opening_inventory_entry_id' => $entry->id,
                    'reason' => $entry->difference_reason ?: $batch->notes ?: 'رصيد افتتاحي بعد الجرد الفعلي',
                    'occurred_at' => now('UTC'),
                    'recorded_by' => $managerId,
                ]);
            }
            $batch->update(['status' => 'approved', 'approved_by' => $managerId, 'approved_at' => now('UTC')]);
            $audit->record('opening_inventory_batch_approved', $batch, $batch->id, ['status', 'approved_by', 'approved_at']);
        });

        return back()->with('status', 'اعتمد المدير الرصيد الافتتاحي وسُجلت حركاته.');
    }

    public function storeAdjustment(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('inventory.manage');
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.inventory_variant_id' => ['required', 'integer', 'distinct', 'exists:inventory_variants,id'],
            'items.*.quantity_delta' => ['required', 'integer', 'between:-1000000,1000000', 'not_in:0'],
            'items.*.reason' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($data, $request, $audit): void {
            $adjustment = InventoryAdjustment::query()->create([
                'reference' => $this->newReference('ADJ'),
                'status' => 'pending',
                'reason' => trim($data['reason']),
                'created_by' => $request->user()->id,
            ]);
            foreach ($data['items'] as $item) {
                $adjustment->items()->create([
                    'inventory_variant_id' => $item['inventory_variant_id'],
                    'quantity_delta' => $item['quantity_delta'],
                    'reason' => $item['reason'] ?? $data['reason'],
                ]);
            }
            $audit->record('inventory_adjustment_created', $adjustment, $adjustment->id, ['reason', 'items']);
        });

        return back()->with('status', 'سُجل طلب تصحيح المخزون بحالة انتظار الاعتماد.');
    }

    public function approveAdjustment(InventoryAdjustment $adjustment, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('inventory.manage');
        DB::transaction(function () use ($adjustment, $audit): void {
            $adjustment = InventoryAdjustment::query()->whereKey($adjustment->id)->lockForUpdate()->with('items')->firstOrFail();
            if ($adjustment->status !== 'pending') {
                throw ValidationException::withMessages(['adjustment' => 'التصحيح ليس بانتظار الاعتماد.']);
            }
            $variantIds = $adjustment->items->pluck('inventory_variant_id')->sort()->values();
            InventoryVariant::query()->whereIn('id', $variantIds)->orderBy('id')->lockForUpdate()->get();
            $balances = [];
            foreach ($adjustment->items as $item) {
                $balance = InventoryBalance::query()->where('inventory_variant_id', $item->inventory_variant_id)->lockForUpdate()->first();
                $current = (int) ($balance?->quantity_available ?? 0);
                if ($current + $item->quantity_delta < 0) {
                    throw ValidationException::withMessages(['adjustment' => 'التصحيح سيجعل رصيد أحد الأصناف سالباً. لم يُعتمد أي بند.']);
                }
                $balances[$item->id] = $balance;
            }
            foreach ($adjustment->items as $item) {
                $balance = $balances[$item->id] ?? InventoryBalance::query()->firstOrCreate(
                    ['inventory_variant_id' => $item->inventory_variant_id], ['quantity_available' => 0],
                );
                $balance->update(['quantity_available' => $balance->quantity_available + $item->quantity_delta]);
                InventoryMovement::query()->create([
                    'inventory_variant_id' => $item->inventory_variant_id,
                    'quantity_change' => $item->quantity_delta,
                    'movement_type' => $item->quantity_delta > 0 ? 'adjustment_in' : 'adjustment_out',
                    'inventory_adjustment_item_id' => $item->id,
                    'reason' => $item->reason ?: $adjustment->reason,
                    'occurred_at' => now('UTC'),
                    'recorded_by' => auth()->id(),
                ]);
            }
            $adjustment->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now('UTC')]);
            $audit->record('inventory_adjustment_approved', $adjustment, $adjustment->id, ['status', 'approved_by', 'approved_at']);
        });

        return back()->with('status', 'اعتمد المدير تصحيح المخزون وسُجلت الحركات المرتبطة.');
    }

    public function rejectAdjustment(Request $request, InventoryAdjustment $adjustment, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('inventory.manage');
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($adjustment, $data, $audit): void {
            $adjustment = InventoryAdjustment::query()->whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
            if ($adjustment->status !== 'pending') {
                throw ValidationException::withMessages(['adjustment' => 'التصحيح ليس بانتظار القرار.']);
            }
            $adjustment->update(['status' => 'rejected']);
            $audit->record('inventory_adjustment_rejected', $adjustment, $adjustment->id, ['status'], $data['rejection_reason']);
        });

        return back()->with('status', 'رُفض تصحيح المخزون وسُجل السبب.');
    }

    private function newReference(string $prefix): string
    {
        do {
            $reference = $prefix.'-'.now('Africa/Khartoum')->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
        } while (DB::table($prefix === 'OPEN' ? 'opening_inventory_batches' : 'inventory_adjustments')->where('reference', $reference)->exists());

        return $reference;
    }
}
