<?php

namespace App\Http\Controllers;

use App\Models\Guardian;
use App\Models\InventoryMovement;
use App\Models\InventoryVariant;
use App\Models\Order;
use App\Models\ProductionJob;
use App\Models\School;
use App\Services\FinancialReportService;
use App\Services\ReportPdfService;
use App\Support\LocalDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function index(Request $request, FinancialReportService $financialReports): View
    {
        return view('reports.index', $this->reportData($request, $financialReports));
    }

    public function exportPdf(Request $request, FinancialReportService $financialReports, ReportPdfService $pdf): Response
    {
        $data = $this->reportData($request, $financialReports, true);
        $bytes = $pdf->render(view('reports.pdf', $data)->render());

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="asmaa-report-'.$data['from'].'-'.$data['to'].'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function reportData(Request $request, FinancialReportService $financialReports, bool $forPdf = false): array
    {
        abort_unless(Gate::allows('reports.operational') || Gate::allows('reports.financial'), 403);
        $defaultFrom = now(config('app.display_timezone', 'Africa/Khartoum'))->startOfMonth()->toDateString();
        $defaultTo = now(config('app.display_timezone', 'Africa/Khartoum'))->toDateString();
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'guardian_id' => ['nullable', 'integer', 'exists:guardians,id'],
            'status' => ['nullable', 'in:new,in_progress,partially_received,fully_received,ready,delivered,cancelled'],
        ]);
        $from = $data['from'] ?? $defaultFrom;
        $to = $data['to'] ?? $defaultTo;
        if ($from > $to) {
            throw ValidationException::withMessages(['from' => 'بداية الفترة يجب أن تسبق نهايتها.']);
        }

        $canSeeFinancial = Gate::allows('reports.financial');
        $filters = array_filter(['school_id' => $data['school_id'] ?? null, 'guardian_id' => $data['guardian_id'] ?? null, 'status' => $data['status'] ?? null], fn ($value) => $value !== null && $value !== '');
        $financial = $canSeeFinancial ? $financialReports->forPeriod($from, $to, $filters) : null;
        $ordersQuery = Order::query()->with('guardian')->whereDate('created_on', '>=', $from)->whereDate('created_on', '<=', $to)
            ->when($filters['school_id'] ?? null, fn ($query, $schoolId) => $query->whereHas('schoolContract', fn ($contract) => $contract->where('school_id', $schoolId)))
            ->when($filters['guardian_id'] ?? null, fn ($query, $guardianId) => $query->where('guardian_id', $guardianId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('operational_status', $status));
        $ordersQuery->latest('created_on');
        $orders = $forPdf ? $ordersQuery->limit(500)->get() : $ordersQuery->paginate(20)->withQueryString();
        $ordersForStates = (clone $ordersQuery)->reorder();
        $orderStates = $ordersForStates->selectRaw('operational_status, COUNT(*) as total')->groupBy('operational_status')->pluck('total', 'operational_status');
        $productionJobs = ProductionJob::query()->with(['order', 'orderItem', 'dispatches.items.receipts'])
            ->whereHas('order', fn ($query) => $query->whereDate('created_on', '>=', $from)->whereDate('created_on', '<=', $to)->when($filters['school_id'] ?? null, fn ($q, $schoolId) => $q->whereHas('schoolContract', fn ($contract) => $contract->where('school_id', $schoolId)))->when($filters['guardian_id'] ?? null, fn ($q, $guardianId) => $q->where('guardian_id', $guardianId))->when($filters['status'] ?? null, fn ($q, $status) => $q->where('operational_status', $status)))->get();
        $inventory = InventoryVariant::query()->with(['school', 'garmentType', 'grade', 'balance'])->when($filters['school_id'] ?? null, fn ($query, $schoolId) => $query->where('school_id', $schoolId))->orderBy('school_id')->orderBy('garment_type_id')->get();
        $movements = InventoryMovement::query()->with('variant.school', 'variant.garmentType')->whereHas('variant', fn ($query) => $query->when($filters['school_id'] ?? null, fn ($q, $schoolId) => $q->where('school_id', $schoolId)))->whereBetween('occurred_at', [LocalDateTime::toUtc($from.' 00:00:00'), LocalDateTime::toUtc($to.' 23:59:59')])->latest('occurred_at')->limit(100)->get();
        $schools = School::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $guardians = Guardian::query()->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);

        return compact('from', 'to', 'filters', 'schools', 'guardians', 'financial', 'orders', 'orderStates', 'productionJobs', 'inventory', 'movements');
    }
}
