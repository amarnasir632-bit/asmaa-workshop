<?php

namespace Tests\Feature;

use App\Models\Cancellation;
use App\Models\DeliveryItem;
use App\Models\Guardian;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Role;
use App\Models\User;
use App\Services\OrderFinancialSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancellationRefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
    }

    public function test_manager_sets_cancellation_discount_and_refunds_remain_separate_from_original_payment(): void
    {
        $manager = $this->staff('refund.manager', 'manager');
        $order = $this->order($manager, '100.00');
        $this->actingAs($manager)->post(route('payments.store'), ['order_id' => $order->id, 'amount' => '100.00', 'method' => 'cash', 'paid_at' => now()->format('Y-m-d H:i:s')])->assertRedirect();
        $payment = Payment::query()->firstOrFail();
        $employee = $this->staff('refund.employee', 'employee');
        $this->actingAs($employee)->post(route('orders.cancel', $order), ['reason' => 'يجب رفضه', 'cancellation_discount' => '0'])->assertForbidden();
        $this->post(route('refunds.approve', $order), ['approved_amount' => '10.00', 'reason' => 'يجب رفضه'])->assertForbidden();
        $this->actingAs($manager);
        $this->post(route('orders.cancel', $order), ['reason' => 'إلغاء اصطناعي', 'cancellation_discount' => '20.00'])->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->operational_status);
        $this->assertDatabaseHas('cancellations', ['order_id' => $order->id, 'cancellation_discount' => '20.00', 'refund_due' => '80.00']);
        $cancellation = Cancellation::query()->firstOrFail();

        $this->post(route('refunds.approve', $order), ['approved_amount' => '60.00', 'reason' => 'استرداد جزئي', 'original_payment_id' => $payment->id])->assertRedirect();
        $refund = Refund::query()->firstOrFail();
        $this->assertSame($cancellation->id, $refund->cancellation_id);
        $this->post(route('refunds.approve', $order), ['approved_amount' => '25.00', 'reason' => 'يتجاوز المتاح'])->assertSessionHasErrors('approved_amount');
        $this->post(route('refunds.pay', $refund), ['amount' => '50.00', 'method' => 'cash', 'refunded_at' => now()->format('Y-m-d H:i:s')])->assertRedirect();
        $this->assertSame('100.00', $payment->fresh()->amount);
        $this->assertSame('50.00', $refund->fresh()->refunded_amount);
        $this->assertSame(5000, app(OrderFinancialSummary::class)->for($order->fresh())['refunded']);
        $this->assertDatabaseHas('refund_transactions', ['refund_id' => $refund->id, 'amount' => '50.00']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'order_cancelled']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'refund_approved']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'refund_paid']);
        $this->get(route('orders.show', $order))->assertOk()->assertSee('قرار الإلغاء')->assertSee('تصحيح موثق');
    }

    public function test_refund_after_delivery_requires_inspected_returned_item(): void
    {
        $manager = $this->staff('refund.return.manager', 'manager');
        $order = $this->order($manager, '40.00', 1);
        $this->actingAs($manager)->post(route('payments.store'), ['order_id' => $order->id, 'amount' => '40.00', 'method' => 'cash', 'paid_at' => now()->format('Y-m-d H:i:s')])->assertRedirect();
        $this->post(route('deliveries.store', $order), ['delivered_at' => now()->format('Y-m-d H:i:s'), 'received_by_name' => 'مستلم تجريبي', 'items' => [['order_item_id' => $order->items->first()->id, 'quantity_delivered' => 1]]])->assertRedirect();
        $this->post(route('refunds.approve', $order), ['approved_amount' => '10.00', 'reason' => 'إعادة قطعة'])->assertSessionHasErrors('returned_delivery_item_id');
        $deliveryItem = DeliveryItem::query()->firstOrFail();
        $this->post(route('refunds.approve', $order), ['approved_amount' => '10.00', 'reason' => 'إعادة قطعة', 'returned_delivery_item_id' => $deliveryItem->id, 'returned_quantity' => 1, 'returned_item_condition' => 'damaged', 'inspection_notes' => 'فحص تجريبي'])->assertRedirect();
        $this->assertDatabaseHas('refunds', ['returned_delivery_item_id' => $deliveryItem->id, 'returned_quantity' => 1, 'returned_item_condition' => 'damaged']);
    }

    public function test_manager_can_correct_a_refund_approval_only_with_a_reason_and_audit_record(): void
    {
        $manager = $this->staff('refund.correct.manager', 'manager');
        $order = $this->order($manager, '100.00');
        $this->actingAs($manager)->post(route('payments.store'), ['order_id' => $order->id, 'amount' => '100.00', 'method' => 'cash', 'paid_at' => now()->format('Y-m-d H:i:s')])->assertRedirect();
        $this->post(route('orders.cancel', $order), ['reason' => 'سبب تجريبي', 'cancellation_discount' => '10.00'])->assertRedirect();
        $this->post(route('refunds.approve', $order), ['approved_amount' => '50.00', 'reason' => 'اعتماد تجريبي'])->assertRedirect();
        $refund = Refund::query()->firstOrFail();
        $this->patch(route('refunds.correct', $refund), ['approved_amount' => '60.00', 'reason' => 'تصحيح موثق'])->assertRedirect();
        $this->assertSame('60.00', $refund->fresh()->approved_amount);
        $this->assertDatabaseHas('financial_corrections', ['order_id' => $order->id, 'field_name' => 'approved_amount', 'old_amount' => '50.00', 'new_amount' => '60.00', 'reason' => 'تصحيح موثق']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'refund_corrected', 'subject_id' => $refund->id]);
        $this->patch(route('refunds.correct', $refund), ['approved_amount' => '101.00', 'reason' => 'تجاوز السقف'])->assertSessionHasErrors('approved_amount');
    }

    private function order(User $creator, string $total, int $quantity = 1): Order
    {
        $guardian = Guardian::query()->create(['name' => 'ولي استرداد اصطناعي', 'phone' => '000000006']);
        $order = Order::query()->create(['number' => 'RFD-'.uniqid(), 'type' => 'custom', 'guardian_id' => $guardian->id, 'customer_name_snapshot' => $guardian->name, 'created_by' => $creator->id, 'created_on' => now()->toDateString(), 'operational_status' => 'ready', 'items_total' => $total, 'discount_total' => '0.00', 'approved_total' => $total]);
        $order->items()->create(['description_snapshot' => 'قطعة اختبار', 'quantity' => $quantity, 'unit_price' => $total, 'discount_amount' => '0.00', 'line_total' => $total]);

        return $order->load('items');
    }

    private function staff(string $username, string $roleName): User
    {
        $user = User::factory()->create(['username' => $username]);
        $user->roles()->attach(Role::query()->where('name', $roleName)->value('id'));

        return $user;
    }
}
