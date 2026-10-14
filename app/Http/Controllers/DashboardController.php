<?php

namespace App\Http\Controllers;

use App\Services\FinancialReportService;
use App\Services\InternalNotificationService;
use App\Services\OrderFinancialSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(InternalNotificationService $notifications, OrderFinancialSummary $summary, FinancialReportService $financialReports): View
    {
        $canViewOrders = Gate::allows('orders.view');
        $canViewCustomers = Gate::allows('customers.view');
        $canViewInventory = Gate::allows('inventory.view');
        $canViewFinance = Gate::allows('finance.view');
        $userNotifications = $canViewOrders ? $notifications->refreshFor(auth()->user(), $summary) : collect();
        $financial = $canViewFinance
            ? $financialReports->forPeriod(now(config('app.display_timezone', 'Africa/Khartoum'))->startOfMonth()->toDateString(), now(config('app.display_timezone', 'Africa/Khartoum'))->toDateString())
            : null;

        return view('dashboard', [
            'ordersCount' => $canViewOrders ? DB::table('orders')->whereNull('deleted_at')->count() : null,
            'guardiansCount' => $canViewCustomers ? DB::table('guardians')->whereNull('deleted_at')->count() : null,
            'readyStockUnits' => $canViewInventory ? (int) DB::table('inventory_balances')->sum('quantity_available') : null,
            'pendingTransfersCount' => $canViewFinance ? DB::table('payments')->where('verification_status', 'pending')->count() : null,
            'financial' => $financial,
            'notifications' => $userNotifications,
        ]);
    }
}
