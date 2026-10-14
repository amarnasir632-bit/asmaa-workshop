<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
    }

    public function test_login_page_is_arabic_and_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('مشغل أسماء')->assertSee('تسجيل الدخول', false);
    }

    public function test_active_staff_can_log_in_by_username_and_logout(): void
    {
        $staff = $this->makeStaff('noura.staff');

        $this->post(route('login.store'), [
            'login' => $staff->username,
            'password' => 'A-Long-Test-Password-Only',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($staff);
        $this->get(route('dashboard'))->assertOk()->assertSee('ابدأ بإضافة بيانات المشغل')->assertDontSee('تحويلات بانتظار التحقق');
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_inactive_staff_cannot_log_in(): void
    {
        $staff = $this->makeStaff('inactive.staff');
        $staff->forceFill(['is_active' => false])->save();

        $this->post(route('login.store'), [
            'login' => $staff->username,
            'password' => 'A-Long-Test-Password-Only',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_deactivated_staff_are_logged_out_on_the_next_request(): void
    {
        $staff = $this->makeStaff('deactivated.after.login');
        $staff->forceFill(['is_active' => false])->save();

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_manager_dashboard_can_show_finance_counts(): void
    {
        $manager = $this->makeStaff('dashboard.manager', 'manager');

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('تحويلات بانتظار التحقق');
    }

    public function test_manager_has_sensitive_permissions_and_employee_does_not(): void
    {
        $manager = $this->makeStaff('manager.test', 'manager');
        $employee = $this->makeStaff('employee.test', 'employee');

        $this->assertTrue(Gate::forUser($manager)->allows('payments.verify'));
        $this->assertTrue(Gate::forUser($manager)->allows('refunds.approve'));
        $this->assertTrue(Gate::forUser($employee)->allows('payments.record'));
        $this->assertFalse(Gate::forUser($employee)->allows('payments.verify'));
        $this->assertFalse(Gate::forUser($employee)->allows('refunds.approve'));
        $this->assertFalse(Gate::forUser($employee)->allows('inventory.manage'));
    }

    public function test_first_manager_is_created_interactively_without_a_default_password(): void
    {
        $this->artisan('app:create-manager')
            ->expectsQuestion('الاسم الظاهر', 'مدير اختباري')
            ->expectsQuestion('اسم المستخدم (أحرف إنجليزية وأرقام ونقطة أو شرطة)', 'manager.first')
            ->expectsQuestion('البريد الإلكتروني (اختياري)', '')
            ->expectsQuestion('كلمة مرور طويلة (12 حرفاً على الأقل)', 'Synthetic-Manager-Password-2026')
            ->expectsQuestion('أعد كتابة كلمة المرور', 'Synthetic-Manager-Password-2026')
            ->assertExitCode(0);

        $manager = User::query()->where('username', 'manager.first')->firstOrFail();
        $this->assertTrue(Hash::check('Synthetic-Manager-Password-2026', $manager->password));
        $this->assertTrue($manager->hasRole('manager'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'bootstrap_manager_created', 'subject_id' => $manager->id]);
    }

    public function test_staff_can_create_guardian_and_child_and_audit_their_creation(): void
    {
        $staff = $this->makeStaff('customer.staff');
        $this->actingAs($staff);

        $this->post(route('guardians.store'), [
            'name' => 'ولي أمر تجريبي',
            'phone' => '+249900000001',
        ])->assertRedirect();

        $guardian = Guardian::query()->firstOrFail();
        $this->post(route('children.store', $guardian), ['name' => 'طفل تجريبي'])
            ->assertRedirect(route('guardians.show', $guardian));

        $this->assertDatabaseHas('children', ['guardian_id' => $guardian->id, 'name' => 'طفل تجريبي']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'guardian_created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'child_created']);
        $this->get(route('guardians.show', $guardian))->assertOk()->assertSee('طفل تجريبي');
    }

    public function test_duplicate_names_are_allowed_and_measurements_use_the_srs_order(): void
    {
        $staff = $this->makeStaff('measurements.staff');
        $first = Guardian::query()->create(['name' => 'اسم مكرر', 'phone' => '+249900000002']);
        Guardian::query()->create(['name' => 'اسم مكرر', 'phone' => '+249900000003']);
        $child = $first->children()->create(['name' => 'اسم مكرر']);

        $this->actingAs($staff)->patch(route('children.measurements.update', $child), [
            'shoulder_cm' => '31.5',
            'chest_cm' => '68',
            'sleeve_cm' => '47.25',
            'waist_circumference_cm' => '62',
            'length_cm' => '92',
        ])->assertRedirect(route('guardians.show', $first));

        $this->assertDatabaseHas('current_measurements', [
            'child_id' => $child->id,
            'shoulder_cm' => 31.5,
            'chest_cm' => 68,
            'sleeve_cm' => 47.25,
            'waist_circumference_cm' => 62,
            'length_cm' => 92,
        ]);
        $this->assertSame(2, Guardian::query()->where('name', 'اسم مكرر')->count());
    }

    public function test_unassigned_user_cannot_call_customer_write_route_directly(): void
    {
        $unassigned = User::factory()->create();

        $this->actingAs($unassigned)->post(route('guardians.store'), [
            'name' => 'محاولة غير مصرح بها',
            'phone' => '+249900000004',
        ])->assertForbidden();

        $this->assertDatabaseMissing('guardians', ['name' => 'محاولة غير مصرح بها']);
    }

    private function makeStaff(string $username, string $role = 'employee'): User
    {
        $user = User::factory()->create([
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => Hash::make('A-Long-Test-Password-Only'),
        ]);
        $user->roles()->attach(Role::query()->where('name', $role)->value('id'));

        return $user;
    }
}
