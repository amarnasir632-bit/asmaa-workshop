<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\ReportPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TailorReportController extends Controller
{
    public function index(Request $request): View
    {
        $data = $this->reportData($request);

        return view('reports.tailor', $data);
    }

    public function exportPdf(Request $request, ReportPdfService $pdf): Response
    {
        $data = $this->reportData($request);
        $bytes = $pdf->render(view('reports.tailor-pdf', $data)->render(), 'L');

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="tailor-report-'.$data['from'].'-'.$data['to'].'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function form(Request $request): View
    {
        Gate::authorize('reports.operational');

        return view('reports.tailor-form', ['rows' => $this->formRows($request)]);
    }

    public function formPdf(Request $request, ReportPdfService $pdf): Response
    {
        Gate::authorize('reports.operational');
        $bytes = $pdf->render(view('reports.tailor-form-pdf', ['rows' => $this->formRows($request)])->render(), 'L');

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="tailor-form.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function formRows(Request $request): int
    {
        return max(1, min(120, (int) $request->query('rows', 20)));
    }

    /** @return array{from:string,to:string,rows:array<int,array<string,mixed>>,ordersCount:int,itemsCount:int,quantityTotal:int} */
    private function reportData(Request $request): array
    {
        abort_unless(Gate::allows('reports.operational'), 403);
        $timezone = config('app.display_timezone', 'Africa/Khartoum');
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $from = $data['from'] ?? now($timezone)->startOfMonth()->toDateString();
        $to = $data['to'] ?? now($timezone)->toDateString();
        if ($from > $to) {
            throw ValidationException::withMessages(['from' => 'بداية الفترة يجب أن تسبق نهايتها.']);
        }

        $orders = Order::query()
            ->with(['items.beneficiary', 'items.child', 'items.measurementSnapshot'])
            ->whereDate('created_on', '>=', $from)
            ->whereDate('created_on', '<=', $to)
            ->where('operational_status', '!=', 'cancelled')
            ->whereHas('payments')
            ->orderBy('created_on')
            ->orderBy('id')
            ->get();

        $rows = [];
        $serial = 0;
        $quantityTotal = 0;
        foreach ($orders as $order) {
            $serial++;
            foreach ($order->items as $item) {
                $measurements = $item->measurementSnapshot;
                $quantity = (int) $item->quantity;
                $quantityTotal += $quantity;
                $rows[] = [
                    'serial' => $serial,
                    'number' => $order->number,
                    'customer' => $order->customer_name_snapshot,
                    'beneficiary' => $item->beneficiary?->name_snapshot ?? $item->child?->name,
                    'description' => $item->description_snapshot,
                    'quantity' => $quantity,
                    'shoulder' => $measurements?->shoulder_cm,
                    'chest' => $measurements?->chest_cm,
                    'sleeve' => $measurements?->sleeve_cm,
                    'waist' => $measurements?->waist_circumference_cm,
                    'length' => $measurements?->length_cm,
                    'due_on' => $order->due_on?->format('Y-m-d'),
                    'notes' => $order->notes,
                ];
            }
        }

        return [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'ordersCount' => $orders->count(),
            'itemsCount' => count($rows),
            'quantityTotal' => $quantityTotal,
        ];
    }
}
