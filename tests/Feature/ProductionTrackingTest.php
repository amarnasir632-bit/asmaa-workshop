<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Order;
use App\Models\Role;
use App\Models\TailorDispatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
    }

    public function test_dispatch_and_partial_receipt_are_recorded_without_exceeding_sent_quantity(): void
    {
        $employee = $this->staff('production.employee');
        $order = $this->makeOrder($employee);
        $item = $order->items()->firstOrFail();
        $this->actingAs($employee)->post(route('production.jobs.store'), [
            'order_item_id' => $item->id, 'quantity' => 3, 'expected_on' => now()->addWeek()->toDateString(),
        ])->assertSessionHasNoErrors();
        $job = $item->productionJob()->firstOrFail();

        $this->post(route('production.dispatches.store', $job), [
            'recipient_name' => 'ترزي تجريبي', 'sent_on' => now()->toDateString(), 'quantity_sent' => 2, 'agreed_cost' => '25.50',
        ])->assertSessionHasNoErrors();
        $dispatch = TailorDispatch::query()->firstOrFail();
        $dispatchItem = $dispatch->items()->firstOrFail();

        $receiptPayload = ['received_on' => now()->toDateString(), 'items' => [['dispatch_item_id' => $dispatchItem->id, 'quantity_received' => 1]]];
        $this->post(route('production.receipts.store', $dispatch), $receiptPayload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tailor_receipt_items', ['tailor_dispatch_item_id' => $dispatchItem->id, 'quantity_received' => 1]);
        $this->assertSame('partially_received', $order->fresh()->operational_status);

        $tooMany = $receiptPayload;
        $tooMany['items'][0]['quantity_received'] = 2;
        $this->post(route('production.receipts.store', $dispatch), $tooMany)->assertSessionHasErrors('items.0.quantity_received');
        $this->assertSame(1, (int) $dispatchItem->receipts()->sum('quantity_received'));

        $this->post(route('production.receipts.store', $dispatch), $receiptPayload)->assertSessionHasNoErrors();
        $this->assertSame(2, (int) $dispatchItem->receipts()->sum('quantity_received'));
        $this->post(route('production.dispatches.store', $job), [
            'recipient_name' => 'ترزي تجريبي', 'sent_on' => now()->toDateString(), 'quantity_sent' => 1,
        ])->assertSessionHasNoErrors();
        $lastDispatch = TailorDispatch::query()->latest('id')->firstOrFail();
        $lastDispatchItem = $lastDispatch->items()->firstOrFail();
        $this->post(route('production.receipts.store', $lastDispatch), [
            'received_on' => now()->toDateString(),
            'items' => [['dispatch_item_id' => $lastDispatchItem->id, 'quantity_received' => 1]],
        ])->assertSessionHasNoErrors();
        $this->assertSame('fully_received', $order->fresh()->operational_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tailor_dispatch_created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tailor_receipt_created']);
    }

    public function test_cannot_produce_or_dispatch_more_than_the_ordered_quantity(): void
    {
        $employee = $this->staff('production.limits.employee');
        $order = $this->makeOrder($employee);
        $item = $order->items()->firstOrFail();
        $this->actingAs($employee)->post(route('production.jobs.store'), ['order_item_id' => $item->id, 'quantity' => 2])
            ->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('production_jobs', 0);
    }

    public function test_overdue_warning_is_derived_from_expected_date_and_production_stage(): void
    {
        $employee = $this->staff('production.overdue.employee');
        $order = $this->makeOrder($employee);
        $item = $order->items()->firstOrFail();
        $this->actingAs($employee)->post(route('production.jobs.store'), [
            'order_item_id' => $item->id,
            'quantity' => $item->quantity,
            'expected_on' => now(config('app.display_timezone'))->subDay()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->get(route('production.index'))->assertOk()->assertSee('متأخر');
        $this->assertSame('sewing', $item->productionJob()->value('stage'));
    }

    private function makeOrder(User $employee): Order
    {
        $guardian = Guardian::query()->create(['name' => 'ولي إنتاج اختباري', 'phone' => '000000099']);
        $order = Order::query()->create([
            'number' => 'ORD-TEST-'.strtoupper(bin2hex(random_bytes(3))), 'type' => 'custom', 'guardian_id' => $guardian->id,
            'customer_name_snapshot' => $guardian->name, 'created_by' => $employee->id, 'created_on' => now()->toDateString(),
            'operational_status' => 'new', 'items_total' => '300.00', 'discount_total' => '0.00', 'approved_total' => '300.00',
        ]);
        $order->items()->create([
            'description_snapshot' => 'زي تجريبي', 'quantity' => 3, 'unit_price' => '100.00', 'discount_amount' => '0.00', 'line_total' => '300.00',
        ]);

        return $order;
    }

    private function staff(string $username): User
    {
        $user = User::factory()->create(['username' => $username]);
        $user->roles()->attach(Role::query()->where('name', 'employee')->value('id'));

        return $user;
    }
}
