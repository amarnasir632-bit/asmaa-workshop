@extends('layouts.app')
@section('title', 'المخزون')
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <header><p class="text-sm font-semibold text-[var(--brand-gold)]">المخزون الجاهز</p><h1 class="mt-1 text-2xl font-bold text-[var(--brand-navy)]">المخزون والحركات</h1><p class="mt-2 text-sm text-[var(--brand-muted)]">الرصيد لا يتغير إلا بحركة مسجلة. الأرصدة الافتتاحية وتصحيحات الجرد لا تنفذ قبل اعتماد المدير.</p></header>
    @if ($errors->any())<div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @can('inventory.manage')
    <div class="grid gap-5 xl:grid-cols-2">
        <section class="rounded-xl border border-[var(--brand-border)] bg-white p-5"><h2 class="mb-2 text-lg font-bold">إنشاء جرد افتتاحي</h2><p class="mb-4 text-xs text-[var(--brand-muted)]">يسجل كمسودة. بعد المراجعة يعتمد المدير الدفعة مرة واحدة؛ لا تستخدم الرصيد الافتتاحي لإجراء جرد لاحق.</p>
            <form method="POST" action="{{ route('inventory.opening-batches.store') }}" class="space-y-3">@csrf
                <div id="opening-entries" data-next-index="1"><div class="grid gap-2 sm:grid-cols-3" data-opening-row><label class="text-xs">الصنف<select name="entries[0][inventory_variant_id]" required class="mt-1 w-full rounded border p-2"><option value="">اختر</option>@foreach($activeVariants as $variant)<option value="{{ $variant->id }}">{{ $variant->garmentType->name }}{{ $variant->school ? ' · '.$variant->school->name : '' }}{{ $variant->size ? ' · '.$variant->size : '' }}{{ $variant->color ? ' · '.$variant->color : '' }}</option>@endforeach</select></label><label class="text-xs">الكمية الفعلية<input type="number" name="entries[0][quantity_counted]" min="0" max="1000000" required class="mt-1 w-full rounded border p-2"></label><label class="text-xs">سبب فرق (اختياري)<input name="entries[0][difference_reason]" maxlength="500" class="mt-1 w-full rounded border p-2"></label></div></div>
                <template id="opening-template"><div class="grid gap-2 border-t pt-3 sm:grid-cols-3" data-opening-row><label class="text-xs">الصنف<select name="entries[__INDEX__][inventory_variant_id]" required class="mt-1 w-full rounded border p-2"><option value="">اختر</option>@foreach($activeVariants as $variant)<option value="{{ $variant->id }}">{{ $variant->garmentType->name }}{{ $variant->school ? ' · '.$variant->school->name : '' }}{{ $variant->size ? ' · '.$variant->size : '' }}{{ $variant->color ? ' · '.$variant->color : '' }}</option>@endforeach</select></label><label class="text-xs">الكمية الفعلية<input type="number" name="entries[__INDEX__][quantity_counted]" min="0" max="1000000" required class="mt-1 w-full rounded border p-2"></label><label class="text-xs">سبب فرق<input name="entries[__INDEX__][difference_reason]" maxlength="500" class="mt-1 w-full rounded border p-2"></label></div></template>
                <button type="button" data-add-row="opening" class="rounded-lg border px-3 py-2 text-sm">إضافة صنف للجرد</button><label class="block text-sm">تاريخ الجرد<input type="date" name="counted_on" value="{{ now(config('app.display_timezone'))->toDateString() }}" required class="mt-1 w-full rounded-lg border p-2"></label><label class="block text-sm">ملاحظة الدفعة<input name="notes" maxlength="2000" class="mt-1 w-full rounded-lg border p-2"></label><button class="rounded-lg bg-[var(--brand-navy)] px-4 py-2 font-semibold text-white">حفظ مسودة الجرد</button>
            </form>
        </section>
        <section class="rounded-xl border border-[var(--brand-border)] bg-white p-5"><h2 class="mb-2 text-lg font-bold">طلب تصحيح مخزون</h2><p class="mb-4 text-xs text-[var(--brand-muted)]">أدخل فرق الجرد الموجب أو السالب وسببه. اعتماد المدير يرفض تلقائياً أي حركة تجعل الرصيد سالباً.</p>
            <form method="POST" action="{{ route('inventory.adjustments.store') }}" class="space-y-3">@csrf
                <label class="block text-sm">السبب العام<input name="reason" required maxlength="1000" class="mt-1 w-full rounded-lg border p-2"></label>
                <div id="adjustment-entries" data-next-index="1"><div class="grid gap-2 sm:grid-cols-3" data-adjustment-row><label class="text-xs">الصنف<select name="items[0][inventory_variant_id]" required class="mt-1 w-full rounded border p-2"><option value="">اختر</option>@foreach($variants as $variant)<option value="{{ $variant->id }}">{{ $variant->garmentType->name }}{{ $variant->school ? ' · '.$variant->school->name : '' }}{{ $variant->size ? ' · '.$variant->size : '' }}</option>@endforeach</select></label><label class="text-xs">فرق الكمية (+/-)<input type="number" name="items[0][quantity_delta]" min="-1000000" max="1000000" required class="mt-1 w-full rounded border p-2"></label><label class="text-xs">سبب خاص بالصنف<input name="items[0][reason]" maxlength="500" class="mt-1 w-full rounded border p-2"></label></div></div>
                <template id="adjustment-template"><div class="grid gap-2 border-t pt-3 sm:grid-cols-3" data-adjustment-row><label class="text-xs">الصنف<select name="items[__INDEX__][inventory_variant_id]" required class="mt-1 w-full rounded border p-2"><option value="">اختر</option>@foreach($variants as $variant)<option value="{{ $variant->id }}">{{ $variant->garmentType->name }}{{ $variant->school ? ' · '.$variant->school->name : '' }}{{ $variant->size ? ' · '.$variant->size : '' }}</option>@endforeach</select></label><label class="text-xs">فرق الكمية (+/-)<input type="number" name="items[__INDEX__][quantity_delta]" min="-1000000" max="1000000" required class="mt-1 w-full rounded border p-2"></label><label class="text-xs">سبب الصنف<input name="items[__INDEX__][reason]" maxlength="500" class="mt-1 w-full rounded border p-2"></label></div></template>
                <button type="button" data-add-row="adjustment" class="rounded-lg border px-3 py-2 text-sm">إضافة صنف للتصحيح</button><button class="block rounded-lg bg-[var(--brand-navy)] px-4 py-2 font-semibold text-white">حفظ للتدقيق والاعتماد</button>
            </form>
        </section>
    </div>
    @endcan

    <!-- الأرصدة الحالية -->
    <section class="rounded-xl border border-[var(--brand-border)] bg-white shadow-xs overflow-hidden">
        <h2 class="border-b border-slate-200 p-4 font-bold text-slate-900 text-base sm:text-lg">الأرصدة الحالية في المخزن</h2>

        <!-- جدول الكمبيوتر (hidden md:block) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full min-w-[650px] text-right text-sm">
                <thead class="bg-slate-100 text-xs font-bold text-slate-800 border-b border-slate-300">
                    <tr>
                        <th class="p-3">الصنف</th>
                        <th class="p-3">المدرسة والمرحلة</th>
                        <th class="p-3">المقاس / اللون</th>
                        <th class="p-3 text-center">الرصيد المتاح</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($variants as $variant)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="p-3 font-semibold text-slate-900">
                                {{ $variant->garmentType->name }}
                                @unless($variant->is_active)
                                    <span class="text-xs text-rose-600 font-normal mr-1">(موقوف للبيع الجديد)</span>
                                @endunless
                            </td>
                            <td class="p-3 text-xs text-slate-600">
                                {{ $variant->school?->name ?? 'عام / بدون مدرسة' }}{{ $variant->grade ? ' · '.$variant->grade->name : '' }}
                            </td>
                            <td class="p-3 text-xs text-slate-700">
                                {{ $variant->size ?? '—' }} / {{ $variant->color ?? '—' }}
                            </td>
                            <td class="p-3 text-center">
                                @php($qty = $variant->balance?->quantity_available ?? 0)
                                <span class="inline-block rounded-full px-3 py-1 text-xs font-bold {{ $qty > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $qty }} قطعة
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-8 text-center text-slate-500">لا توجد أصناف مسجلة.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- بطاقات الهاتف للأرصدة (md:hidden) -->
        <div class="grid grid-cols-1 gap-3 p-3 md:hidden">
            @forelse($variants as $variant)
                @php($qty = $variant->balance?->quantity_available ?? 0)
                <article class="rounded-xl border border-slate-200 bg-white p-3.5 shadow-2xs">
                    <div class="flex items-start justify-between gap-2 border-b border-slate-100 pb-2">
                        <div>
                            <p class="font-bold text-sm text-slate-900">{{ $variant->garmentType->name }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $variant->school?->name ?? 'صنف عام' }}{{ $variant->grade ? ' · '.$variant->grade->name : '' }}</p>
                        </div>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $qty > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                            {{ $qty }} متاح
                        </span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-xs text-slate-600">
                        <span>المقاس: <b>{{ $variant->size ?? '—' }}</b></span>
                        <span>اللون: <b>{{ $variant->color ?? '—' }}</b></span>
                        @if($variant->sku)
                            <span class="text-slate-400 text-[11px]">{{ $variant->sku }}</span>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-slate-500">
                    لا توجد أصناف مسجلة.
                </div>
            @endforelse
        </div>

        <div class="border-t border-slate-200 p-3">
            {{ $variants->links() }}
        </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-2">
        <section class="space-y-3">
            <h2 class="font-bold text-slate-900 text-base">دفعات الجرد الافتتاحي</h2>
            @forelse($openingBatches as $batch)
                <article class="rounded-xl border border-[var(--brand-border)] bg-white p-4 shadow-2xs">
                    <div class="flex justify-between items-center gap-2">
                        <b class="text-slate-900">{{ $batch->reference }}</b>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $batch->status === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ $batch->status === 'approved' ? 'معتمد' : 'مسودة بانتظار الاعتماد' }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs sm:text-sm text-[var(--brand-muted)]">{{ $batch->counted_on->format('Y-m-d') }} · {{ $batch->entries->count() }} صنف</p>
                    @if($batch->status==='draft' && auth()->user()->can('inventory.manage'))
                        <form method="POST" action="{{ route('inventory.opening-batches.approve',$batch) }}" class="mt-3">
                            @csrf
                            <button class="w-full sm:w-auto rounded-lg bg-[var(--brand-navy)] px-4 py-2 text-sm font-semibold text-white min-h-[44px]">اعتماد الجرد</button>
                        </form>
                    @endif
                </article>
            @empty
                <p class="rounded-xl bg-white p-4 text-sm text-[var(--brand-muted)] border border-slate-200">لا توجد دفعات جرد.</p>
            @endforelse
        </section>

        <section class="space-y-3">
            <h2 class="font-bold text-slate-900 text-base">تصحيحات المخزون</h2>
            @forelse($adjustments as $adjustment)
                <article class="rounded-xl border border-[var(--brand-border)] bg-white p-4 shadow-2xs">
                    <div class="flex justify-between items-center gap-2">
                        <b class="text-slate-900">{{ $adjustment->reference }}</b>
                        @php($adjStatus = [
                            'pending' => 'bg-amber-100 text-amber-800',
                            'approved' => 'bg-emerald-100 text-emerald-800',
                            'rejected' => 'bg-rose-100 text-rose-800'
                        ][$adjustment->status] ?? 'bg-slate-100')
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $adjStatus }}">
                            {{ ['pending'=>'بانتظار الاعتماد','approved'=>'معتمد','rejected'=>'مرفوض'][$adjustment->status] ?? $adjustment->status }}
                        </span>
                    </div>
                    <p class="mt-1.5 text-xs sm:text-sm font-medium text-slate-800">{{ $adjustment->reason }}</p>
                    <ul class="mt-2 text-xs text-[var(--brand-muted)] divide-y divide-slate-100 bg-slate-50 p-2 rounded-lg">
                        @foreach($adjustment->items as $item)
                            <li class="py-1 flex justify-between">
                                <span>{{ $item->variant->garmentType->name }}: {{ $item->reason }}</span>
                                <b class="{{ $item->quantity_delta > 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $item->quantity_delta > 0 ? '+' : '' }}{{ $item->quantity_delta }}</b>
                            </li>
                        @endforeach
                    </ul>
                    @if($adjustment->status==='pending' && auth()->user()->can('inventory.manage'))
                        <div class="mt-3 flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('inventory.adjustments.approve',$adjustment) }}" class="flex-1">
                                @csrf
                                <button class="w-full rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-800 min-h-[44px]">اعتماد وتطبيق</button>
                            </form>
                            <form method="POST" action="{{ route('inventory.adjustments.reject',$adjustment) }}" class="flex-1 flex gap-1">
                                @csrf
                                <input name="rejection_reason" required placeholder="سبب الرفض..." class="w-full rounded-lg border px-2 py-1 text-xs">
                                <button class="rounded-lg bg-rose-700 px-3 py-2 text-xs font-bold text-white min-h-[44px]">رفض</button>
                            </form>
                        </div>
                    @endif
                </article>
            @empty
                <p class="rounded-xl bg-white p-4 text-sm text-[var(--brand-muted)] border border-slate-200">لا توجد تصحيحات.</p>
            @endforelse
        </section>
    </div>

    <!-- آخر حركات المخزون -->
    <section class="rounded-xl border border-[var(--brand-border)] bg-white shadow-xs overflow-hidden">
        <h2 class="border-b border-slate-200 p-4 font-bold text-slate-900 text-base sm:text-lg">آخر حركات المخزون</h2>

        <!-- جدول الكمبيوتر (hidden md:block) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full min-w-[650px] text-right text-sm">
                <thead class="bg-slate-100 text-xs font-bold text-slate-800 border-b border-slate-300">
                    <tr>
                        <th class="p-3">التاريخ والوقت</th>
                        <th class="p-3">الصنف</th>
                        <th class="p-3">نوع الحركة</th>
                        <th class="p-3 text-center">الكمية</th>
                        <th class="p-3">السبب أو البيان</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($movements as $movement)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="p-3 text-xs text-slate-500">{{ $movement->occurred_at->timezone(config('app.display_timezone'))->format('Y-m-d H:i') }}</td>
                            <td class="p-3 font-semibold text-slate-900">{{ $movement->variant->garmentType->name }}{{ $movement->variant->size ? ' · '.$movement->variant->size : '' }}</td>
                            <td class="p-3 text-xs">
                                <span class="rounded bg-slate-100 px-2 py-0.5 font-semibold text-slate-800">
                                    {{ ['opening'=>'رصيد افتتاحي','ready_sale'=>'بيع جاهز من المخزون','customer_return'=>'مرتجع من عميل','adjustment_in'=>'تصحيح تسوية زيادة','adjustment_out'=>'تصحيح تسوية نقص','manual_in'=>'إدخال يدوي','manual_out'=>'صرف يدوي'][$movement->movement_type] ?? $movement->movement_type }}
                                </span>
                            </td>
                            <td class="p-3 text-center font-bold {{ $movement->quantity_change > 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $movement->quantity_change > 0 ? '+' : '' }}{{ $movement->quantity_change }}
                            </td>
                            <td class="p-3 text-xs text-slate-600">{{ $movement->reason }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-8 text-center text-slate-500">لا توجد حركات مخزنية مسجلة بعد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- بطاقات الهاتف لآخر الحركات (md:hidden) -->
        <div class="grid grid-cols-1 gap-3 p-3 md:hidden">
            @forelse($movements as $movement)
                <article class="rounded-xl border border-slate-200 bg-white p-3.5 shadow-2xs">
                    <div class="flex items-start justify-between gap-2 border-b border-slate-100 pb-2">
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-800">
                            {{ ['opening'=>'رصيد افتتاحي','ready_sale'=>'بيع جاهز','customer_return'=>'مرتجع عميل','adjustment_in'=>'تسوية زيادة','adjustment_out'=>'تسوية نقص'][$movement->movement_type] ?? $movement->movement_type }}
                        </span>
                        <time class="text-[11px] text-slate-400">{{ $movement->occurred_at->timezone(config('app.display_timezone'))->format('Y-m-d H:i') }}</time>
                    </div>
                    <div class="mt-2 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-bold text-slate-900">{{ $movement->variant->garmentType->name }}</p>
                            <p class="text-xs text-slate-500">{{ $movement->reason }}</p>
                        </div>
                        <span class="text-base font-extrabold {{ $movement->quantity_change > 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                            {{ $movement->quantity_change > 0 ? '+' : '' }}{{ $movement->quantity_change }}
                        </span>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-slate-500">
                    لا توجد حركات مخزنية مسجلة بعد.
                </div>
            @endforelse
        </div>
    </section>
</div>
@endsection

