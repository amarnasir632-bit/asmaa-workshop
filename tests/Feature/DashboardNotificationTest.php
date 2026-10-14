<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\InternalNotification;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
    }

    public function test_dashboard_shows_financial_summary_and_idempotent_internal_notifications(): void
    {
        $manager = $this->staff('dashboard.notifications.manager', 'manager');
        $guardian = Guardian::query()->create(['name' => 'عميل لوحة تجريبي', 'phone' => '000000017']);
        $overdue = $this->order($manager, $guardian, 'NOTIFY-OVERDUE', 'in_progress', '50.00', now()->subDay()->toDateString());
        $ready = $this->order($manager, $guardian, 'NOTIFY-READY', 'ready', '0.00');

        $this->actingAs($manager)->get(route('dashboard'))->assertOk()->assertSee('مبيعات الشهر')->assertSee('التحصيل المؤكد')->assertSee('الديون الحالية')->assertSee('طلب متأخر')->assertSee('طلب جاهز')->assertSee('رصيد مستحق');
        $this->get(route('dashboard'))->assertOk();
        $this->assertDatabaseCount('notifications', 3);

        $debtNotice = InternalNotification::query()->where('type', 'order_balance_due')->firstOrFail();
        $this->post(route('notifications.read', $debtNotice))->assertRedirect(route('dashboard'));
        $this->assertNotNull($debtNotice->fresh()->read_at);
        $other = $this->staff('dashboard.notifications.other', 'employee');
        $this->actingAs($other)->post(route('notifications.read', $debtNotice))->assertNotFound();

        $this->actingAs($manager)->post(route('payments.store'), ['order_id' => $overdue->id, 'amount' => '50.00', 'method' => 'cash', 'paid_at' => now()->format('Y-m-d H:i:s')])->assertRedirect();
        $this->get(route('dashboard'))->assertOk();
        $this->assertNotNull($debtNotice->fresh()->resolved_at);
        $this->assertDatabaseCount('notifications', 3);
    }

    private function order(User $creator, Guardian $guardian, string $number, string $status, string $amount, ?string $due = null): Order
    {
        return Order::query()->create(['number' => $number, 'type' => 'custom', 'guardian_id' => $guardian->id, 'customer_name_snapshot' => $guardian->name, 'created_by' => $creator->id, 'created_on' => now()->toDateString(), 'due_on' => $due, 'operational_status' => $status, 'items_total' => $amount, 'discount_total' => '0.00', 'approved_total' => $amount]);
    }

    private function staff(string $username, string $roleName): User
    {
        $user = User::factory()->create(['username' => $username]);
        $user->roles()->attach(Role::query()->where('name', $roleName)->value('id'));

        return $user;
    }
}
