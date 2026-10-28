<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CancellationController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ChildController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GuardianController;
use App\Http\Controllers\HistoricalImportController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\PublicOrderLookupController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\TailorReportController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));
Route::get('/lookup', [PublicOrderLookupController::class, 'index'])->name('public.lookup');
Route::post('/lookup', [PublicOrderLookupController::class, 'search'])->middleware('throttle:5,1')->name('public.lookup.search');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');
});

Route::middleware(['auth', 'active.staff'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('/reports/pdf', [ReportsController::class, 'exportPdf'])->name('reports.pdf');
    Route::get('/reports/tailor', [TailorReportController::class, 'index'])->name('reports.tailor');
    Route::get('/reports/tailor/pdf', [TailorReportController::class, 'exportPdf'])->name('reports.tailor.pdf');
    Route::get('/reports/tailor/form', [TailorReportController::class, 'form'])->name('reports.tailor.form');
    Route::get('/reports/tailor/form/pdf', [TailorReportController::class, 'formPdf'])->name('reports.tailor.form.pdf');

    Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
    Route::post('/catalog/schools', [CatalogController::class, 'storeSchool'])->name('catalog.schools.store');
    Route::post('/catalog/grades', [CatalogController::class, 'storeGrade'])->name('catalog.grades.store');
    Route::post('/catalog/garment-types', [CatalogController::class, 'storeGarmentType'])->name('catalog.garment-types.store');
    Route::post('/catalog/variants', [CatalogController::class, 'storeVariant'])->name('catalog.variants.store');
    Route::post('/catalog/schools/{school}/deactivate', [CatalogController::class, 'deactivateSchool'])->name('catalog.schools.deactivate');
    Route::post('/catalog/grades/{grade}/deactivate', [CatalogController::class, 'deactivateGrade'])->name('catalog.grades.deactivate');
    Route::post('/catalog/garment-types/{garmentType}/deactivate', [CatalogController::class, 'deactivateGarmentType'])->name('catalog.garment-types.deactivate');
    Route::post('/catalog/variants/{variant}/deactivate', [CatalogController::class, 'deactivateVariant'])->name('catalog.variants.deactivate');

    Route::get('/guardians', [GuardianController::class, 'index'])->name('guardians.index');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');
    Route::get('/school-contracts/create', [OrderController::class, 'createSchoolContract'])->name('school-contracts.create');
    Route::post('/school-contracts', [OrderController::class, 'storeSchoolContract'])->name('school-contracts.store');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/statement', [OrderController::class, 'statement'])->name('orders.statement');
    Route::get('/orders/{order}/statement/pdf', [OrderController::class, 'statementPdf'])->name('orders.statement.pdf');
    Route::post('/orders/{order}/deliveries', [DeliveryController::class, 'store'])->name('deliveries.store');
    Route::post('/orders/{order}/cancel', [CancellationController::class, 'store'])->name('orders.cancel');
    Route::patch('/orders/{order}/cancellation', [CancellationController::class, 'correct'])->name('orders.cancellation.correct');
    Route::post('/orders/{order}/refunds', [RefundController::class, 'approve'])->name('refunds.approve');
    Route::post('/refunds/{refund}/pay', [RefundController::class, 'pay'])->name('refunds.pay');
    Route::patch('/refunds/{refund}', [RefundController::class, 'correct'])->name('refunds.correct');
    Route::get('/production', [ProductionController::class, 'index'])->name('production.index');
    Route::post('/production/jobs', [ProductionController::class, 'storeJob'])->name('production.jobs.store');
    Route::patch('/production/jobs/{job}/stage', [ProductionController::class, 'updateStage'])->name('production.jobs.stage.update');
    Route::post('/production/jobs/{job}/dispatches', [ProductionController::class, 'storeDispatch'])->name('production.dispatches.store');
    Route::post('/production/dispatches/{dispatch}/receipts', [ProductionController::class, 'storeReceipt'])->name('production.receipts.store');
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory/opening-batches', [InventoryController::class, 'storeOpeningBatch'])->name('inventory.opening-batches.store');
    Route::post('/inventory/opening-batches/{batch}/approve', [InventoryController::class, 'approveOpeningBatch'])->name('inventory.opening-batches.approve');
    Route::post('/inventory/adjustments', [InventoryController::class, 'storeAdjustment'])->name('inventory.adjustments.store');
    Route::post('/inventory/adjustments/{adjustment}/approve', [InventoryController::class, 'approveAdjustment'])->name('inventory.adjustments.approve');
    Route::post('/inventory/adjustments/{adjustment}/reject', [InventoryController::class, 'rejectAdjustment'])->name('inventory.adjustments.reject');
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::post('/expenses/{expense}/reverse', [ExpenseController::class, 'reverse'])->name('expenses.reverse');
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::post('/payments/{payment}/verify', [PaymentController::class, 'verify'])->name('payments.verify');
    Route::post('/payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('payments.reverse');
    Route::get('/payment-evidence/{evidence}', [PaymentController::class, 'evidence'])->name('payments.evidence');
    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit.index');
    Route::get('/history-imports', [HistoricalImportController::class, 'index'])->name('history.index');
    Route::post('/history-imports', [HistoricalImportController::class, 'storeBatch'])->name('history.batches.store');
    Route::get('/history-imports/{batch}', [HistoricalImportController::class, 'show'])->name('history.show');
    Route::post('/history-imports/{batch}/records', [HistoricalImportController::class, 'storeRecord'])->name('history.records.store');
    Route::patch('/history-records/{record}/review', [HistoricalImportController::class, 'review'])->name('history.records.review');
    Route::patch('/history-records/{record}/correction', [HistoricalImportController::class, 'correct'])->name('history.records.correct');
    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::patch('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::get('/guardians/create', [GuardianController::class, 'create'])->name('guardians.create');
    Route::post('/guardians', [GuardianController::class, 'store'])->name('guardians.store');
    Route::get('/guardians/{guardian}', [GuardianController::class, 'show'])->name('guardians.show');
    Route::get('/guardians/{guardian}/edit', [GuardianController::class, 'edit'])->name('guardians.edit');
    Route::patch('/guardians/{guardian}', [GuardianController::class, 'update'])->name('guardians.update');
    Route::post('/guardians/{guardian}/archive', [GuardianController::class, 'archive'])->name('guardians.archive');
    Route::post('/guardians/{guardian}/children', [ChildController::class, 'store'])->name('children.store');
    Route::patch('/children/{child}/measurements', [ChildController::class, 'updateMeasurements'])->name('children.measurements.update');
    Route::post('/children/{child}/archive', [ChildController::class, 'archive'])->name('children.archive');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
