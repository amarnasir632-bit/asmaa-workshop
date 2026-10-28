@extends('layouts.app')
@section('title', 'تقرير الترزي')
@section('content')
<style>@media print{.no-print,aside{display:none!important}body,html{background:#fff!important}main{padding:0!important}.print-report{max-width:none!important;padding:0!important}table{font-size:9pt!important}thead{display:table-header-group}}</style>
<div class="print-report mx-auto max-w-7xl space-y-5">
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-[var(--brand-gold)]">مشغل أسماء</p>
            <h1 class="text-2xl font-bold">تقرير تفصيل الترزي</h1>
            <p class="mt-1 text-sm text-slate-600">الفترة من {{ $from }} إلى {{ $to }} ({{ $ordersCount }} طلب · {{ $quantityTotal }} قطعة)</p>
        </div>
        <div class="no-print flex gap-2">
            <a class="rounded-lg bg-[var(--brand-navy)] px-4 py-2 font-bold text-white" href="{{ route('reports.tailor.pdf', request()->query()) }}">تنزيل PDF</a>
            <a class="rounded-lg border border-[var(--brand-navy)] px-4 py-2 font-bold text-[var(--brand-navy)]" href="{{ route('reports.tailor.form') }}">نموذج ورقي فارغ</a>
            <button class="rounded-lg border border-slate-300 px-4 py-2 font-bold text-slate-700" onclick="window.print()">طباعة</button>
        </div>
    </header>

    <form class="no-print flex flex-wrap items-end gap-3 rounded-xl border bg-white p-4">
        <label class="text-sm">من
            <input type="date" name="from" value="{{ $from }}" class="mt-1 block rounded border-slate-300">
        </label>
        <label class="text-sm">إلى
            <input type="date" name="to" value="{{ $to }}" class="mt-1 block rounded border-slate-300">
        </label>
        <button class="rounded bg-[var(--brand-navy)] px-4 py-2 text-white">تحديث</button>
    </form>

    <section class="rounded-xl border bg-white p-4 shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead>
                    <tr class="border-b bg-slate-50 text-slate-700">
                        <th class="p-2">#</th>
                        <th class="p-2">رقم الطلب</th>
                        <th class="p-2">العميل / ولي الأمر</th>
                        <th class="p-2">المستفيد / الطفل</th>
                        <th class="p-2">البيان</th>
                        <th class="p-2">العدد</th>
                        <th class="p-2">كتف</th>
                        <th class="p-2">صدر</th>
                        <th class="p-2">كم</th>
                        <th class="p-2">وسط</th>
                        <th class="p-2">طول</th>
                        <th class="p-2">تاريخ التسليم</th>
                        <th class="p-2">ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr class="border-b hover:bg-slate-50">
                            <td class="p-2 text-slate-500">{{ $row['serial'] }}</td>
                            <td class="p-2 font-medium">{{ $row['number'] }}</td>
                            <td class="p-2">{{ $row['customer'] }}</td>
                            <td class="p-2">{{ $row['beneficiary'] ?? '—' }}</td>
                            <td class="p-2">{{ $row['description'] }}</td>
                            <td class="p-2 font-bold">{{ $row['quantity'] }}</td>
                            <td class="p-2">{{ $row['shoulder'] ?? '—' }}</td>
                            <td class="p-2">{{ $row['chest'] ?? '—' }}</td>
                            <td class="p-2">{{ $row['sleeve'] ?? '—' }}</td>
                            <td class="p-2">{{ $row['waist'] ?? '—' }}</td>
                            <td class="p-2">{{ $row['length'] ?? '—' }}</td>
                            <td class="p-2 text-xs">{{ $row['due_on'] ?? '—' }}</td>
                            <td class="p-2 text-xs text-slate-600">{{ $row['notes'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="p-4 text-center text-slate-500">لا توجد طلبات تفصيل في الفترة المحددة.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
