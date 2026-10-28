@extends('layouts.app')
@section('title', 'المصروفات')
@section('content')
<div class="mx-auto max-w-6xl space-y-5">
    <header>
        <p class="text-xs sm:text-sm font-semibold text-[var(--brand-gold)]">السجل المالي</p>
        <h1 class="mt-0.5 text-xl sm:text-2xl font-bold text-[var(--brand-navy)]">المصروفات</h1>
        <p class="mt-1 text-xs sm:text-sm text-[var(--brand-muted)]">تسجيل ومتابعة كافة المصروفات التشغيلية والنثرية مع إمكانية تصحيح المبالغ عبر قيود تسوية عكسية.</p>
    </header>

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 font-medium">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="rounded-xl border border-[var(--brand-border)] bg-white p-4 sm:p-5 shadow-xs">
        <h2 class="mb-4 text-base sm:text-lg font-bold text-slate-900">تسجيل مصروف جديد</h2>
        <form method="POST" action="{{ route('expenses.store') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @csrf
            <label class="text-xs sm:text-sm font-semibold text-slate-700">الفئة <span class="text-red-600">*</span>
                <select name="expense_category_id" required class="mt-1 w-full rounded-lg border border-slate-300 p-2.5 text-sm">
                    <option value="">اختر الفئة</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs sm:text-sm font-semibold text-slate-700">المبلغ (ج.س) <span class="text-red-600">*</span>
                <input name="amount" required inputmode="decimal" placeholder="0.00" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5 text-sm">
            </label>
            <label class="text-xs sm:text-sm font-semibold text-slate-700">التاريخ <span class="text-red-600">*</span>
                <input type="date" name="spent_on" required max="{{ now(config('app.display_timezone'))->toDateString() }}" value="{{ now(config('app.display_timezone'))->toDateString() }}" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5 text-sm">
            </label>
            <label class="text-xs sm:text-sm font-semibold text-slate-700 lg:col-span-3">الوصف <span class="text-red-600">*</span>
                <input name="description" required maxlength="180" placeholder="بيان المصروف..." class="mt-1 w-full rounded-lg border border-slate-300 p-2.5 text-sm">
            </label>
            <label class="text-xs sm:text-sm font-semibold text-slate-700 lg:col-span-3">ملاحظات (اختياري)
                <textarea name="notes" maxlength="2000" rows="2" placeholder="ملاحظات توضيحية إضافية..." class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"></textarea>
            </label>
            <button class="rounded-xl bg-[var(--brand-navy)] px-5 py-3 font-semibold text-white shadow-xs hover:opacity-95 transition sm:col-span-2 lg:col-span-3 min-h-[48px] active:scale-[0.99]">
                حفظ المصروف
            </button>
        </form>
    </section>

    <!-- سجل المصروفات -->
    <section class="rounded-xl border border-[var(--brand-border)] bg-white shadow-xs overflow-hidden">
        <h2 class="border-b border-slate-200 p-4 font-bold text-slate-900 text-base sm:text-lg">
            {{ auth()->user()->can('finance.view') ? 'سجل المصروفات' : 'مصروفاتي المسجلة' }}
        </h2>

        <!-- جدول الكمبيوتر (hidden md:block) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full min-w-[700px] text-right text-sm">
                <thead class="bg-slate-100 text-xs font-bold text-slate-800 border-b border-slate-300">
                    <tr>
                        <th class="p-3">التاريخ</th>
                        <th class="p-3">الفئة</th>
                        <th class="p-3">الوصف والملاحظات</th>
                        <th class="p-3">المبلغ</th>
                        <th class="p-3">المسجل</th>
                        <th class="p-3 text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($expenses as $expense)
                        <tr class="hover:bg-slate-50/70 transition {{ (float)$expense->amount < 0 ? 'bg-amber-50/30' : '' }}">
                            <td class="p-3 text-xs text-slate-600">{{ $expense->spent_on->format('Y-m-d') }}</td>
                            <td class="p-3">
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-800">
                                    {{ $expense->category->name }}
                                </span>
                            </td>
                            <td class="p-3">
                                <p class="font-medium text-slate-900">{{ $expense->description }}</p>
                                @if($expense->notes)
                                    <p class="mt-0.5 text-xs text-[var(--brand-muted)]">{{ $expense->notes }}</p>
                                @endif
                            </td>
                            <td class="p-3 font-bold {{ (float)$expense->amount < 0 ? 'text-emerald-700' : 'text-slate-900' }}">
                                {{ number_format((float)$expense->amount, 2) }} ج.س
                            </td>
                            <td class="p-3 text-xs text-slate-500">{{ $expense->recorder->name }}</td>
                            <td class="p-3 text-center">
                                @if((float)$expense->amount > 0 && auth()->user()->can('expenses.record'))
                                    <details class="relative inline-block text-right">
                                        <summary class="rounded-lg border border-rose-300 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 cursor-pointer transition">
                                            إلغاء / تصحيح المبلغ
                                        </summary>
                                        <div class="absolute left-0 mt-1 w-64 rounded-xl border border-slate-200 bg-white p-3 shadow-xl z-20">
                                            <form method="POST" action="{{ route('expenses.reverse', $expense) }}" class="space-y-2">
                                                @csrf
                                                <p class="text-xs font-bold text-slate-800">قيد تسوية عكسي للمصروف #{{ $expense->id }}</p>
                                                <input name="reason" required placeholder="سبب الإلغاء أو التصحيح..." class="w-full text-xs p-2 border rounded-lg focus:outline-hidden">
                                                <button class="w-full rounded-lg bg-rose-700 text-white text-xs py-2 font-bold hover:bg-rose-800 transition">
                                                    تأكيد القيد العكسي
                                                </button>
                                            </form>
                                        </div>
                                    </details>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-[var(--brand-muted)]">لا توجد مصروفات مسجلة.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- بطاقات الهاتف لسجل المصروفات (md:hidden) -->
        <div class="grid grid-cols-1 gap-3 p-3 md:hidden">
            @forelse($expenses as $expense)
                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs {{ (float)$expense->amount < 0 ? 'border-amber-200 bg-amber-50/20' : '' }}">
                    <div class="flex items-start justify-between gap-2 border-b border-slate-100 pb-2.5">
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-800">
                            {{ $expense->category->name }}
                        </span>
                        <time class="text-xs text-slate-500">{{ $expense->spent_on->format('Y-m-d') }}</time>
                    </div>

                    <div class="my-2.5">
                        <p class="text-sm font-bold text-slate-900">{{ $expense->description }}</p>
                        @if($expense->notes)
                            <p class="mt-1 text-xs text-slate-500 bg-slate-50 p-1.5 rounded">{{ $expense->notes }}</p>
                        @endif
                        <div class="mt-2 flex items-center justify-between text-xs">
                            <span class="text-slate-500">المسجل: {{ $expense->recorder->name }}</span>
                            <span class="text-base font-extrabold {{ (float)$expense->amount < 0 ? 'text-emerald-700' : 'text-slate-900' }}">
                                {{ number_format((float)$expense->amount, 2) }} ج.س
                            </span>
                        </div>
                    </div>

                    @if((float)$expense->amount > 0 && auth()->user()->can('expenses.record'))
                        <div class="pt-2 border-t border-slate-100">
                            <details class="w-full">
                                <summary class="w-full flex items-center justify-center rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100 cursor-pointer transition min-h-[44px]">
                                    إلغاء / تصحيح المبلغ
                                </summary>
                                <form method="POST" action="{{ route('expenses.reverse', $expense) }}" class="mt-2 space-y-2 p-3 bg-slate-50 border rounded-xl">
                                    @csrf
                                    <p class="text-xs font-bold text-slate-800">إنشاء قيد تسوية عكسي لتصحيح المصروف</p>
                                    <input name="reason" required placeholder="سبب الإلغاء أو التصحيح..." class="w-full text-xs p-2.5 border rounded-lg bg-white">
                                    <button class="w-full rounded-lg bg-rose-700 text-white text-xs py-2.5 font-bold hover:bg-rose-800 transition min-h-[40px]">
                                        تأكيد قيد التسوية العكسي
                                    </button>
                                </form>
                            </details>
                        </div>
                    @endif
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-slate-500">
                    لا توجد مصروفات مسجلة.
                </div>
            @endforelse
        </div>

        <div class="border-t border-slate-200 p-3">
            {{ $expenses->links() }}
        </div>
    </section>
</div>
@endsection

