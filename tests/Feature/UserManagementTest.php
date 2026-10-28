<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
    }

    public function test_manager_can_create_user_with_hashed_password_and_change_role(): void
    {
        $manager = $this->staff('users.manager', 'manager');
        $this->actingAs($manager)->post(route('users.store'), [
            'name' => 'موظف اختباري', 'username' => 'new.employee', 'email' => 'employee@example.test',
            'role' => 'employee', 'password' => 'Synthetic-User-Password-2026', 'password_confirmation' => 'Synthetic-User-Password-2026',
        ])->assertRedirect();

        $created = User::query()->where('username', 'new.employee')->firstOrFail();
        $this->assertTrue(Hash::check('Synthetic-User-Password-2026', $created->password));
        $this->assertTrue($created->hasRole('employee'));
        $this->patch(route('users.update', $created), [
            'name' => 'مدير تجريبي', 'username' => 'new.employee', 'email' => 'employee@example.test',
            'role' => 'manager', 'is_active' => 1,
        ])->assertRedirect();
        $this->assertTrue($created->fresh()->hasRole('manager'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff_user_created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff_user_updated']);
    }

    public function test_employee_cannot_manage_users_or_change_privileges_by_http(): void
    {
        $employee = $this->staff('users.employee', 'employee');
        $target = $this->staff('users.target', 'employee');

        $this->actingAs($employee)->get(route('users.index'))->assertForbidden();
        $this->patch(route('users.update', $target), [
            'name' => $target->name, 'username' => $target->username, 'email' => $target->email,
            'role' => 'manager', 'is_active' => 1,
        ])->assertForbidden();
        $this->assertFalse($target->fresh()->hasRole('manager'));
    }

    public function test_last_manager_cannot_disable_or_demote_their_own_account(): void
    {
        $manager = $this->staff('users.last.manager', 'manager');

        $this->actingAs($manager)->patch(route('users.update', $manager), [
            'name' => $manager->name, 'username' => $manager->username, 'email' => $manager->email,
            'role' => 'employee', 'is_active' => 1,
        ])->assertSessionHasErrors('user');
        $this->assertTrue($manager->fresh()->hasRole('manager'));
    }

    private function staff(string $username, string $roleName): User
    {
        $user = User::factory()->create(['username' => $username]);
        $user->roles()->attach(Role::query()->where('name', $roleName)->value('id'));

        return $user;
    }
}
