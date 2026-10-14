<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('expenses.record');
        $query = Expense::query()->with(['category', 'recorder'])->latest('spent_on')->latest('id');
        if (! $request->user()->can('finance.view')) {
            $query->where('recorded_by', $request->user()->id);
        }
        $expenses = $query->paginate(30);
        $categories = ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get();

        return view('expenses.index', compact('expenses', 'categories'));
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('expenses.record');
        $data = $request->validate([
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/'],
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $category = ExpenseCategory::query()->whereKey($data['expense_category_id'])->where('is_active', true)->firstOrFail();
        $expense = DB::transaction(function () use ($data, $category, $request, $audit): Expense {
            $expense = Expense::query()->create([
                'expense_category_id' => $category->id,
                'amount' => Money::fromMinorUnits(Money::toMinorUnits($data['amount'])),
                'spent_on' => $data['spent_on'],
                'description' => trim($data['description']),
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);
            $audit->record('expense_recorded', $expense, $expense->id, ['expense_category_id', 'amount', 'spent_on', 'description']);

            return $expense;
        });

        return redirect()->route('expenses.index')->with('status', 'تم تسجيل المصروف رقم '.$expense->id.'؛ لا يمكن تعديله أو حذفه دون سجل تصحيح معتمد.');
    }
}
