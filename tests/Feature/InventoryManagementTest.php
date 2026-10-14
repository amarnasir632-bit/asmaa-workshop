<?php

namespace Tests\Feature;

use App\Models\GarmentType;
use App\Models\InventoryAdjustment;
use App\Models\InventoryBalance;
use App\Models\InventoryVariant;
use App\Models\OpeningInventoryBatch;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
    }

    public function test_opening_stock_is_inactive_until_manager_approval_and_creates_ledger_movement(): void
    {
        $manager = $this->staff('inventory.manager', 'manager');
        $variant = $this->variant();
        $this->actingAs($manager)->post(route('inventory.opening-batches.store'), [
            'counted_on' => now()->toDateString(),
            'entries' => [['inventory_variant_id' => $variant->id, 'quantity_counted' => 5]],
        ])->assertSessionHasNoErrors();
        $batch = OpeningInventoryBatch::query()->firstOrFail();
        $this->assertSame(0, (int) (InventoryBalance::query()->where('inventory_variant_id', $variant->id)->value('quantity_available') ?? 0));

        $this->post(route('inventory.opening-batches.approve', $batch))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('inventory_balances', ['inventory_variant_id' => $variant->id, 'quantity_available' => 5]);
        $this->assertDatabaseHas('inventory_movements', ['inventory_variant_id' => $variant->id, 'quantity_change' => 5, 'movement_type' => 'opening']);
        $this->post(route('inventory.opening-batches.approve', $batch))->assertSessionHasErrors('batch');
    }

    public function test_adjustment_cannot_make_stock_negative_and_successful_change_has_audit_and_movement(): void
    {
        $manager = $this->staff('inventory.adjust.manager', 'manager');
        $variant = $this->variant();
        InventoryBalance::query()->create(['inventory_variant_id' => $variant->id, 'quantity_available' => 2]);

        $this->actingAs($manager)->post(route('inventory.adjustments.store'), [
            'reason' => 'تصحيح جرد اختباري',
            'items' => [['inventory_variant_id' => $variant->id, 'quantity_delta' => -3, 'reason' => 'نقص فعلي']],
        ])->assertSessionHasNoErrors();
        $adjustment = InventoryAdjustment::query()->firstOrFail();
        $this->post(route('inventory.adjustments.approve', $adjustment))->assertSessionHasErrors('adjustment');
        $this->assertSame(2, (int) $adjustment->items()->firstOrFail()->variant->balance()->value('quantity_available'));
        $this->assertDatabaseCount('inventory_movements', 0);

        $adjustment->items()->firstOrFail()->update(['quantity_delta' => 1]);
        $this->post(route('inventory.adjustments.approve', $adjustment))->assertSessionHasNoErrors();
        $this->assertSame(3, (int) $variant->balance()->value('quantity_available'));
        $this->assertDatabaseHas('inventory_movements', ['inventory_variant_id' => $variant->id, 'quantity_change' => 1, 'movement_type' => 'adjustment_in']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'inventory_adjustment_approved']);
    }

    public function test_employee_cannot_create_opening_stock_or_adjust_inventory(): void
    {
        $employee = $this->staff('inventory.employee', 'employee');
        $variant = $this->variant();
        $this->actingAs($employee)->post(route('inventory.opening-batches.store'), [
            'counted_on' => now()->toDateString(),
            'entries' => [['inventory_variant_id' => $variant->id, 'quantity_counted' => 99]],
        ])->assertForbidden();
        $this->assertDatabaseCount('opening_inventory_batches', 0);
        $this->get(route('inventory.index'))->assertOk()->assertSee('المخزون الجاهز');
    }

    private function variant(): InventoryVariant
    {
        return InventoryVariant::query()->create([
            'school_id' => School::query()->create(['name' => 'مدرسة مخزون'])->id,
            'garment_type_id' => GarmentType::query()->firstOrFail()->id,
            'size' => '10',
            'attributes_key' => hash('sha256', random_bytes(16)),
        ]);
    }

    private function staff(string $username, string $roleName): User
    {
        $user = User::factory()->create(['username' => $username]);
        $user->roles()->attach(Role::query()->where('name', $roleName)->value('id'));

        return $user;
    }
}
