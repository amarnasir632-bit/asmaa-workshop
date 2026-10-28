<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvidence;
use App\Models\Refund;
use App\Models\RefundTransaction;
use App\Services\AuditLogger;
use App\Services\OrderFinancialSummary;
use App\Support\LocalDateTime;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request, OrderFinancialSummary $summary): View
    {
        Gate::authorize('payments.record');
        $orders = Order::query()->with('guardian')->latest('created_on')->paginate(30);
        $orders->getCollection()->each(fn (Order $order) => $order->setAttribute('financial_summary', $summary->for($order)));
        $payments = Payment::query()->with(['order.guardian', 'recorder', 'evidence'])->when(
            ! $request->user()->can('finance.view'),
            fn ($query) => $query->where('recorded_by', $request->user()->id),
        )->latest('paid_at')->paginate(30, ['*'], 'payments_page');

        return view('payments.index', compact('orders', 'payments'));
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('payments.record');
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/'],
            'method' => ['required', Rule::in(['cash', 'bank_transfer'])],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'transaction_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'evidence' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $order = Order::query()->findOrFail($data['order_id']);
        $reference = isset($data['transaction_reference']) && trim($data['transaction_reference']) !== '' ? trim($data['transaction_reference']) : null;
        if ($reference && Payment::query()->where('order_id', $order->id)->where('transaction_reference', $reference)->exists()) {
            return back()->withErrors(['transaction_reference' => 'رقم العملية مسجل مسبقاً لهذا الطلب.'])->withInput();
        }

        $amountMinor = Money::toMinorUnits($data['amount']);
        $path = null;
        try {
            DB::transaction(function () use ($request, $order, $data, $reference, $amountMinor, $audit, &$path): void {
                $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                $sum = app(OrderFinancialSummary::class)->for($lockedOrder);
                $recorded = Money::toMinorUnits((string) $lockedOrder->payments()->sum('amount'));
                if ($recorded + $amountMinor > Money::toMinorUnits((string) $lockedOrder->approved_total)) {
                    throw ValidationException::withMessages(['amount' => 'مجموع الدفعات المسجلة يتجاوز قيمة الطلب المعتمدة.']);
                }
                $payment = $lockedOrder->payments()->create([
                    'amount' => Money::fromMinorUnits($amountMinor), 'paid_at' => LocalDateTime::toUtc($data['paid_at']),
                    'method' => $data['method'], 'verification_status' => $data['method'] === 'cash' ? 'not_required' : 'pending',
                    'transaction_reference' => $reference, 'recorded_by' => $request->user()->id, 'notes' => $data['notes'] ?? null,
                ]);
                if ($file = $request->file('evidence')) {
                    $sha = hash_file('sha256', $file->getRealPath());
                    $duplicate = PaymentEvidence::query()->where('sha256', $sha)->whereHas('payment', fn ($q) => $q->where('order_id', $lockedOrder->id))->exists();
                    if ($duplicate) {
                        $payment->delete();
                        throw ValidationException::withMessages(['evidence' => 'هذا الملف مرفق مسبقاً كدليل لدفعة في الطلب نفسه.']);
                    }
                    $path = $file->store('payment-evidence/'.$lockedOrder->id, 'private');
                    $payment->evidence()->create([
                        'private_path' => $path, 'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                        'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(), 'sha256' => $sha,
                        'uploaded_by' => $request->user()->id,
                    ]);
                }
                $audit->record('payment_recorded', $payment, $payment->id, ['order_id', 'amount', 'method', 'verification_status', 'transaction_reference']);
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('private')->delete($path);
            }
            throw $exception;
        }

        return redirect()->route('payments.index')->with('status', 'تم تسجيل الدفعة. التحويل البنكي يظل بانتظار اعتماد المدير.');
    }

    public function verify(Request $request, Payment $payment, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('payments.verify');
        abort_unless($payment->method === 'bank_transfer', 422);
        abort_unless($payment->verification_status === 'pending', 409, 'الدفعة ليست بانتظار التحقق.');
        DB::transaction(function () use ($request, $payment, $audit): void {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->verification_status !== 'pending') {
                abort(409);
            }
            $locked->update(['verification_status' => 'verified', 'verified_by' => $request->user()->id, 'verified_at' => now()]);
            $audit->record('bank_transfer_verified', $locked, $locked->id, ['verification_status', 'verified_by', 'verified_at']);
        });

        return back()->with('status', 'تم اعتماد التحويل وإضافته إلى التحصيل المؤكد.');
    }

    public function reverse(Request $request, Payment $payment, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('payments.record');
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $payment, $data, $audit): void {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($lockedPayment->order_id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->method === 'bank_transfer' && $lockedPayment->verification_status === 'pending') {
                $lockedPayment->delete();
                $audit->record('payment_reversed', $lockedPayment, $lockedPayment->id, ['verification_status'], 'إلغاء إشعار تحويل بنكي معلق: ' . trim($data['reason']));
            } else {
                $refund = Refund::query()->create([
                    'order_id' => $order->id,
                    'original_payment_id' => $lockedPayment->id,
                    'approved_amount' => $lockedPayment->amount,
                    'refunded_amount' => $lockedPayment->amount,
                    'method' => $lockedPayment->method,
                    'refunded_at' => now(),
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                    'reason' => 'قيد تسوية عكسي لإلغاء الدفعة رقم ' . $lockedPayment->id . ': ' . trim($data['reason']),
                ]);

                RefundTransaction::query()->create([
                    'refund_id' => $refund->id,
                    'amount' => $lockedPayment->amount,
                    'method' => $lockedPayment->method,
                    'refunded_at' => now(),
                    'recorded_by' => $request->user()->id,
                ]);

                $audit->record('payment_reversed', $lockedPayment, $lockedPayment->id, ['amount'], trim($data['reason']));
            }
            $order->touch();
        });

        return back()->with('status', 'تم إنشاء قيد التسوية العكسي بنجاح وتصحيح الحساب دون حذف السجل.');
    }

    public function evidence(Request $request, PaymentEvidence $evidence)
    {
        Gate::authorize('finance.view');

        return Storage::disk('private')->download($evidence->private_path, $evidence->original_name, ['Content-Type' => $evidence->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }
}
