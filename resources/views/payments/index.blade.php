@extends('layouts.app')
@section('title', 'الدفعات والتحصيل')
@section('content')
<header class="mb-6">
    <p class="text-xs sm:text-sm font-semibold text-[var(--brand-gold)]">التحصيل والمالية</p>
    <h1 class="mt-0.5 text-xl sm:text-2xl font-bold text-[var(--brand-navy)]">الدفعات والتحصيل</h1>
    <p class="mt-1 text-xs sm:text-sm text-slate-600">النقد يُحتسب فور تسجيله كاش، والتحويل البنكي يظل بانتظار مراجعة المدير قبل اعتماده.</p>
</header>

<section class="mb-8 rounded-xl border border-slate-200 bg-white p-4 sm:p-5 shadow-xs">
    <h2 class="mb-4 text-base sm:text-lg font-bold text-slate-900">تسجيل دفعة جديدة</h2>
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-800 font-medium">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route('payments.store') }}" enctype="multipart/form-data" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @csrf
        <label class="text-xs sm:text-sm font-semibold text-slate-700">الطلب <span class="text-red-600">*</span>
            <select required name="order_id" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5 text-sm">
                <option value="">اختر الطلب</option>
                @foreach ($orders as $order)
                    <option value="{{ $order->id }}" @selected((string) old('order_id', request('order_id')) === (string) $order->id)>
                        {{ $order->number }} · {{ $order->customer_name_snapshot }} · المتبقي {{ number_format($order->financial_summary['due']/100,2) }} ج.س
                    </option>
                @endforeach
            </select>
        </label>
        <label class="text-xs sm:text-sm font-semibold text-slate-700">المبلغ (ج.س) <span class="text-red-600">*</span>
            <input required name="amount" inputmode="decimal" placeholder="0.00" value="{{ old('amount') }}" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5 text-sm">
        </label>
        <label class="text-xs sm:text-sm font-semibold text-slate-700">طريقة الدفع <span class="text-red-600">*</span>
            <select required name="method" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5 text-sm">
                <option value="cash">نقد (كاش مستلم)</option>
                <option value="bank_transfer">تحويل بنكي</option>
            </select>
        </label>
        <label class="text-xs sm:text-sm font-semibold text-slate-700">تاريخ ووقت الدفع <span class="text-red-600">*</span>
            <input required type="datetime-local" name="paid_at" value="{{ old('paid_at', now()->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5 text-sm">
        </label>
        <label class="text-xs sm:text-sm font-semibold text-slate-700">رقم العملية البنكية (اختياري)
            <input name="transaction_reference" placeholder="مثال: TRX-12345" value="{{ old('transaction_reference') }}" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5 text-sm">
        </label>
        <label class="text-xs sm:text-sm font-semibold text-slate-700">إشعار التحويل (PDF/JPG/PNG حتى 5MB)
            <input type="file" name="evidence" accept=".pdf,.jpg,.jpeg,.png" class="mt-1 w-full rounded-lg border border-slate-300 p-1.5 text-sm">
        </label>
        <label class="text-xs sm:text-sm font-semibold text-slate-700 sm:col-span-2 lg:col-span-3">ملاحظات
            <textarea name="notes" rows="2" placeholder="أي ملاحظات حول الدفعة..." class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">{{ old('notes') }}</textarea>
        </label>
        <button class="rounded-xl bg-[var(--brand-navy)] px-5 py-3 font-bold text-white shadow-xs hover:opacity-95 transition sm:col-span-2 lg:col-span-3 min-h-[48px] active:scale-[0.99]">
            حفظ الدفعة
        </button>
    </form>
</section>

<!-- ملخص الرصيد حسب الطلب -->
<section class="mb-8 rounded-xl border border-slate-200 bg-white p-4 sm:p-5 shadow-xs">
    <div class="mb-3">
        <h2 class="text-base sm:text-lg font-bold text-slate-900">ملخص الرصيد حسب الطلب</h2>
        <p class="mt-0.5 text-xs text-slate-500">كاش وبنك مستلم ومؤكد (المؤكد) يحتسب النقد والتحويلات المعتمدة، بينما (المسجل) يشمل أيضاً إشعارات بنك بانتظار المراجعة.</p>
    </div>

    <!-- جدول الكمبيوتر -->
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-right text-sm">
            <thead class="bg-slate-100 text-xs font-bold text-slate-800 border-b border-slate-300">
                <tr>
                    <th class="p-3">الطلب والعميل</th>
                    <th class="p-3">قيمة الطلب</th>
                    <th class="p-3">المسجل (شامل المعلق)</th>
                    <th class="p-3">المؤكد (كاش وبنك معتمد)</th>
                    <th class="p-3">المتبقي</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @foreach($orders as $order)
                    @php($balance = $order->financial_summary)
                    @php($due = $balance['due'] / 100)
                    <tr class="hover:bg-slate-50/70 transition">
                        <td class="p-3">
                            <a href="{{ route('orders.show', $order) }}" class="font-bold text-[var(--brand-navy)] hover:underline">{{ $order->number }}</a>
                            <span class="text-slate-600 block text-xs">{{ $order->customer_name_snapshot }}</span>
                        </td>
                        <td class="p-3 font-semibold text-slate-900">{{ number_format((float)$order->approved_total, 2) }} ج.س</td>
                        <td class="p-3 text-slate-700">{{ number_format($balance['recorded']/100, 2) }} ج.س</td>
                        <td class="p-3 font-bold text-emerald-700">{{ number_format($balance['net_paid']/100, 2) }} ج.س</td>
                        <td class="p-3 font-extrabold {{ $due > 0 ? 'text-rose-700' : 'text-slate-600' }}">{{ number_format($due, 2) }} ج.س</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- بطاقات الهاتف لملخص الأرصدة -->
    <div class="grid grid-cols-1 gap-3 md:hidden">
        @foreach($orders as $order)
            @php($balance = $order->financial_summary)
            @php($due = $balance['due'] / 100)
            <article class="rounded-lg border border-slate-200 bg-white p-3 shadow-2xs">
                <div class="flex items-start justify-between gap-2 border-b border-slate-100 pb-2">
                    <div>
                        <a href="{{ route('orders.show', $order) }}" class="font-bold text-sm text-[var(--brand-navy)] hover:underline">{{ $order->number }}</a>
                        <p class="text-xs font-medium text-slate-800">{{ $order->customer_name_snapshot }}</p>
                    </div>
                    <span class="text-xs font-bold {{ $due > 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                        {{ $due > 0 ? 'متبقٍ: '.number_format($due,2).' ج.س' : 'خالص بالكامل' }}
                    </span>
                </div>
                <div class="mt-2.5 grid grid-cols-3 gap-2 text-center text-xs bg-slate-50 p-2 rounded-md">
                    <div>
                        <span class="text-[10px] text-slate-500 block">الإجمالي</span>
                        <b class="text-slate-900">{{ number_format((float)$order->approved_total, 2) }}</b>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-500 block">المسجل</span>
                        <b class="text-slate-700">{{ number_format($balance['recorded']/100, 2) }}</b>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-500 block">المؤكد</span>
                        <b class="text-emerald-700">{{ number_format($balance['net_paid']/100, 2) }}</b>
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    <div class="border-t border-slate-100 pt-3 mt-3">
        {{ $orders->links() }}
    </div>
</section>

<!-- سجل الدفعات -->
<section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5 shadow-xs">
    <div class="mb-4">
        <h2 class="text-base sm:text-lg font-bold text-slate-900">سجل الدفعات</h2>
        <p class="text-xs text-slate-500">حركات السداد المستلمة وإشعارات البنك مع إمكانية اعتماد التحويل أو إنشاء قيد تسوية عكسي لتصحيح الخطأ.</p>
    </div>

    <!-- جدول الكمبيوتر (hidden md:block) -->
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-right text-sm">
            <thead class="bg-slate-100 text-xs font-bold text-slate-800 border-b border-slate-300">
                <tr>
                    <th class="p-3">الطلب والعميل</th>
                    <th class="p-3">المبلغ</th>
                    <th class="p-3">الطريقة</th>
                    <th class="p-3">الحالة</th>
                    <th class="p-3">التاريخ</th>
                    <th class="p-3">المرفق</th>
                    <th class="p-3 text-center">إجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($payments as $payment)
                    <tr class="hover:bg-slate-50/70 transition">
                        <td class="p-3">
                            <a href="{{ route('orders.show', $payment->order) }}" class="font-bold text-[var(--brand-navy)] hover:underline">{{ $payment->order->number }}</a>
                            <span class="block text-xs text-slate-500">{{ $payment->order->customer_name_snapshot }}</span>
                        </td>
                        <td class="p-3 font-bold text-slate-900">{{ number_format((float)$payment->amount, 2) }} ج.س</td>
                        <td class="p-3">
                            <span class="inline-flex items-center gap-1 text-xs">
                                @if($payment->method === 'cash')
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-emerald-800 font-bold">نقد كاش</span>
                                @else
                                    <span class="rounded bg-blue-100 px-2 py-0.5 text-blue-800 font-bold">تحويل بنكي</span>
                                @endif
                            </span>
                        </td>
                        <td class="p-3">
                            @php($verBadge = [
                                'not_required' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                'pending' => 'bg-amber-100 text-amber-800 border-amber-200',
                                'verified' => 'bg-emerald-100 text-emerald-800 border-emerald-200'
                            ][$payment->verification_status] ?? 'bg-slate-100 text-slate-800')
                            <span class="inline-block rounded-full border px-2 py-0.5 text-xs font-semibold {{ $verBadge }}">
                                {{ ['not_required'=>'كاش مؤكد','pending'=>'إشعار بانتظار المراجعة','verified'=>'تحويل معتمد'][$payment->verification_status] ?? $payment->verification_status }}
                            </span>
                        </td>
                        <td class="p-3 text-xs text-slate-500">{{ $payment->paid_at->format('Y-m-d H:i') }}</td>
                        <td class="p-3 text-xs">
                            @if (auth()->user()->can('finance.view') && $payment->evidence->isNotEmpty())
                                @foreach ($payment->evidence as $evidence)
                                    <a class="text-blue-700 underline font-semibold block" href="{{ route('payments.evidence', $evidence) }}">
                                        عرض الإشعار
                                    </a>
                                @endforeach
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="p-3 text-center">
                            <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                @if ($payment->verification_status === 'pending' && auth()->user()->can('payments.verify'))
                                    <form method="POST" action="{{ route('payments.verify', $payment) }}">
                                        @csrf
                                        <button class="rounded-lg bg-emerald-700 px-2.5 py-1 text-xs font-bold text-white hover:bg-emerald-800 transition">
                                            اعتماد التحويل
                                        </button>
                                    </form>
                                @endif

                                @can('payments.record')
                                    <details class="relative inline-block text-right">
                                        <summary class="rounded-lg border border-rose-300 bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 cursor-pointer transition">
                                            إلغاء / تصحيح المبلغ
                                        </summary>
                                        <div class="absolute left-0 mt-1 w-64 rounded-xl border border-slate-200 bg-white p-3 shadow-xl z-20">
                                            <form method="POST" action="{{ route('payments.reverse', $payment) }}" class="space-y-2">
                                                @csrf
                                                <p class="text-xs font-bold text-slate-800">قيد تسوية عكسي للدفعة #{{ $payment->id }}</p>
                                                <input name="reason" required placeholder="سبب الإلغاء أو التصحيح..." class="w-full text-xs p-2 border rounded-lg focus:outline-hidden">
                                                <button class="w-full rounded-lg bg-rose-700 text-white text-xs py-2 font-bold hover:bg-rose-800 transition">
                                                    تأكيد القيد العكسي
                                                </button>
                                            </form>
                                        </div>
                                    </details>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center text-slate-500">لا توجد دفعات مسجلة.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- بطاقات الهاتف لسجل الدفعات (md:hidden) -->
    <div class="grid grid-cols-1 gap-3 md:hidden">
        @forelse ($payments as $payment)
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
                <!-- رأس البطاقة -->
                <div class="flex items-start justify-between gap-2 border-b border-slate-100 pb-3">
                    <div>
                        <a href="{{ route('orders.show', $payment->order) }}" class="font-bold text-base text-[var(--brand-navy)] hover:underline">
                            {{ $payment->order->number }}
                        </a>
                        <p class="mt-0.5 text-xs text-slate-700 font-medium">{{ $payment->order->customer_name_snapshot }}</p>
                    </div>
                    <div>
                        @php($cardVerClass = [
                            'not_required' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                            'pending' => 'bg-amber-100 text-amber-800 border-amber-200',
                            'verified' => 'bg-emerald-100 text-emerald-800 border-emerald-200'
                        ][$payment->verification_status] ?? 'bg-slate-100 text-slate-800')
                        <span class="inline-block rounded-full border px-2.5 py-1 text-xs font-bold {{ $cardVerClass }}">
                            {{ ['not_required'=>'كاش مؤكد','pending'=>'إشعار بانتظار المراجعة','verified'=>'تحويل معتمد'][$payment->verification_status] ?? $payment->verification_status }}
                        </span>
                    </div>
                </div>

                <!-- جسم البطاقة -->
                <div class="my-3 grid grid-cols-2 gap-2 rounded-lg bg-slate-50 p-2.5 text-xs">
                    <div>
                        <span class="text-[10px] text-slate-500 block">المبلغ المستلم</span>
                        <b class="text-base text-slate-900">{{ number_format((float)$payment->amount, 2) }} <span class="text-[10px] font-normal text-slate-500">ج.س</span></b>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-500 block">طريقة الدفع</span>
                        <b class="text-sm text-slate-800">{{ $payment->method === 'cash' ? 'نقد كاش' : 'تحويل بنكي' }}</b>
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs text-slate-500 mb-3 px-1">
                    <time>{{ $payment->paid_at->format('Y-m-d H:i') }}</time>
                    @if (auth()->user()->can('finance.view') && $payment->evidence->isNotEmpty())
                        @foreach ($payment->evidence as $evidence)
                            <a class="text-blue-700 underline font-semibold" href="{{ route('payments.evidence', $evidence) }}">
                                مستند التحويل
                            </a>
                        @endforeach
                    @endif
                </div>

                <!-- ذيل البطاقة: أزرار مناسبة للمس (min-h-[44px]) -->
                <div class="flex flex-col gap-2 pt-2 border-t border-slate-100">
                    @if ($payment->verification_status === 'pending' && auth()->user()->can('payments.verify'))
                        <form method="POST" action="{{ route('payments.verify', $payment) }}">
                            @csrf
                            <button class="w-full flex items-center justify-center rounded-lg bg-emerald-700 px-3 py-2.5 text-sm font-bold text-white hover:bg-emerald-800 transition min-h-[44px]">
                                اعتماد التحويل البنكي
                            </button>
                        </form>
                    @endif

                    @can('payments.record')
                        <details class="w-full">
                            <summary class="w-full flex items-center justify-center rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100 cursor-pointer transition min-h-[44px]">
                                إلغاء / تصحيح المبلغ
                            </summary>
                            <form method="POST" action="{{ route('payments.reverse', $payment) }}" class="mt-2 space-y-2 p-3 bg-slate-50 border rounded-xl">
                                @csrf
                                <p class="text-xs font-bold text-slate-800">إنشاء قيد تسوية عكسي لتصحيح الدفعة</p>
                                <input name="reason" required placeholder="سبب الإلغاء أو التصحيح..." class="w-full text-xs p-2.5 border rounded-lg bg-white">
                                <button class="w-full rounded-lg bg-rose-700 text-white text-xs py-2.5 font-bold hover:bg-rose-800 transition min-h-[40px]">
                                    تأكيد قيد التسوية العكسي
                                </button>
                            </form>
                        </details>
                    @endcan
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-500">
                لا توجد دفعات مسجلة.
            </div>
        @endforelse
    </div>

    <div class="border-t border-slate-100 pt-3 mt-3">
        {{ $payments->links() }}
    </div>
</section>
@endsection

