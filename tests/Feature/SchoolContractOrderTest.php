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

    private function staff(string $username): User
    {
        $user = User::factory()->create(['username' => $username]);
        $user->roles()->attach(Role::query()->where('name', 'employee')->value('id'));

        return $user;
    }
}
