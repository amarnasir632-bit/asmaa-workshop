@extends('layouts.app')
@section('title', 'نموذج تفصيل الترزي')
@section('content')
<style>@media print{.no-print,aside{display:none!important}body,html{background:#fff!important}main{padding:0!important}.print-report{max-width:none!important;padding:0!important}table{font-size:9pt!important}thead{display:table-header-group}}</style>
<div class="print-report mx-auto max-w-7xl space-y-5">
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-[var(--brand-gold)]">مشغل أسماء</p>
            <h1 class="text-2xl font-bold">نموذج تفصيل يدوي للترزي</h1>
            <p class="mt-1 text-sm text-slate-600">نموذج فارغ للطباعة والاستخدام اليدوي في الورشة ({{ $rows }} سطر)</p>
        </div>
        <div class="no-print flex gap-2">
            <a class="rounded-lg bg-[var(--brand-navy)] px-4 py-2 font-bold text-white" href="{{ route('reports.tailor.form.pdf', request()->query()) }}">تنزيل PDF</a>
            <button class="rounded-lg border border-[var(--brand-navy)] px-4 py-2 font-bold text-[var(--brand-navy)]" onclick="window.print()">طباعة</button>
        </div>
    </header>

    <form class="no-print flex flex-wrap items-end gap-3 rounded-xl border bg-white p-4">
        <label class="text-sm">عدد الأسطر
            <input type="number" name="rows" min="1" max="120" value="{{ $rows }}" class="mt-1 block rounded border-slate-300">
        </label>
        <button class="rounded bg-[var(--brand-navy)] px-4 py-2 text-white">تحديث</button>
    </form>

    <section class="rounded-xl border bg-white p-4 shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead>
                    <tr class="border-b bg-slate-50 text-slate-700">
                        <th class="p-2 w-10">#</th>
                        <th class="p-2">رقم الطلب</th>
                        <th class="p-2">اسم العميل</th>
                        <th class="p-2">المستفيد</th>
                        <th class="p-2">البيان</th>
                        <th class="p-2 w-16">العدد</th>
                        <th class="p-2 w-16">كتف</th>
                        <th class="p-2 w-16">صدر</th>
                        <th class="p-2 w-16">كم</th>
                        <th class="p-2 w-16">وسط</th>
                        <th class="p-2 w-16">طول</th>
                        <th class="p-2 w-28">التسليم</th>
                        <th class="p-2">ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    @for($i = 1; $i <= $rows; $i++)
                        <tr class="border-b" style="height: 38px;">
                            <td class="p-2 text-slate-400 text-center">{{ $i }}</td>
                            <td class="p-2">&nbsp;</td>
                            <td class="p-2">&nbsp;</td>
                            <td class="p-2">&nbsp;</td>
                            <td class="p-2">&nbsp;</td>
                            <td class="p-2">&nbsp;</td>
                            <td class="p-2">&nbsp;</td>
                            <td class="p-2">&nbsp;</td>
                            <td class="p-2">&nbsp;</td>
                            <td class="p-2">&nbsp;</td>
                            <td class="p-2">&nbsp;</td>
                            <td class="p-2">&nbsp;</td>
                            <td class="p-2">&nbsp;</td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
