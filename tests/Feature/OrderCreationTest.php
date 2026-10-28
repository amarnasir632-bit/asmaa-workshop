<?php

namespace Tests\Feature;

use App\Models\CurrentMeasurement;
use App\Models\GarmentType;
use App\Models\Guardian;
use App\Models\InventoryBalance;
use App\Models\InventoryVariant;
use App\Models\Order;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
    }

    public function test_employee_can_create_order_with_frozen_prices_and_measurements(): void
    {
        $employee = $this->staff('orders.employee');
        $guardian = Guardian::query()->create(['name' => 'ولي أمر اصطناعي', 'phone' => '000000000']);
        $child = $guardian->children()->create(['name' => 'طفل اصطناعي']);
        CurrentMeasurement::query()->create([
            'child_id' => $child->id, 'shoulder_cm' => 30, 'chest_cm' => 60, 'sleeve_cm' => 45,
            'waist_circumference_cm' => 55, 'length_cm' => 85, 'updated_by' => $employee->id,
        ]);

        $response = $this->actingAs($employee)->post(route('orders.store'), [
            'type' => 'custom', 'guardian_id' => $guardian->id, 'due_on' => now()->addWeek()->toDateString(),
            'items' => [
                ['child_id' => $child->id, 'garment_type_id' => GarmentType::query()->firstOrFail()->id,
                    'description' => 'قميص مدرسة', 'quantity' => 2, 'unit_price' => '125.50', 'discount' => '10.25'],
                ['description' => 'تعديل كم', 'quantity' => 1, 'unit_price' => '30.00', 'discount' => '0'],
            ],
        ]);

        $order = Order::query()->firstOrFail();
        $response->assertRedirect(route('orders.show', $order));
        $this->assertSame('270.75', $order->approved_total);
        $this->assertSame('270.75', number_format((float) $order->items()->sum('line_total'), 2, '.', ''));
        $snapshot = $order->items()->where('child_id', $child->id)->firstOrFail()->measurementSnapshot;
        $this->assertSame(30.0, (float) $snapshot->shoulder_cm);

        CurrentMeasurement::query()->where('child_id', $child->id)->update(['shoulder_cm' => 37]);
        $this->assertSame(30.0, (float) $snapshot->fresh()->shoulder_cm);
        $this->assertSame('125.50', $order->items()->where('child_id', $child->id)->firstOrFail()->unit_price);
        $this->assertDatabaseHas('audit_logs', ['action' => 'order_created', 'subject_id' => $order->id]);
    }

    public function test_creation_rejects_child_from_another_guardian_and_discount_above_line_total(): void
    {
        $employee = $this->staff('orders.validation.employee');
        $firstGuardian = Guardian::query()->create(['name' => 'ولي أ', 'phone' => '000000001']);
        $secondGuardian = Guardian::query()->create(['name' => 'ولي ب', 'phone' => '000000002']);
        $child = $secondGuardian->children()->create(['name' => 'طفل آخر']);
        $base = ['type' => 'custom', 'guardian_id' => $firstGuardian->id];

        $this->actingAs($employee)->post(route('orders.store'), $base + ['items' => [[
            'child_id' => $child->id, 'description' => 'بند', 'quantity' => 1, 'unit_price' => '10', 'discount' => '0',
        ]]])->assertSessionHasErrors('items.0.child_id');

        $this->post(route('orders.store'), $base + ['items' => [[
            'description' => 'بند', 'quantity' => 1, 'unit_price' => '10', 'discount' => '10.01',
        ]]])->assertSessionHasErrors('items.0.discount');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_ready_sale_decrements_available_stock_atomically_and_cancel_restores_undelivered_units(): void
    {
        $employee = $this->staff('orders.ready.employee');
        $guardian = Guardian::query()->create(['name' => 'ولي جاهز', 'phone' => '000000100']);
        $variant = InventoryVariant::query()->create([
            'school_id' => School::query()->create(['name' => 'مدرسة بيع جاهز'])->id,
            'garment_type_id' => GarmentType::query()->firstOrFail()->id, 'size' => '10', 'attributes_key' => hash('sha256', 'ready-order-test'),
        ]);
        InventoryBalance::query()->create(['inventory_variant_id' => $variant->id, 'quantity_available' => 2]);
        $this->actingAs($employee)->get(route('orders.create'))->assertOk()->assertSee('بيع ملابس جاهزة من المخزون')->assertSee('المتاح 2');
        $payload = ['type' => 'ready', 'guardian_id' => $guardian->id, 'items' => [['inventory_variant_id' => $variant->id, 'description' => 'قميص جاهز', 'quantity' => 3, 'unit_price' => '50']]];
        $this->actingAs($employee)->post(route('orders.store'), $payload)->assertSessionHasErrors('items');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(2, (int) $variant->balance()->value('quantity_available'));

        $payload['items'][0]['quantity'] = 2;
        $this->post(route('orders.store'), $payload)->assertRedirect();
        $order = Order::query()->firstOrFail();
        $this->assertSame(0, (int) $variant->balance()->value('quantity_available'));
        $this->assertDatabaseHas('inventory_movements', ['order_item_id' => $order->items->first()->id, 'quantity_change' => -2, 'movement_type' => 'ready_sale']);

        $manager = User::factory()->create(['username' => 'orders.ready.manager']);
        $manager->roles()->attach(Role::query()->where('name', 'manager')->value('id'));
        $this->actingAs($manager)->post(route('orders.cancel', $order), ['reason' => 'إلغاء بيع غير مستلم', 'cancellation_discount' => '0'])->assertRedirect();
        $this->assertSame(2, (int) $variant->balance()->value('quantity_available'));
        $this->assertDatabaseHas('inventory_movements', ['order_item_id' => $order->items->first()->id, 'quantity_change' => 2, 'movement_type' => 'ready_sale_cancel']);
    }

    private function staff(string $username): User
    {
        $user = User::factory()->create(['username' => $username]);
        $user->roles()->attach(Role::query()->where('name', 'employee')->value('id'));

        return $user;
    }
}
