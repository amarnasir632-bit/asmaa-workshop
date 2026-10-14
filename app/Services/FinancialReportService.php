<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Support\LocalDateTime;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

final class FinancialReportService
{
    public function forPeriod(string $from, string $to, array $filters = []): array
    {
        $fromTimestamp = LocalDateTime::toUtc($from.' 00:00:00')->toDateTimeString();
        $toTimestamp = LocalDateTime::toUtc($to.' 23:59:59')->toDateTimeString();
        $orders = $this->filteredOrders($filters)->with('schoolContract.school')->whereDate('created_on', '>=', $from)->whereDate('created_on', '<=', $to)->get();
        $salesMinor = $orders->sum(fn (Order $order): int => Money::toMinorUnits((string) $order->approved_total));
        $salesBySchool = $orders->groupBy(fn (Order $order): string => $order->schoolContract?->school?->name ?? 'طلبات فردية أو بلا مدرسة')
            ->map(fn ($schoolOrders): array => [
                'orders' => $schoolOrders->count(),
                'sales' => $schoolOrders->sum(fn (Order $order): int => Money::toMinorUnits((string) $order->approved_total)),
            ])->sortByDesc('sales');

        $cash = Payment::query()->whereHas('order', fn (Builder $query) => $this->applyOrderFilters($query, $filters))->where('method', 'cash')->whereBetween('paid_at', [$fromTimestamp, $toTimestamp])->sum('amount');
        $bank = Payment::query()->whereHas('order', fn (Builder $query) => $this->applyOrderFilters($query, $filters))->where('method', 'bank_transfer')->where('verification_status', 'verified')
            ->whereBetween('verified_at', [$fromTimestamp, $toTimestamp])->sum('amount');
        $refunds = Refund::query()->whereHas('order', fn (Builder $query) => $this->applyOrderFilters($query, $filters))->whereNotNull('refunded_at')->whereBetween('refunded_at', [$fromTimestamp, $toTimestamp])
            ->get()->sum(fn (Refund $refund): int => Money::toMinorUnits((string) $refund->refunded_amount));
        $expenses = Expense::query()->whereDate('spent_on', '>=', $from)->whereDate('spent_on', '<=', $to)->get()
            ->sum(fn (Expense $expense): int => Money::toMinorUnits((string) $expense->amount));

        return [
            'sales' => $salesMinor,
            'sales_by_school' => $salesBySchool,
            'orders_count' => $orders->count(),
            'cash_collections' => Money::toMinorUnits((string) $cash),
            'verified_bank_collections' => Money::toMinorUnits((string) $bank),
            'collections' => Money::toMinorUnits((string) $cash) + Money::toMinorUnits((string) $bank),
            'refunds' => $refunds,
            'expenses' => $expenses,
            'estimated_profit' => $salesMinor - $refunds - $expenses,
            'debts' => $this->currentDebts($filters),
        ];
    }

    private function currentDebts(array $filters): int
    {
        $summary = app(OrderFinancialSummary::class);

        return $this->filteredOrders($filters)->where('operational_status', '!=', 'cancelled')->get()
            ->sum(fn (Order $order): int => $summary->for($order)['due']);
    }

    private function filteredOrders(array $filters): Builder
    {
        return $this->applyOrderFilters(Order::query()->whereNull('deleted_at'), $filters);
    }

    private function applyOrderFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['school_id'] ?? null, fn (Builder $builder, $schoolId) => $builder->whereHas('schoolContract', fn (Builder $contract) => $contract->where('school_id', $schoolId)))
            ->when($filters['guardian_id'] ?? null, fn (Builder $builder, $guardianId) => $builder->where('guardian_id', $guardianId))
            ->when($filters['status'] ?? null, fn (Builder $builder, $status) => $builder->where('operational_status', $status));
    }
}
