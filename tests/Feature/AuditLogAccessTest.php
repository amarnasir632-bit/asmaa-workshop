<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLogAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
    }

    public function test_only_manager_can_read_audit_log_and_no_write_route_is_exposed(): void
    {
        $employee = $this->staff('audit.employee', 'employee');
        $manager = $this->staff('audit.manager', 'manager');
        DB::table('audit_logs')->insert([
            'user_id' => $employee->id, 'action' => 'synthetic_audit_event', 'subject_type' => User::class,
            'subject_id' => $employee->id, 'after_values' => json_encode(['changed_fields' => ['is_active']]),
            'occurred_at' => now('UTC'),
        ]);

        $this->actingAs($employee)->get(route('audit.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('audit.index'))->assertOk()->assertSee('synthetic_audit_event');
        $this->patch(route('audit.index'))->assertStatus(405);
        $this->delete(route('audit.index'))->assertStatus(405);
    }

    private function staff(string $username, string $roleName): User
    {
        $user = User::factory()->create(['username' => $username]);
        $user->roles()->attach(Role::query()->where('name', $roleName)->value('id'));

        return $user;
    }
}
