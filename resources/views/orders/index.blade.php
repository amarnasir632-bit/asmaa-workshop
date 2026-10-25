@extends('layouts.app')
@section('title', 'الطلبات')
@section('content')
<div class="mx-auto max-w-7xl space-y-5">
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-[var(--brand-gold)]">العملاء والإنتاج</p>
            <h1 class="mt-1 text-2xl font-bold text-[var(--brand-navy)]">الطلبات وعقود المدارس</h1>
            <p class="mt-2 text-sm text-[var(--brand-muted)]">متابعة طلبات التفصيل الفردية وعقود المدارس الجماعية والمدفوع والمتبقي.</p>
        </div>
        @can('orders.create')
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('orders.create') }}" class="rounded-lg bg-[var(--brand-navy)] px-4 py-2.5 font-semibold text-white shadow hover:opacity-95">
                    إنشاء طلب فردي
                </a>
                <a href="{{ route('school-contracts.create') }}" class="rounded-lg border-2 border-[var(--brand-navy)] bg-white px-4 py-2.5 font-semibold text-[var(--brand-navy)] hover:bg-slate-50">
                    + طلب مدرسة جماعي
                </a>
            </div>
        @endcan
    </header>

    <!-- شريط البحث والفلترة -->
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[var(--brand-border)] bg-white p-4">
        <form method="GET" class="flex flex-1 flex-wrap items-center gap-2">
            <input type="hidden" name="type_filter" value="{{ $filterType ?? '' }}">
            <input id="q" name="q" value="{{ $search }}" placeholder="ابحث برقم الطلب، اسم المدرسة، أو ولي الأمر..." class="min-w-0 flex-1 rounded-lg border p-2 text-sm">
            <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                بحث
            </button>
            @if($search || ($filterType ?? ''))
                <a href="{{ route('orders.index') }}" class="rounded-lg border px-3 py-2 text-xs text-slate-600 hover:bg-slate-50">
                    إلغاء التصفية
                </a>
            @endif
        </form>

        <div class="flex items-center gap-1.5 text-xs">
            <span class="text-slate-500">التصنيف:</span>
            <a href="{{ route('orders.index', array_filter(['q' => $search])) }}" class="rounded-md px-2.5 py-1.5 font-medium {{ empty($filterType) ? 'bg-[var(--brand-navy)] text-white' : 'border hover:bg-slate-50' }}">
                الكل
            </a>
            <a href="{{ route('orders.index', array_filter(['q' => $search, 'type_filter' => 'school_contract'])) }}" class="rounded-md px-2.5 py-1.5 font-medium {{ ($filterType ?? '') === 'school_contract' ? 'bg-[var(--brand-navy)] text-white' : 'border hover:bg-slate-50' }}">
                طلبات المدارس الجماعية
            </a>
            <a href="{{ route('orders.index', array_filter(['q' => $search, 'type_filter' => 'individual'])) }}" class="rounded-md px-2.5 py-1.5 font-medium {{ ($filterType ?? '') === 'individual' ? 'bg-[var(--brand-navy)] text-white' : 'border hover:bg-slate-50' }}">
                الطلبات الفردية
            </a>
        </div>
    </div>

    <!-- جدول الطلبات -->
    <section class="overflow-x-auto rounded-xl border border-[var(--brand-border)] bg-white shadow-sm">
        <table class="w-full min-w-[850px] text-right text-sm">
            <thead class="bg-slate-50 text-xs font-bold text-slate-700">
                <tr>
                    <th class="p-3">رقم الطلب</th>
                    <th class="p-3">العميل / المدرسة</th>
                    <th class="p-3">النوع والمراحل</th>
                    <th class="p-3 text-center">كم لبسة</th>
                    <th class="p-3">قيمة الطلب</th>
                    <th class="p-3">المدفوع / المتبقي</th>
                    <th class="p-3">الحالة التشغيلية</th>
                    <th class="p-3">تاريخ الطلب</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($orders as $order)
                    @php($isSchool = (bool) $order->schoolContract)
                    @php($totalGarments = $order->items->sum('quantity'))
                    @php($summary = $order->financial_summary)
                    <tr class="hover:bg-slate-50/60 {{ $isSchool ? 'bg-amber-50/20' : '' }}">
                        <td class="p-3">
                            <a class="font-bold text-[var(--brand-navy)] underline hover:opacity-80" href="{{ route('orders.show', $order) }}">
                                {{ $order->number }}
                            </a>
                        </td>
                        <td class="p-3">
                            @if($isSchool)
                                <span class="font-bold text-slate-900">{{ $order->schoolContract->school->name }}</span>
                                <span class="mr-1 inline-block rounded bg-indigo-100 px-1.5 py-0.5 text-[11px] font-semibold text-indigo-800">مدرسة</span>
                            @else
                                <span class="font-medium text-slate-800">{{ $order->customer_name_snapshot ?: $order->guardian?->name }}</span>
                            @endif
                        </td>
                        <td class="p-3 text-xs">
                            @if($isSchool)
                                @php($gradesCount = $order->schoolContract->beneficiaries->pluck('grade_snapshot')->filter()->unique()->count())
                                <span class="text-indigo-900 font-semibold">عقد جماعي ({{ $gradesCount ?: 1 }} مراحل)</span>
                            @else
                                <span>{{ ['custom'=>'تفصيل','ready'=>'جاهز','alteration'=>'تعديل'][$order->type] }}</span>
                            @endif
                        </td>
                        <td class="p-3 text-center">
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-800">
                                {{ $totalGarments }} لبسة
                            </span>
                        </td>
                        <td class="p-3 font-semibold">
                            {{ number_format((float)$order->approved_total, 2) }} ج.س
                        </td>
                        <td class="p-3 text-xs">
                            @if($summary)
                                @if($summary['due'] == 0 && $order->operational_status !== 'cancelled')
                                    <span class="inline-block rounded bg-emerald-100 px-2 py-0.5 font-bold text-emerald-800">خالص بالكامل</span>
                                @else
                                    <div class="space-y-0.5">
                                        <p class="text-emerald-700">مدفوع: <b>{{ number_format($summary['net_paid'] / 100, 2) }}</b></p>
                                        <p class="text-rose-700 font-bold">متبقي: {{ number_format($summary['due'] / 100, 2) }} ج.س</p>
                                    </div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="p-3">
                            <span class="inline-block rounded-md px-2 py-1 text-xs font-semibold {{ $order->operational_status === 'delivered' ? 'bg-emerald-100 text-emerald-800' : ($order->operational_status === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-800') }}">
                                {{ ['new'=>'جديد','in_progress'=>'قيد التنفيذ','partially_received'=>'استلام جزئي','fully_received'=>'مستلم بالكامل','ready'=>'جاهز','delivered'=>'مسلّم','cancelled'=>'ملغي'][$order->operational_status] }}
                            </span>
                        </td>
                        <td class="p-3 text-xs text-[var(--brand-muted)]">
                            {{ $order->created_on->format('Y-m-d') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="p-8 text-center text-[var(--brand-muted)]">
                            لا توجد طلبات مطابقة للبحث.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t p-3">
            {{ $orders->links() }}
        </div>
    </section>
</div>
@endsection
