<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseRecordingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
    }

    public function test_employee_can_record_and_only_view_their_own_expenses(): void
    {
        $employee = $this->staff('expense.employee', 'employee');
        $other = $this->staff('expense.other', 'employee');
        $category = ExpenseCategory::query()->firstOrFail();
        Expense::query()->create([
            'expense_category_id' => $category->id, 'amount' => '30.00', 'spent_on' => now()->toDateString(),
            'description' => 'مصروف مستخدم آخر', 'recorded_by' => $other->id,
        ]);

        $this->actingAs($employee)->post(route('expenses.store'), [
            'expense_category_id' => $category->id, 'amount' => '20.25', 'spent_on' => now()->toDateString(),
            'description' => 'نقل تجريبي',
        ])->assertRedirect(route('expenses.index'));

        $this->assertDatabaseHas('expenses', ['recorded_by' => $employee->id, 'amount' => '20.25', 'description' => 'نقل تجريبي']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'expense_recorded']);
        $this->get(route('expenses.index'))->assertOk()->assertSee('نقل تجريبي')->assertDontSee('مصروف مستخدم آخر');
        $this->patch(route('expenses.store'))->assertStatus(405);
    }

    public function test_employee_cannot_write_invalid_or_zero_expense(): void
    {
        $employee = $this->staff('expense.invalid.employee', 'employee');
        $this->actingAs($employee)->post(route('expenses.store'), [
            'expense_category_id' => ExpenseCategory::query()->firstOrFail()->id,
            'amount' => '0', 'spent_on' => now()->toDateString(), 'description' => 'قيمة صفرية',
        ])->assertSessionHasErrors('amount');
        $this->assertDatabaseMissing('expenses', ['description' => 'قيمة صفرية']);
    }

    private function staff(string $username, string $roleName): User
    {
        $user = User::factory()->create(['username' => $username]);
        $user->roles()->attach(Role::query()->where('name', $roleName)->value('id'));

        return $user;
    }
}
