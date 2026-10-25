<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Models\GarmentType;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\InventoryVariant;
use App\Models\Order;
use App\Models\School;
use App\Services\AuditLogger;
use App\Services\OrderFinancialSummary;
use App\Services\OrderNumberGenerator;
use App\Services\ReportPdfService;
use App\Support\LocalDateTime;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class OrderController extends Controller
{
    public function index(Request $request, OrderFinancialSummary $summary): View
    {
        Gate::authorize('orders.view');
        $search = trim((string) $request->query('q', ''));
        $filterType = (string) $request->query('type_filter', '');

        $orders = Order::query()
            ->with(['guardian', 'schoolContract.school', 'schoolContract.beneficiaries', 'items', 'payments'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('number', 'like', '%'.$search.'%')
                        ->orWhere('customer_name_snapshot', 'like', '%'.$search.'%')
                        ->orWhereHas('guardian', fn (Builder $guardian) => $guardian->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('schoolContract.school', fn (Builder $school) => $school->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($filterType === 'school_contract', function (Builder $query): void {
                $query->has('schoolContract');
            })
            ->when($filterType === 'individual', function (Builder $query): void {
                $query->doesntHave('schoolContract');
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $orders->getCollection()->each(fn (Order $order) => $order->setAttribute('financial_summary', $summary->for($order)));

        return view('orders.index', compact('orders', 'search', 'filterType'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('orders.create');
        $guardians = Guardian::query()->with(['children' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get();
        $variants = InventoryVariant::query()->with(['garmentType', 'school', 'grade', 'balance'])->where('is_active', true)->orderBy('id')->get();
        $garmentTypes = GarmentType::query()->where('is_active', true)->orderBy('name')->get();

        return view('orders.create', compact('guardians', 'variants', 'garmentTypes'));
    }

    public function createSchoolContract(): View
    {
        Gate::authorize('orders.create');
        $schools = School::query()->with('grades')->where('is_active', true)->orderBy('name')->get();
        $guardians = Guardian::query()->with('children')->orderBy('name')->get();
        $variants = InventoryVariant::query()->with(['garmentType', 'school', 'grade', 'balance'])->where('is_active', true)->orderBy('id')->get();
        $garmentTypes = GarmentType::query()->where('is_active', true)->orderBy('name')->get();

        return view('orders.create-school-contract', compact('schools', 'guardians', 'variants', 'garmentTypes'));
    }

    public function storeSchoolContract(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('orders.create');
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'contract_number' => ['nullable', 'string', 'max:80', 'unique:school_contracts,contract_number'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'terms' => ['nullable', 'string', 'max:3000'],
            'due_on' => ['nullable', 'date', 'after_or_equal:today'],
            'beneficiaries' => ['required', 'array', 'min:1', 'max:100'],
            'beneficiaries.*.name' => ['nullable', 'string', 'max:160'],
            'beneficiaries.*.child_id' => ['nullable', 'integer', 'exists:children,id'],
            'beneficiaries.*.grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'beneficiaries.*.source_reference' => ['nullable', 'string', 'max:100'],
            'beneficiaries.*.items' => ['required', 'array', 'min:1', 'max:10'],
            'beneficiaries.*.items.*.inventory_variant_id' => ['nullable', 'integer', 'exists:inventory_variants,id'],
            'beneficiaries.*.items.*.garment_type_id' => ['nullable', 'integer', 'exists:garment_types,id'],
            'beneficiaries.*.items.*.description' => ['required', 'string', 'max:180'],
            'beneficiaries.*.items.*.quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'beneficiaries.*.items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/'],
            'beneficiaries.*.items.*.discount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/'],
            'initial_payment' => ['nullable', 'array'],
            'initial_payment.amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/'],
            'initial_payment.method' => ['nullable', 'required_with:initial_payment.amount', Rule::in(['cash', 'bank_transfer'])],
            'initial_payment.transaction_reference' => ['nullable', 'string', 'max:120'],
            'initial_payment.notes' => ['nullable', 'string', 'max:2000'],
            'initial_payment.paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            'initial_payment_evidence' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $school = School::query()->whereKey($data['school_id'])->where('is_active', true)->firstOrFail();
        $flatItems = [];
        $itemCount = 0;
        foreach ($data['beneficiaries'] as $beneficiaryIndex => $beneficiary) {
            if (empty(trim($beneficiary['name'] ?? '')) && empty($beneficiary['grade_id'])) {
                throw ValidationException::withMessages(["beneficiaries.$beneficiaryIndex.name" => 'يجب إدخال اسم المستفيد أو اختيار المرحلة الدراسية.']);
            }
            if (! empty($beneficiary['grade_id']) && ! Grade::query()->whereKey($beneficiary['grade_id'])->where('school_id', $school->id)->exists()) {
                throw ValidationException::withMessages(["beneficiaries.$beneficiaryIndex.grade_id" => 'المرحلة لا تتبع المدرسة المختارة.']);
            }
            if (! empty($beneficiary['child_id'])) {
                $child = Child::query()->whereKey($beneficiary['child_id'])->first();
                if (! $child || $child->school_id !== $school->id) {
                    throw ValidationException::withMessages(["beneficiaries.$beneficiaryIndex.child_id" => 'الطفل لا يتبع المدرسة المحددة.']);
                }
            }
            foreach ($beneficiary['items'] as $itemIndex => $item) {
                if (! empty($item['inventory_variant_id'])) {
                    $variant = InventoryVariant::query()->whereKey($item['inventory_variant_id'])->where('is_active', true)->first();
                    if (! $variant || ($variant->school_id && (int) $variant->school_id !== (int) $school->id)) {
                        throw ValidationException::withMessages(["beneficiaries.$beneficiaryIndex.items.$itemIndex.inventory_variant_id" => 'الصنف غير نشط أو لا يتبع المدرسة المختارة.']);
                    }
                }
                $gross = Money::toMinorUnits($item['unit_price']) * (int) $item['quantity'];
                $discount = Money::toMinorUnits($item['discount'] ?? '0');
                if ($discount > $gross) {
                    throw ValidationException::withMessages(["beneficiaries.$beneficiaryIndex.items.$itemIndex.discount" => 'خصم البند لا يمكن أن يتجاوز قيمته.']);
                }
                $flatItems[] = ['beneficiary_index' => $beneficiaryIndex, 'item' => $item, 'gross' => $gross, 'discount' => $discount];
                $itemCount++;
            }
        }
        if ($itemCount > 500) {
            throw ValidationException::withMessages(['beneficiaries' => 'الحد الأقصى لهذه الدفعة 500 بند. قسّم قائمة المدرسة إلى أكثر من طلب/عقد.']);
        }

        $grossTotal = array_sum(array_column($flatItems, 'gross'));
        $discountTotal = array_sum(array_column($flatItems, 'discount'));
        $orderApprovedMinor = $grossTotal - $discountTotal;
        $initialPaymentAmount = ! empty($data['initial_payment']['amount']) ? Money::toMinorUnits($data['initial_payment']['amount']) : 0;

        if ($initialPaymentAmount > $orderApprovedMinor) {
            throw ValidationException::withMessages([
                'initial_payment.amount' => 'المبلغ المدفوع يتجاوز إجمالي قيمة العقد المعتمدة ('.number_format($orderApprovedMinor / 100, 2).' ج.س).',
            ]);
        }

        $number = $this->newOrderNumber();
        $evidencePath = null;

        try {
            $order = DB::transaction(function () use ($data, $flatItems, $school, $grossTotal, $discountTotal, $orderApprovedMinor, $initialPaymentAmount, $number, $request, $audit, &$evidencePath): Order {
                $order = Order::query()->create([
                    'number' => $number, 'type' => 'custom', 'guardian_id' => null, 'customer_name_snapshot' => $school->name,
                    'created_by' => $request->user()->id, 'created_on' => now(config('app.display_timezone', 'Africa/Khartoum'))->toDateString(),
                    'due_on' => $data['due_on'] ?? null, 'operational_status' => 'new', 'items_total' => Money::fromMinorUnits($grossTotal),
                    'discount_total' => Money::fromMinorUnits($discountTotal), 'approved_total' => Money::fromMinorUnits($orderApprovedMinor),
                ]);
                $contract = $order->schoolContract()->create([
                    'school_id' => $school->id, 'contract_number' => $data['contract_number'] ?? null,
                    'contact_name' => $data['contact_name'] ?? null, 'terms' => $data['terms'] ?? null,
                ]);
                $beneficiaries = [];
                foreach ($data['beneficiaries'] as $index => $beneficiaryData) {
                    $grade = ! empty($beneficiaryData['grade_id']) ? Grade::query()->find($beneficiaryData['grade_id']) : null;
                    $nameSnapshot = ! empty(trim($beneficiaryData['name'] ?? ''))
                        ? trim($beneficiaryData['name'])
                        : ($grade ? 'دفعة '.$grade->name : 'مستفيد مدرسة');

                    $beneficiaries[$index] = $contract->beneficiaries()->create([
                        'child_id' => $beneficiaryData['child_id'] ?? null,
                        'name_snapshot' => $nameSnapshot,
                        'grade_snapshot' => $grade?->name,
                        'source_reference' => $beneficiaryData['source_reference'] ?? null,
                    ]);
                }
                foreach ($flatItems as $flat) {
                    $beneficiary = $beneficiaries[$flat['beneficiary_index']];
                    $item = $flat['item'];
                    $orderItem = $beneficiary->items()->create([
                        'order_id' => $order->id,
                        'child_id' => $beneficiary->child_id,
                        'inventory_variant_id' => $item['inventory_variant_id'] ?? null,
                        'garment_type_id' => $item['garment_type_id'] ?? null,
                        'description_snapshot' => trim($item['description']),
                        'quantity' => $item['quantity'],
                        'unit_price' => Money::fromMinorUnits(Money::toMinorUnits($item['unit_price'])),
                        'discount_amount' => Money::fromMinorUnits($flat['discount']),
                        'line_total' => Money::fromMinorUnits($flat['gross'] - $flat['discount']),
                    ]);
                    if ($beneficiary->child_id) {
                        $measurements = Child::query()->findOrFail($beneficiary->child_id)->currentMeasurements?->only([
                            'shoulder_cm', 'chest_cm', 'sleeve_cm', 'waist_circumference_cm', 'length_cm',
                        ]) ?? [];
                        if (array_filter($measurements, fn ($value) => $value !== null && $value !== '') !== []) {
                            $orderItem->measurementSnapshot()->create($measurements);
                        }
                    }
                }

                $audit->record('school_contract_order_created', $order, $order->id, ['school_id', 'beneficiary_count', 'item_count', 'totals']);

                if ($initialPaymentAmount > 0) {
                    $method = $data['initial_payment']['method'] ?? 'cash';
                    $ref = ! empty($data['initial_payment']['transaction_reference']) ? trim($data['initial_payment']['transaction_reference']) : null;
                    $paidAt = ! empty($data['initial_payment']['paid_at'])
                        ? LocalDateTime::toUtc($data['initial_payment']['paid_at'])
                        : now();

                    $payment = $order->payments()->create([
                        'amount' => Money::fromMinorUnits($initialPaymentAmount),
                        'paid_at' => $paidAt,
                        'method' => $method,
                        'verification_status' => $method === 'cash' ? 'not_required' : 'pending',
                        'transaction_reference' => $ref,
                        'recorded_by' => $request->user()->id,
                        'notes' => $data['initial_payment']['notes'] ?? 'دفعة مقدمة عند إنشاء عقد المدرسة',
                    ]);

                    if ($file = $request->file('initial_payment_evidence')) {
                        $sha = hash_file('sha256', $file->getRealPath());
                        $evidencePath = $file->store('payment-evidence/'.$order->id, 'private');
                        $payment->evidence()->create([
                            'private_path' => $evidencePath,
                            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                            'mime_type' => $file->getMimeType(),
                            'size_bytes' => $file->getSize(),
                            'sha256' => $sha,
                            'uploaded_by' => $request->user()->id,
                        ]);
                    }

                    $audit->record('payment_recorded', $payment, $payment->id, ['order_id', 'amount', 'method', 'verification_status', 'transaction_reference']);
                }

                return $order;
            });
        } catch (Throwable $exception) {
            if ($evidencePath) {
                Storage::disk('private')->delete($evidencePath);
            }
            throw $exception;
        }

        $totalGarments = (int) array_sum(array_column(array_column($flatItems, 'item'), 'quantity'));
        $gradeCount = count(array_unique(array_filter(array_column($data['beneficiaries'], 'grade_id'))));
        $statusMsg = "تم إنشاء طلب المدرسة (#{$order->number}) بنجاح: تم طلب {$totalGarments} لبسة عبر {$gradeCount} مرحلة/دفعات بإجمالي ".number_format($orderApprovedMinor / 100, 2).' ج.س.';

        if ($initialPaymentAmount > 0) {
            $remaining = max(0, $orderApprovedMinor - $initialPaymentAmount);
            $statusMsg .= ' تم تسجيل دفعة بمبلغ '.number_format($initialPaymentAmount / 100, 2).' ج.س (المتبقي: '.number_format($remaining / 100, 2).' ج.س).';
        }

        return redirect()->route('orders.show', $order)->with('status', $statusMsg);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('orders.create');
        $data = $request->validate([
            'type' => ['required', 'in:custom,alteration,ready'],
            'guardian_id' => ['required', 'integer', 'exists:guardians,id'],
            'due_on' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.child_id' => ['nullable', 'integer', 'exists:children,id'],
            'items.*.inventory_variant_id' => ['nullable', 'integer', 'exists:inventory_variants,id'],
            'items.*.garment_type_id' => ['nullable', 'integer', 'exists:garment_types,id'],
            'items.*.description' => ['required', 'string', 'max:180'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/'],
            'items.*.measurements.shoulder_cm' => ['nullable', 'numeric', 'gt:0', 'max:500'],
            'items.*.measurements.chest_cm' => ['nullable', 'numeric', 'gt:0', 'max:500'],
            'items.*.measurements.sleeve_cm' => ['nullable', 'numeric', 'gt:0', 'max:500'],
            'items.*.measurements.waist_circumference_cm' => ['nullable', 'numeric', 'gt:0', 'max:500'],
            'items.*.measurements.length_cm' => ['nullable', 'numeric', 'gt:0', 'max:500'],
        ]);

        $guardian = Guardian::query()->withTrashed()->findOrFail($data['guardian_id']);
        if ($guardian->trashed()) {
            throw ValidationException::withMessages(['guardian_id' => 'لا يمكن إنشاء طلب لملف ولي أمر مؤرشف.']);
        }

        $itemAmounts = [];
        foreach ($data['items'] as $index => $item) {
            if ($data['type'] === 'ready' && empty($item['inventory_variant_id'])) {
                throw ValidationException::withMessages(["items.$index.inventory_variant_id" => 'بيع الملابس الجاهزة يتطلب اختيار صنف ومقاس من المخزون.']);
            }
            if (! empty($item['child_id'])) {
                $childBelongs = Child::query()->whereKey($item['child_id'])->where('guardian_id', $guardian->id)->exists();
                if (! $childBelongs) {
                    throw ValidationException::withMessages(["items.$index.child_id" => 'الطفل المحدد لا يتبع ولي الأمر المختار.']);
                }
            }
            if (! empty($item['inventory_variant_id']) && ! InventoryVariant::query()->whereKey($item['inventory_variant_id'])->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(["items.$index.inventory_variant_id" => 'الصنف المحدد غير نشط.']);
            }
            try {
                $gross = Money::toMinorUnits($item['unit_price']) * (int) $item['quantity'];
                $discount = Money::toMinorUnits($item['discount'] ?? '0');
            } catch (Throwable) {
                throw ValidationException::withMessages(["items.$index.unit_price" => 'تحقق من السعر والخصم المدخلين.']);
            }
            if ($discount > $gross) {
                throw ValidationException::withMessages(["items.$index.discount" => 'خصم البند لا يمكن أن يتجاوز قيمته.']);
            }
            $itemAmounts[$index] = ['gross' => $gross, 'discount' => $discount, 'net' => $gross - $discount];
        }

        $itemsGross = array_sum(array_column($itemAmounts, 'gross'));
        $discountTotal = array_sum(array_column($itemAmounts, 'discount'));
        $approvedTotal = $itemsGross - $discountTotal;
        $number = $this->newOrderNumber();

        $order = DB::transaction(function () use ($data, $guardian, $itemAmounts, $itemsGross, $discountTotal, $approvedTotal, $number, $request, $audit): Order {
            $stockDemand = [];
            if ($data['type'] === 'ready') {
                foreach ($data['items'] as $item) {
                    $variantId = (int) $item['inventory_variant_id'];
                    $stockDemand[$variantId] = ($stockDemand[$variantId] ?? 0) + (int) $item['quantity'];
                }
                foreach ($stockDemand as $variantId => $quantity) {
                    $balance = InventoryBalance::query()->where('inventory_variant_id', $variantId)->lockForUpdate()->first();
                    if (! $balance || $balance->quantity_available < $quantity) {
                        throw ValidationException::withMessages(['items' => 'الكمية المطلوبة تتجاوز المخزون المتاح. لم يُنشأ الطلب ولم يتغير المخزون.']);
                    }
                }
            }
            $order = Order::query()->create([
                'number' => $number,
                'type' => $data['type'],
                'guardian_id' => $guardian->id,
                'customer_name_snapshot' => $guardian->name,
                'created_by' => $request->user()->id,
                'created_on' => now(config('app.display_timezone', 'Africa/Khartoum'))->toDateString(),
                'due_on' => $data['due_on'] ?? null,
                'operational_status' => 'new',
                'notes' => $data['notes'] ?? null,
                'items_total' => Money::fromMinorUnits($itemsGross),
                'discount_total' => Money::fromMinorUnits($discountTotal),
                'approved_total' => Money::fromMinorUnits($approvedTotal),
            ]);

            foreach ($data['items'] as $index => $item) {
                $orderItem = $order->items()->create([
                    'child_id' => $item['child_id'] ?? null,
                    'inventory_variant_id' => $item['inventory_variant_id'] ?? null,
                    'garment_type_id' => $item['garment_type_id'] ?? null,
                    'description_snapshot' => trim($item['description']),
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => Money::fromMinorUnits(Money::toMinorUnits($item['unit_price'])),
                    'discount_amount' => Money::fromMinorUnits($itemAmounts[$index]['discount']),
                    'line_total' => Money::fromMinorUnits($itemAmounts[$index]['net']),
                ]);

                if ($data['type'] === 'ready') {
                    $variantId = (int) $item['inventory_variant_id'];
                    InventoryBalance::query()->where('inventory_variant_id', $variantId)->decrement('quantity_available', (int) $item['quantity']);
                    InventoryMovement::query()->create([
                        'inventory_variant_id' => $variantId, 'quantity_change' => -(int) $item['quantity'],
                        'movement_type' => 'ready_sale', 'order_item_id' => $orderItem->id,
                        'reason' => 'بيع مخزون جاهز للطلب '.$order->number, 'occurred_at' => now('UTC'),
                        'recorded_by' => $request->user()->id,
                    ]);
                }

                if (! empty($item['child_id'])) {
                    $measurements = $item['measurements'] ?? Child::query()->findOrFail($item['child_id'])->currentMeasurements?->only([
                        'shoulder_cm', 'chest_cm', 'sleeve_cm', 'waist_circumference_cm', 'length_cm',
                    ]) ?? [];
                    if (array_filter($measurements, fn ($value) => $value !== null && $value !== '') !== []) {
                        $orderItem->measurementSnapshot()->create($measurements);
                    }
                }
            }

            $audit->record('order_created', $order, $order->id, ['type', 'guardian_id', 'items', 'totals']);

            return $order;
        });

        return redirect()->route('orders.show', $order)->with('status', 'تم إنشاء الطلب وحفظ أسعار البنود ومقاسات الطفل وقت الطلب.');
    }

    public function show(Order $order, OrderFinancialSummary $financialSummary): View
    {
        Gate::authorize('orders.view');
        $order->load(['guardian', 'schoolContract.school', 'schoolContract.beneficiaries', 'items.beneficiary', 'items.child.currentMeasurements', 'items.variant.garmentType', 'items.measurementSnapshot', 'creator', 'payments', 'cancellations', 'refunds.originalPayment', 'refunds.transactions', 'deliveries.items.orderItem', 'financialCorrections']);
        $order->setAttribute('financial_summary', $financialSummary->for($order));
        $order->load('deliveries.items');
        foreach ($order->items as $item) {
            $item->setAttribute('delivered_quantity', $item->deliveryItems()->sum('quantity_delivered'));
        }

        return view('orders.show', compact('order'));
    }

    public function statement(Order $order, OrderFinancialSummary $financialSummary): View
    {
        Gate::authorize('orders.view');
        $order->load([
            'guardian', 'schoolContract.school', 'schoolContract.beneficiaries.items',
            'items.beneficiary', 'items.child', 'items.variant.garmentType',
            'creator', 'payments', 'deliveries.items.orderItem',
        ]);
        $order->setAttribute('financial_summary', $financialSummary->for($order));
        foreach ($order->items as $item) {
            $item->setAttribute('delivered_quantity', (int) $item->deliveryItems()->sum('quantity_delivered'));
        }

        return view('orders.statement', compact('order'));
    }

    public function statementPdf(Order $order, OrderFinancialSummary $financialSummary, ReportPdfService $pdf): Response
    {
        Gate::authorize('orders.view');
        $order->load([
            'guardian', 'schoolContract.school', 'schoolContract.beneficiaries.items',
            'items.beneficiary', 'items.child', 'items.variant.garmentType',
            'creator', 'payments', 'deliveries.items.orderItem',
        ]);
        $order->setAttribute('financial_summary', $financialSummary->for($order));
        foreach ($order->items as $item) {
            $item->setAttribute('delivered_quantity', (int) $item->deliveryItems()->sum('quantity_delivered'));
        }

        $html = view('orders.statement-pdf', compact('order'))->render();
        $content = $pdf->render($html, 'P');

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="statement-order-'.$order->number.'.pdf"',
        ]);
    }

    private function newOrderNumber(): string
    {
        return app(OrderNumberGenerator::class)->next();
    }
}
