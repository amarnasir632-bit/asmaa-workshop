<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvidence;
use App\Models\Role;
use App\Models\User;
use App\Services\OrderFinancialSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentRecordingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
        Storage::fake('private');
    }

    public function test_cash_counts_immediately_and_pending_transfer_does_not_count_until_manager_verifies(): void
    {
        $employee = $this->staff('payments.employee', 'employee');
        $manager = $this->staff('payments.manager', 'manager');
        $order = $this->order($employee);
        $summary = app(OrderFinancialSummary::class);

        $this->actingAs($employee)->post(route('payments.store'), ['order_id' => $order->id, 'amount' => '40.00', 'method' => 'cash', 'paid_at' => now()->format('Y-m-d H:i:s')])->assertRedirect();
        $this->post(route('payments.store'), ['order_id' => $order->id, 'amount' => '60.00', 'method' => 'bank_transfer', 'paid_at' => now()->format('Y-m-d H:i:s'), 'transaction_reference' => 'TX-001'])->assertRedirect();
        $this->assertSame(4000, $summary->for($order->fresh())['confirmed']);
        $this->assertSame(6000, $summary->for($order->fresh())['due']);

        $transfer = Payment::query()->where('method', 'bank_transfer')->firstOrFail();
        $this->post(route('payments.verify', $transfer))->assertForbidden();
        $this->actingAs($manager)->post(route('payments.verify', $transfer))->assertRedirect();
        $this->assertSame(10000, $summary->for($order->fresh())['confirmed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'bank_transfer_verified', 'subject_id' => $transfer->id]);
        $this->get(route('payments.index'))->assertOk()->assertSee('المسجل')->assertSee('المؤكد');
    }

    public function test_duplicate_transfer_reference_and_duplicate_evidence_do_not_create_duplicate_receipts(): void
    {
        $employee = $this->staff('payments.duplicate', 'employee');
        $order = $this->order($employee);
        $base = ['order_id' => $order->id, 'amount' => '25.00', 'method' => 'bank_transfer', 'paid_at' => now()->format('Y-m-d H:i:s'), 'transaction_reference' => 'SAME-REF'];
        $this->actingAs($employee)->post(route('payments.store'), $base + ['evidence' => UploadedFile::fake()->createWithContent('receipt.pdf', 'synthetic transfer notice')])->assertRedirect();
        $this->post(route('payments.store'), $base)->assertSessionHasErrors('transaction_reference');
        $this->assertDatabaseCount('payments', 1);

        $second = $base;
        $second['transaction_reference'] = 'SECOND-REF';
        $this->post(route('payments.store'), $second + ['evidence' => UploadedFile::fake()->createWithContent('receipt-copy.pdf', 'synthetic transfer notice')])->assertSessionHasErrors('evidence');
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('payment_evidence', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment_recorded']);
    }

    public function test_employee_cannot_download_private_transfer_evidence_and_overpayment_is_rejected(): void
    {
        $employee = $this->staff('payments.private', 'employee');
        $order = $this->order($employee);
        $this->actingAs($employee)->post(route('payments.store'), ['order_id' => $order->id, 'amount' => '100.01', 'method' => 'cash', 'paid_at' => now()->format('Y-m-d H:i:s')])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payments', 0);
        $this->post(route('payments.store'), ['order_id' => $order->id, 'amount' => '10.00', 'method' => 'bank_transfer', 'paid_at' => now()->format('Y-m-d H:i:s'), 'evidence' => UploadedFile::fake()->createWithContent('notice.pdf', 'synthetic notice')])->assertRedirect();
        $evidence = PaymentEvidence::query()->firstOrFail();
        $this->get(route('payments.evidence', $evidence))->assertForbidden();
        $this->assertSame(0, app(OrderFinancialSummary::class)->for($order->fresh())['confirmed']);
    }

    private function order(User $creator): Order
    {
        $guardian = Guardian::query()->create(['name' => 'ولي اصطناعي', 'phone' => '000000003']);

        return Order::query()->create([
            'number' => 'TEST-'.uniqid(), 'type' => 'custom', 'guardian_id' => $guardian->id,
            'customer_name_snapshot' => $guardian->name, 'created_by' => $creator->id, 'created_on' => now()->toDateString(),
            'operational_status' => 'new', 'items_total' => '100.00', 'discount_total' => '0.00', 'approved_total' => '100.00',
        ]);
    }

    private function staff(string $username, string $roleName): User
    {
        $user = User::factory()->create(['username' => $username]);
        $user->roles()->attach(Role::query()->where('name', $roleName)->value('id'));

        return $user;
    }
}
