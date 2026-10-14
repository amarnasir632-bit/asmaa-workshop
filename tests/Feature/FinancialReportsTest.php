<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Guardian;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
    }

    public function test_manager_report_separates_sales_collections_refunds_expenses_and_debts(): void
    {
        $manager = $this->staff('reports.manager', 'manager');
        $guardian = Guardian::query()->create(['name' => 'عميل تقارير اصطناعي', 'phone' => '000000015']);
        $order = Order::query()->create(['number' => 'REPORT-1', 'type' => 'custom', 'guardian_id' => $guardian->id, 'customer_name_snapshot' => $guardian->name, 'created_by' => $manager->id, 'created_on' => now()->toDateString(), 'operational_status' => 'new', 'items_total' => '100.00', 'discount_total' => '0.00', 'approved_total' => '100.00']);
        $order->payments()->create(['amount' => '20.00', 'paid_at' => now(), 'method' => 'cash', 'verification_status' => 'not_required', 'recorded_by' => $manager->id]);
        $order->payments()->create(['amount' => '80.00', 'paid_at' => now(), 'method' => 'bank_transfer', 'verification_status' => 'pending', 'recorded_by' => $manager->id]);
        $order->refunds()->create(['approved_amount' => '5.00', 'refunded_amount' => '3.00', 'refunded_at' => now(), 'method' => 'cash', 'approved_by' => $manager->id, 'approved_at' => now(), 'reason' => 'استرداد اصطناعي']);
        $otherGuardian = Guardian::query()->create(['name' => 'ولي أمر آخر اصطناعي', 'phone' => '000000016']);
        Order::query()->create(['number' => 'REPORT-2', 'type' => 'custom', 'guardian_id' => $otherGuardian->id, 'customer_name_snapshot' => $otherGuardian->name, 'created_by' => $manager->id, 'created_on' => now()->toDateString(), 'operational_status' => 'in_progress', 'items_total' => '300.00', 'discount_total' => '0.00', 'approved_total' => '300.00']);
        $category = ExpenseCategory::query()->firstOrFail();
        Expense::query()->create(['expense_category_id' => $category->id, 'amount' => '5.00', 'spent_on' => now()->toDateString(), 'description' => 'مصروف تقارير', 'recorded_by' => $manager->id]);
        $this->assertSame(40000, app(FinancialReportService::class)->forPeriod(now()->toDateString(), now()->toDateString())['sales']);
        $this->assertSame(10000, app(FinancialReportService::class)->forPeriod(now()->toDateString(), now()->toDateString(), ['guardian_id' => $guardian->id])['sales']);

        $response = $this->actingAs($manager)->get(route('reports.index', ['from' => now()->toDateString(), 'to' => now()->toDateString(), 'guardian_id' => $guardian->id]))->assertOk();
        $response->assertSee('100.00')->assertSee('20.00')->assertSee('3.00')->assertSee('5.00')->assertSee('92.00')->assertSee('83.00');
        $this->actingAs($manager)->get(route('reports.index', ['from' => now()->toDateString(), 'to' => now()->toDateString(), 'guardian_id' => $guardian->id]))->assertOk()->assertSee('REPORT-1')->assertDontSee('REPORT-2')->assertDontSee('300.00');
        $this->actingAs($manager)->get(route('reports.index', ['from' => now()->toDateString(), 'to' => now()->toDateString(), 'status' => 'new']))->assertOk()->assertSee('REPORT-1')->assertDontSee('REPORT-2')->assertDontSee('300.00');

        $pdf = $this->actingAs($manager)->get(route('reports.pdf', ['from' => now()->toDateString(), 'to' => now()->toDateString(), 'guardian_id' => $guardian->id]))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertGreaterThan(1000, strlen($pdf->getContent()));
    }

    public function test_employee_can_read_operational_report_but_not_financial_data(): void
    {
        $employee = $this->staff('reports.employee', 'employee');
        $this->actingAs($employee)->get(route('reports.index'))->assertOk()->assertSee('الطلبات المسجلة')->assertDontSee('التحصيل المؤكد')->assertDontSee('الربح التقديري');
        $this->get(route('reports.index', ['from' => '2026-10-15', 'to' => '2026-10-14']))->assertSessionHasErrors('from');
        $this->get(route('reports.pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    private function staff(string $username, string $roleName): User
    {
        $user = User::factory()->create(['username' => $username]);
        $user->roles()->attach(Role::query()->where('name', $roleName)->value('id'));

        return $user;
    }
}
