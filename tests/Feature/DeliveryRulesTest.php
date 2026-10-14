<?php

namespace Tests\Feature;

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

class DeliveryRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
    }

    public function test_unpaid_order_cannot_be_delivered_and_paid_order_supports_partial_quantities_without_overdelivery(): void
    {
        $employee = $this->staff('delivery.employee');
        $order = $this->order($employee, 3);
        $payload = ['delivered_at' => now()->format('Y-m-d H:i:s'), 'received_by_name' => 'مستلم تجريبي', 'items' => [['order_item_id' => $order->items->first()->id, 'quantity_delivered' => 1]]];

        $this->actingAs($employee)->post(route('deliveries.store', $order), $payload)->assertSessionHasErrors('payment');
        $this->post(route('payments.store'), ['order_id' => $order->id, 'amount' => '30.00', 'method' => 'cash', 'paid_at' => now()->format('Y-m-d H:i:s')])->assertRedirect();
        $this->post(route('deliveries.store', $order), $payload)->assertRedirect();
        $this->assertDatabaseHas('delivery_items', ['quantity_delivered' => 1]);

        $payload['items'][0]['quantity_delivered'] = 3;
        $this->post(route('deliveries.store', $order), $payload)->assertSessionHasErrors('items');
        $payload['items'][0]['quantity_delivered'] = 2;
        $this->post(route('deliveries.store', $order), $payload)->assertRedirect();
        $this->assertSame('delivered', $order->fresh()->operational_status);
        $this->assertDatabaseCount('deliveries', 2);
    }

    public function test_ready_delivery_decrements_stock_once_and_never_below_zero(): void
    {
        $manager = $this->staff('delivery.manager', 'manager');
        $school = School::query()->create(['name' => 'مدرسة اختبار تسليم']);
        $variant = InventoryVariant::query()->create(['school_id' => $school->id, 'garment_type_id' => GarmentType::query()->firstOrFail()->id, 'size' => '12', 'attributes_key' => hash('sha256', 'delivery-variant')]);
        InventoryBalance::query()->create(['inventory_variant_id' => $variant->id, 'quantity_available' => 2]);
        $order = $this->order($manager, 2, 'ready', $variant->id);
        $this->actingAs($manager)->post(route('payments.store'), ['order_id' => $order->id, 'amount' => '20.00', 'method' => 'cash', 'paid_at' => now()->format('Y-m-d H:i:s')])->assertRedirect();
        $payload = ['delivered_at' => now()->format('Y-m-d H:i:s'), 'received_by_name' => 'مستلم تجريبي', 'items' => [['order_item_id' => $order->items->first()->id, 'quantity_delivered' => 2]]];
        $this->post(route('deliveries.store', $order), $payload)->assertRedirect();
        $this->assertSame(0, (int) $variant->balance()->value('quantity_available'));
        $this->assertDatabaseHas('inventory_movements', ['inventory_variant_id' => $variant->id, 'quantity_change' => -2, 'movement_type' => 'ready_sale']);
        $this->post(route('deliveries.store', $order), $payload)->assertSessionHasErrors('items');
        $this->assertSame(0, (int) $variant->balance()->value('quantity_available'));
    }

    public function test_school_contract_cannot_be_partially_delivered_until_the_full_contract_is_paid(): void
    {
        $employee = $this->staff('delivery.school.employee');
        $school = School::query()->create(['name' => 'مدرسة عقد اختبار']);
        $order = $this->order($employee, 2);
        $order->schoolContract()->create(['school_id' => $school->id, 'contract_number' => 'CONTRACT-TEST']);
        $this->actingAs($employee)->post(route('payments.store'), ['order_id' => $order->id, 'amount' => '10.00', 'method' => 'cash', 'paid_at' => now()->format('Y-m-d H:i:s')])->assertRedirect();
        $payload = ['delivered_at' => now()->format('Y-m-d H:i:s'), 'received_by_name' => 'مستلم مدرسة', 'items' => [['order_item_id' => $order->items->first()->id, 'quantity_delivered' => 1]]];
        $this->post(route('deliveries.store', $order), $payload)->assertSessionHasErrors('payment');
        $this->post(route('payments.store'), ['order_id' => $order->id, 'amount' => '10.00', 'method' => 'cash', 'paid_at' => now()->format('Y-m-d H:i:s')])->assertRedirect();
        $this->post(route('deliveries.store', $order), $payload)->assertRedirect();
        $this->assertDatabaseCount('deliveries', 1);
    }

    private function order(User $creator, int $quantity, string $type = 'custom', ?int $variantId = null): Order
    {
        $guardian = Guardian::query()->create(['name' => 'ولي تسليم اصطناعي', 'phone' => '000000005']);
        $total = number_format($quantity * 10, 2, '.', '');
        $order = Order::query()->create(['number' => 'DLV-'.uniqid(), 'type' => $type, 'guardian_id' => $guardian->id, 'customer_name_snapshot' => $guardian->name, 'created_by' => $creator->id, 'created_on' => now()->toDateString(), 'operational_status' => 'ready', 'items_total' => $total, 'discount_total' => '0.00', 'approved_total' => $total]);
        $order->items()->create(['description_snapshot' => 'قميص اختبار', 'quantity' => $quantity, 'inventory_variant_id' => $variantId, 'unit_price' => '10.00', 'discount_amount' => '0.00', 'line_total' => $total]);

        return $order->load('items');
    }

    private function staff(string $username, string $roleName = 'employee'): User
    {
        $user = User::factory()->create(['username' => $username]);
        $user->roles()->attach(Role::query()->where('name', $roleName)->value('id'));

        return $user;
    }
}
