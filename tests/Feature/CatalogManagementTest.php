<?php

namespace Tests\Feature;

use App\Models\GarmentType;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
    }

    public function test_employee_can_view_catalog_but_cannot_manage_it(): void
    {
        $employee = $this->staff('catalog.employee', 'employee');

        $this->actingAs($employee)->get(route('catalog.index'))->assertOk()->assertSee('المدارس والأصناف');
        $this->post(route('catalog.schools.store'), ['name' => 'مدرسة غير مصرح بها'])->assertForbidden();
        $this->assertDatabaseMissing('schools', ['name' => 'مدرسة غير مصرح بها']);
    }

    public function test_manager_can_add_school_grade_garment_and_variant_with_audit(): void
    {
        $manager = $this->staff('catalog.manager', 'manager');
        $this->actingAs($manager);

        $this->post(route('catalog.schools.store'), ['name' => 'مدرسة اختبار', 'phone' => '+249900000001'])
            ->assertSessionHasNoErrors();
        $school = School::query()->where('name', 'مدرسة اختبار')->firstOrFail();
        $this->post(route('catalog.grades.store'), ['school_id' => $school->id, 'name' => 'الصف الأول'])
            ->assertSessionHasNoErrors();
        $grade = $school->grades()->firstOrFail();
        $type = GarmentType::query()->where('name', 'قمصان')->firstOrFail();

        $this->post(route('catalog.variants.store'), [
            'school_id' => $school->id,
            'grade_id' => $grade->id,
            'garment_type_id' => $type->id,
            'size' => '12',
            'color' => 'أبيض',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('inventory_variants', ['school_id' => $school->id, 'grade_id' => $grade->id, 'size' => '12', 'color' => 'أبيض']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'school_created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'grade_created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'inventory_variant_created']);
    }

    public function test_variant_rejects_grade_from_another_school_and_duplicate_combinations(): void
    {
        $manager = $this->staff('catalog.validation.manager', 'manager');
        $firstSchool = School::query()->create(['name' => 'مدرسة أ']);
        $secondSchool = School::query()->create(['name' => 'مدرسة ب']);
        $grade = $firstSchool->grades()->create(['name' => 'أولى']);
        $type = GarmentType::query()->where('name', 'قمصان')->firstOrFail();

        $this->actingAs($manager)->post(route('catalog.variants.store'), [
            'school_id' => $secondSchool->id, 'grade_id' => $grade->id, 'garment_type_id' => $type->id, 'size' => '10',
        ])->assertSessionHasErrors('grade_id');

        $base = ['school_id' => $firstSchool->id, 'grade_id' => $grade->id, 'garment_type_id' => $type->id, 'size' => '10'];
        $this->post(route('catalog.variants.store'), $base)->assertSessionHasNoErrors();
        $this->post(route('catalog.variants.store'), $base)->assertSessionHasErrors('size');
        $this->assertDatabaseCount('inventory_variants', 1);
    }

    private function staff(string $username, string $roleName): User
    {
        $user = User::factory()->create(['username' => $username]);
        $user->roles()->attach(Role::query()->where('name', $roleName)->value('id'));

        return $user;
    }
}
