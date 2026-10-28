@extends('layouts.app')
@section('title', 'الإنتاج والترزي')
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-[var(--brand-gold)]">متابعة العمليات اليومية</p>
            <h1 class="mt-1 text-2xl font-bold text-[var(--brand-navy)]">الإنتاج والترزي</h1>
            <p class="mt-1 text-xs text-[var(--brand-muted)] sm:text-sm">متابعة مراحل التنفيذ، وإرسال واستلام الكميات من الترزية خطوة بخطوة.</p>
        </div>
    </header>

    @if ($errors->any())
        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($eligibleItems->isNotEmpty())
        <section class="rounded-xl border border-[var(--brand-border)] bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-base font-bold text-[var(--brand-navy)]">بدء متابعة بند جديد</h2>
            <form method="POST" action="{{ route('production.jobs.store') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                <label class="text-xs font-semibold text-slate-700">الطلب والبند
                    <select name="order_item_id" required class="mt-1 w-full rounded-lg border border-slate-300 p-2.5 text-sm focus:border-[var(--brand-navy)]">
                        <option value="">اختر البند لبدء الإنتاج</option>
                        @foreach($eligibleItems as $item)
                            <option value="{{ $item->id }}">{{ $item->order->number }} · {{ $item->order->guardian?->name }} · {{ $item->description_snapshot }} (الكمية: {{ $item->quantity }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-700">كمية الإنتاج
                    <input type="number" name="quantity" min="1" max="500" required placeholder="العدد" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm focus:border-[var(--brand-navy)]">
                </label>
                <label class="text-xs font-semibold text-slate-700">موعد متوقع للتسليم
                    <input type="date" name="expected_on" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm focus:border-[var(--brand-navy)]">
                </label>
                <label class="text-xs font-semibold text-slate-700">ملاحظات
                    <input name="notes" maxlength="2000" placeholder="أي تفاصيل خاصة" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm focus:border-[var(--brand-navy)]">
                </label>
                <button class="inline-flex min-h-[44px] items-center justify-center rounded-lg bg-[var(--brand-navy)] px-5 py-2.5 text-sm font-bold text-white shadow hover:opacity-95 sm:col-span-2 lg:col-span-4">
                    بدء الإنتاج للبند المختار
                </button>
            </form>
        </section>
    @endif

    <section class="space-y-4">
        <h2 class="text-lg font-bold text-[var(--brand-navy)]">سجلات الإنتاج الحالية</h2>
        
        @forelse($jobs as $job)
            @php($sent = $job->dispatches->flatMap(fn($dispatch) => $dispatch->items)->sum('quantity_sent'))
            @php($received = $job->dispatches->flatMap(fn($dispatch) => $dispatch->items)->flatMap(fn($item) => $item->receipts)->sum('quantity_received'))
            @php($stageLabels = ['not_started'=>'لم يبدأ','sewing'=>'قيد الخياطة','with_tailor'=>'لدى الترزي','waiting_completion'=>'بانتظار الإكمال','completed'=>'مكتمل'])
            
            <article class="space-y-5 rounded-xl border border-[var(--brand-border)] bg-white p-5 shadow-sm">
                <!-- رأس السجل والمعلومات الأساسية -->
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('orders.show', $job->order) }}" class="font-bold text-[var(--brand-navy)] underline hover:text-[var(--brand-gold)]">
                                طلب رقم: {{ $job->order->number }}
                            </a>
                            <span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-700">{{ $job->order->guardian?->name ?? 'عميل' }}</span>
                        </div>
                        <p class="mt-1.5 text-sm font-semibold text-slate-800">{{ $job->orderItem->description_snapshot }}</p>
                        <div class="mt-2 flex flex-wrap gap-2 text-xs text-[var(--brand-muted)]">
                            <span class="rounded bg-slate-50 px-2 py-1 border border-slate-200">الكمية الإجمالية: <strong>{{ $job->quantity }}</strong></span>
                            <span class="rounded bg-blue-50 px-2 py-1 text-blue-800 border border-blue-200">أُرسل للترزي: <strong>{{ $sent }}</strong></span>
                            <span class="rounded bg-emerald-50 px-2 py-1 text-emerald-800 border border-emerald-200">تم استلامه: <strong>{{ $received }}</strong></span>
                            <span class="rounded bg-amber-50 px-2 py-1 text-amber-800 border border-amber-200">المتبقي لدى الترزي: <strong>{{ max(0, $sent - $received) }}</strong></span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-800">
                            {{ $stageLabels[$job->stage] ?? $job->stage }}
                        </span>
                        @if($job->expected_on)
                            @php($isLate = $job->expected_on->lt(today(config('app.display_timezone'))) && $job->stage !== 'completed')
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $isLate ? 'bg-red-100 text-red-800 border border-red-200' : 'bg-slate-100 text-slate-700' }}">
                                الموعد المتوقع: {{ $job->expected_on->format('d/m/Y') }}
                                @if($isLate) (متأخر) @endif
                            </span>
                        @endif
                    </div>
                </div>

                <!-- شاشتا الإجراءات: فصل بصري وألوان واضحة لمنع التضارب واللمس الخاطئ -->
                <div class="grid gap-4 lg:grid-cols-2">
                    <!-- القسم الأول: تحديث مرحلة الإنتاج -->
                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                        <h3 class="mb-3 flex items-center gap-2 text-sm font-bold text-slate-800">
                            <svg class="h-4 w-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            تحديث مرحلة العمل الداخلية
                        </h3>
                        <form method="POST" action="{{ route('production.jobs.stage.update', $job) }}" class="space-y-3">
                            @csrf @method('PATCH')
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-700">المرحلة الجديدة</label>
                                <select name="stage" class="w-full rounded-lg border border-slate-300 bg-white p-2.5 text-xs font-semibold focus:border-[var(--brand-navy)]">
                                    @foreach($stageLabels as $value=>$label)
                                        <option value="{{ $value }}" @selected($job->stage===$value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-700">سبب أو ملاحظة التغيير <span class="text-red-600">*</span></label>
                                <input name="reason" required maxlength="500" placeholder="مثال: تم الانتهاء من القص والبدء بالخياطة" class="w-full rounded-lg border border-slate-300 bg-white p-2 text-xs focus:border-[var(--brand-navy)]">
                            </div>
                            <button class="inline-flex min-h-[44px] w-full items-center justify-center rounded-lg border border-slate-400 bg-slate-700 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-slate-800">
                                حفظ المرحلة
                            </button>
                        </form>
                    </div>

                    <!-- القسم الثاني: تسجيل إرسال كمية للترزي الخارجي -->
                    <div class="rounded-xl border border-amber-200/90 bg-amber-50/40 p-4">
                        <h3 class="mb-3 flex items-center gap-2 text-sm font-bold text-amber-950">
                            <svg class="h-4 w-4 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            إرسال كمية لخياط / ترزي خارجي
                        </h3>
                        <form method="POST" action="{{ route('production.dispatches.store', $job) }}" onsubmit="return confirm('هل أنت متأكد من تسجيل إرسال هذه الكمية للترزي؟');" class="space-y-3">
                            @csrf
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-700">اسم الترزي / المستلم <span class="text-red-600">*</span></label>
                                    <input name="recipient_name" required maxlength="160" placeholder="اسم الترزي" class="w-full rounded-lg border border-slate-300 bg-white p-2 text-xs focus:border-[var(--brand-navy)]">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-700">تاريخ الإرسال <span class="text-red-600">*</span></label>
                                    <input type="date" name="sent_on" value="{{ now(config('app.display_timezone'))->toDateString() }}" required class="w-full rounded-lg border border-slate-300 bg-white p-2 text-xs focus:border-[var(--brand-navy)]">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-700">الكمية المرسلة <span class="text-red-600">*</span></label>
                                    <input type="number" name="quantity_sent" min="1" max="{{ max(1, $job->quantity - $sent) }}" value="{{ max(1, $job->quantity - $sent) }}" required class="w-full rounded-lg border border-slate-300 bg-white p-2 text-xs focus:border-[var(--brand-navy)]">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-700">الأجر المتفق عليه (ج.س)</label>
                                    <input name="agreed_cost" inputmode="decimal" placeholder="اختياري" class="w-full rounded-lg border border-slate-300 bg-white p-2 text-xs focus:border-[var(--brand-navy)]">
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-700">ملاحظات الإرسال</label>
                                <input name="notes" maxlength="2000" placeholder="مثال: قماش مع الأزرار والباترون" class="w-full rounded-lg border border-slate-300 bg-white p-2 text-xs focus:border-[var(--brand-navy)]">
                            </div>
                            <button class="inline-flex min-h-[44px] w-full items-center justify-center rounded-lg bg-[var(--brand-navy)] px-4 py-2 text-xs font-bold text-white shadow-sm hover:opacity-95 disabled:cursor-not-allowed disabled:bg-slate-300" @disabled($job->quantity <= $sent)>
                                تسجيل الإرسال للترزي
                            </button>
                        </form>
                    </div>
                </div>

                <!-- سجل الإرسال والاستلام السابق -->
                @if($job->dispatches->isNotEmpty())
                    <div class="space-y-3 border-t border-slate-100 pt-3">
                        <h4 class="text-xs font-bold uppercase tracking-wide text-slate-600">سجل الإرساليات والاستلام من الترزية</h4>
                        <div class="space-y-2">
                            @foreach($job->dispatches as $dispatch)
                                <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="text-xs font-bold text-slate-800">
                                            إرسالية بتاريخ {{ $dispatch->sent_on->format('d/m/Y') }} — إلى: {{ $dispatch->recipient_name }}
                                        </p>
                                        @if($dispatch->notes)
                                            <span class="text-xs text-[var(--brand-muted)]">{{ $dispatch->notes }}</span>
                                        @endif
                                    </div>

                                    @foreach($dispatch->items as $dispatchItem)
                                        @php($receivedItem = $dispatchItem->receipts->sum('quantity_received'))
                                        @php($remItem = max(0, $dispatchItem->quantity_sent - $receivedItem))
                                        <div class="mt-2.5 rounded-lg border border-slate-200/80 bg-white p-3 text-xs">
                                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
                                                <span>
                                                    أُرسل: <strong>{{ $dispatchItem->quantity_sent }}</strong> قطعة ·
                                                    مستلم: <strong class="text-emerald-700">{{ $receivedItem }}</strong> ·
                                                    متبقٍ: <strong class="{{ $remItem > 0 ? 'text-amber-700' : 'text-slate-600' }}">{{ $remItem }}</strong>
                                                    @if($dispatchItem->agreed_cost)
                                                        · الأجر: <strong>{{ number_format((float)$dispatchItem->agreed_cost, 2) }} ج.س</strong>
                                                    @endif
                                                </span>
                                            </div>

                                            @if($remItem > 0)
                                                <form method="POST" action="{{ route('production.receipts.store', $dispatch) }}" class="mt-2.5 flex flex-wrap items-end gap-2.5">
                                                    @csrf
                                                    <input type="hidden" name="items[0][dispatch_item_id]" value="{{ $dispatchItem->id }}">
                                                    <div>
                                                        <label class="mb-1 block text-[11px] font-semibold text-slate-600">تاريخ الاستلام</label>
                                                        <input type="date" name="received_on" required value="{{ now(config('app.display_timezone'))->toDateString() }}" class="rounded-lg border border-slate-300 p-1.5 text-xs">
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-[11px] font-semibold text-slate-600">الكمية المستلمة</label>
                                                        <input type="number" name="items[0][quantity_received]" min="1" max="{{ $remItem }}" value="{{ $remItem }}" required class="w-24 rounded-lg border border-slate-300 p-1.5 text-xs text-center">
                                                    </div>
                                                    <div class="flex-1 min-w-[140px]">
                                                        <label class="mb-1 block text-[11px] font-semibold text-slate-600">ملاحظات الاستلام</label>
                                                        <input name="notes" maxlength="2000" placeholder="ملاحظة أو جودة" class="w-full rounded-lg border border-slate-300 p-1.5 text-xs">
                                                    </div>
                                                    <button class="inline-flex min-h-[38px] items-center justify-center rounded-lg border border-emerald-600 bg-emerald-600 px-3.5 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-700">
                                                        تسجيل الاستلام
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-[var(--brand-border)] bg-white p-8 text-center text-sm text-[var(--brand-muted)]">
                لا توجد سجلات إنتاج نشطة حالياً. اختر بنداً من الطلبات أعلاه لبدء المتابعة.
            </div>
        @endforelse

        <div>{{ $jobs->links() }}</div>
    </section>
</div>
@endsection
