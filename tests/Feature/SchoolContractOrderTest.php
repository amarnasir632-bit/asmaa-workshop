<?php

namespace Tests\Feature;

use App\Models\CurrentMeasurement;
use App\Models\GarmentType;
use App\Models\Guardian;
use App\Models\Order;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolContractOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
    }

    public function test_school_contract_saves_each_beneficiary_item_and_price_snapshot(): void
    {
        $employee = $this->staff('contract.employee');
        $school = School::query()->create(['name' => 'مدرسة تعاقد اختباري']);
        $grade = $school->grades()->create(['name' => 'الصف الثاني']);
        $guardian = Guardian::query()->create(['name' => 'ولي طفل تعاقد', 'phone' => '000000321']);
        $child = $guardian->children()->create(['name' => 'طفل مرتبط', 'school_id' => $school->id, 'grade_id' => $grade->id]);
        CurrentMeasurement::query()->create(['child_id' => $child->id, 'shoulder_cm' => 32, 'updated_by' => $employee->id]);
        $shirt = GarmentType::query()->firstOrFail();
        $payload = [
            'school_id' => $school->id,
            'contract_number' => 'TEST-C-001',
            'beneficiaries' => [
                ['name' => $child->name, 'child_id' => $child->id, 'grade_id' => $grade->id, 'items' => [[
                    'garment_type_id' => $shirt->id, 'description' => 'قميص مدرسي', 'quantity' => 2,
                    'unit_price' => '100.00', 'discount' => '10.00',
                ]]],
                ['name' => 'مستفيد بلا ملف', 'grade_id' => $grade->id, 'items' => [[
                    'description' => 'بنطال', 'quantity' => 1, 'unit_price' => '50.00', 'discount' => '0',
                ]]],
            ],
        ];

        $response = $this->actingAs($employee)->post(route('school-contracts.store'), $payload);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHasNoErrors();
        $order = Order::query()->with(['schoolContract.beneficiaries', 'items.measurementSnapshot'])->firstOrFail();
        $response->assertRedirect(route('orders.show', $order));
        $this->assertSame('240.00', $order->approved_total);
        $this->assertSame(2, $order->items()->count());
        $this->assertSame(2, $order->schoolContract->beneficiaries()->count());
        $this->assertSame(32.0, (float) $order->items()->where('child_id', $child->id)->firstOrFail()->measurementSnapshot->shoulder_cm);
        $this->assertDatabaseHas('audit_logs', ['action' => 'school_contract_order_created', 'subject_id' => $order->id]);
        $this->get(route('orders.show', $order))->assertOk()->assertSee('مستفيد بلا ملف');
    }

    public function test_school_contract_rejects_grade_or_child_from_a_different_school(): void
    {
        $employee = $this->staff('contract.validation.employee');
        $firstSchool = School::query()->create(['name' => 'المدرسة الأولى']);
        $secondSchool = School::query()->create(['name' => 'المدرسة الثانية']);
        $grade = $firstSchool->grades()->create(['name' => 'الصف']);
        $guardian = Guardian::query()->create(['name' => 'ولي آخر', 'phone' => '000000322']);
        $child = $guardian->children()->create(['name' => 'طفل مدرسة ثانية', 'school_id' => $secondSchool->id]);
        $payload = [
            'school_id' => $firstSchool->id,
            'beneficiaries' => [[
                'name' => $child->name, 'child_id' => $child->id, 'grade_id' => $grade->id,
                'items' => [['description' => 'بند', 'quantity' => 1, 'unit_price' => '10']],
            ]],
        ];

        $this->actingAs($employee)->post(route('school-contracts.store'), $payload)->assertSessionHasErrors('beneficiaries.0.child_id');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_school_contract_with_bulk_grades_and_initial_payment_tracks_paid_and_due_amounts(): void
    {
        $employee = $this->staff('contract.bulk.employee');
        $school = School::query()->create(['name' => 'مدرسة التميز النموذجية']);
        $grade1 = $school->grades()->create(['name' => 'الصف الأول']);
        $grade2 = $school->grades()->create(['name' => 'الصف الثاني']);

        $payload = [
            'school_id' => $school->id,
            'contract_number' => 'BULK-2026-01',
            'beneficiaries' => [
                [
                    'name' => '', // ترك الاسم فارغاً ليُؤخذ تلقائياً من المرحلة
                    'grade_id' => $grade1->id,
                    'items' => [
                        ['description' => 'زي الصف الأول كامل', 'quantity' => 20, 'unit_price' => '150.00', 'discount' => '0'],
                    ],
                ],
                [
                    'name' => 'دفعة الصف الثاني بنين',
                    'grade_id' => $grade2->id,
                    'items' => [
                        ['description' => 'قميص وبنطلون', 'quantity' => 30, 'unit_price' => '100.00', 'discount' => '0'],
                    ],
                ],
            ],
            'initial_payment' => [
                'amount' => '2500.00',
                'method' => 'cash',
                'paid_at' => now()->format('Y-m-d\TH:i'),
                'notes' => 'دفعة أولى نقداً من إدارة المدرسة',
            ],
        ];

        $response = $this->actingAs($employee)->post(route('school-contracts.store'), $payload);
        $response->assertSessionHasNoErrors();

        $order = Order::query()->with(['schoolContract.beneficiaries', 'items', 'payments'])->firstOrFail();
        $response->assertRedirect(route('orders.show', $order));

        // التحقق من الحسابات: 20*150 = 3000، 30*100 = 3000، الإجمالي = 6000
        $this->assertSame('6000.00', $order->approved_total);
        $this->assertSame(50, (int) $order->items()->sum('quantity'));
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'amount' => '2500.00',
            'method' => 'cash',
            'verification_status' => 'not_required',
        ]);

        // التحقق من التسمية التلقائية للمرحلة
        $this->assertSame('دفعة الصف الأول', $order->schoolContract->beneficiaries()->where('grade_snapshot', 'الصف الأول')->value('name_snapshot'));
        $this->assertSame('دفعة الصف الثاني بنين', $order->schoolContract->beneficiaries()->where('grade_snapshot', 'الصف الثاني')->value('name_snapshot'));

        // فحص صفحة العرض
        $showResponse = $this->actingAs($employee)->get(route('orders.show', $order));
        $showResponse->assertOk()
            ->assertSee('50') // كم لبسة
            ->assertSee('2,500.00') // دفعوا كم
            ->assertSee('3,500.00'); // متبقي كم (6000 - 2500)
    }

    public function test_school_contract_rejects_initial_payment_exceeding_total_amount(): void
    {
        $employee = $this->staff('contract.overpay.employee');
        $school = School::query()->create(['name' => 'مدرسة الأمل']);
        $grade = $school->grades()->create(['name' => 'الصف الخامس']);

        $payload = [
            'school_id' => $school->id,
            'beneficiaries' => [
                [
                    'grade_id' => $grade->id,
                    'items' => [
                        ['description' => 'زي مدرسي', 'quantity' => 10, 'unit_price' => '100.00', 'discount' => '0'], // Total = 1000
                    ],
                ],
            ],
            'initial_payment' => [
                'amount' => '1500.00', // أكبر من 1000
                'method' => 'cash',
            ],
        ];

        $response = $this->actingAs($employee)->post(route('school-contracts.store'), $payload);
        $response->assertSessionHasErrors('initial_payment.amount');
        $this->assertDatabaseCount('orders', 0);
    }

    private function staff(string $username): User
    {
        $user = User::factory()->create(['username' => $username]);
        $user->roles()->attach(Role::query()->where('name', 'employee')->value('id'));

        return $user;
    }
}
