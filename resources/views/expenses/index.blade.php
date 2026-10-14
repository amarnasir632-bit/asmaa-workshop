@extends('layouts.app')
@section('title', 'المصروفات')
@section('content')
<div class="mx-auto max-w-6xl space-y-5">
    <header><p class="text-sm font-semibold text-[var(--brand-gold)]">السجل المالي</p><h1 class="mt-1 text-2xl font-bold text-[var(--brand-navy)]">المصروفات</h1><p class="mt-2 text-sm text-[var(--brand-muted)]">كل مصروف سجل مستقل. لا يمكن تعديله أو حذفه مباشرة؛ أي تصحيح لاحق يحتاج سجلاً مرتبطاً مع سبب وتدقيق.</p></header>
    @if ($errors->any())<div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="rounded-xl border border-[var(--brand-border)] bg-white p-5"><h2 class="mb-4 text-lg font-bold">تسجيل مصروف</h2><form method="POST" action="{{ route('expenses.store') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@csrf
        <label class="text-sm">الفئة<select name="expense_category_id" required class="mt-1 w-full rounded-lg border p-2"><option value="">اختر</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label>
        <label class="text-sm">المبلغ (ج.س)<input name="amount" required inputmode="decimal" placeholder="0.00" class="mt-1 w-full rounded-lg border p-2"></label>
        <label class="text-sm">التاريخ<input type="date" name="spent_on" required max="{{ now(config('app.display_timezone'))->toDateString() }}" value="{{ now(config('app.display_timezone'))->toDateString() }}" class="mt-1 w-full rounded-lg border p-2"></label>
        <label class="text-sm lg:col-span-3">الوصف<input name="description" required maxlength="180" class="mt-1 w-full rounded-lg border p-2"></label>
        <label class="text-sm lg:col-span-3">ملاحظات (اختياري)<textarea name="notes" maxlength="2000" rows="2" class="mt-1 w-full rounded-lg border p-2"></textarea></label>
        <button class="rounded-lg bg-[var(--brand-navy)] px-4 py-2 font-semibold text-white sm:col-span-2 lg:col-span-3">حفظ المصروف</button>
    </form></section>
    <section class="overflow-x-auto rounded-xl border border-[var(--brand-border)] bg-white"><h2 class="border-b p-4 font-bold">{{ auth()->user()->can('finance.view') ? 'سجل المصروفات' : 'مصروفاتي المسجلة' }}</h2><table class="w-full min-w-[700px] text-right text-sm"><thead class="bg-slate-50"><tr><th class="p-3">التاريخ</th><th class="p-3">الفئة</th><th class="p-3">الوصف</th><th class="p-3">المبلغ</th><th class="p-3">المسجل</th></tr></thead><tbody class="divide-y">@forelse($expenses as $expense)<tr><td class="p-3">{{ $expense->spent_on->format('Y-m-d') }}</td><td class="p-3">{{ $expense->category->name }}</td><td class="p-3">{{ $expense->description }}@if($expense->notes)<p class="mt-1 text-xs text-[var(--brand-muted)]">{{ $expense->notes }}</p>@endif</td><td class="p-3 font-bold">{{ number_format((float)$expense->amount,2) }} ج.س</td><td class="p-3">{{ $expense->recorder->name }}</td></tr>@empty<tr><td colspan="5" class="p-7 text-center text-[var(--brand-muted)]">لا توجد مصروفات مسجلة.</td></tr>@endforelse</tbody></table><div class="border-t p-3">{{ $expenses->links() }}</div></section>
    @if(auth()->user()->can('finance.view'))<p class="text-xs text-[var(--brand-muted)]">التقارير والمجاميع المالية لا تظهر هنا إلى حين تثبيت تعريف الفترات والمبيعات والاستردادات الوارد في DEC-007.</p>@endif
</div>
@endsection
