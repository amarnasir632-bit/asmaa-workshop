@extends('layouts.app')

@section('title', 'الرئيسية')

@section('content')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs sm:text-sm font-semibold text-[var(--brand-gold)]">نظرة عامة</p>
            <h1 class="mt-0.5 text-xl sm:text-2xl font-bold text-[var(--brand-navy)]">لوحة التحكم</h1>
            <p class="mt-1 text-xs sm:text-sm text-[var(--brand-muted)]">ملخص مباشر ومتابعة سريعة لأعمال المشغل اليومية.</p>
        </div>
    </div>

    <!-- أزرار الإجراءات السريعة (Quick Actions) -->
    <section aria-label="إجراءات سريعة" class="mb-6 grid grid-cols-1 sm:grid-cols-3 gap-3">
        @can('orders.create')
            <a href="{{ route('orders.create') }}" class="flex items-center justify-center gap-2 rounded-xl bg-[var(--brand-navy)] px-4 py-3.5 text-base font-bold text-white shadow-sm hover:opacity-95 transition min-h-[48px] active:scale-[0.98]">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                + طلب جديد
            </a>
        @endcan
        @can('payments.record')
            <a href="{{ route('payments.index') }}" class="flex items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-3.5 text-base font-bold text-white shadow-sm hover:bg-emerald-800 transition min-h-[48px] active:scale-[0.98]">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                + تسجيل دفعة
            </a>
        @endcan
        @can('customers.create')
            <a href="{{ route('guardians.create') }}" class="flex items-center justify-center gap-2 rounded-xl bg-[var(--brand-gold)] px-4 py-3.5 text-base font-bold text-slate-900 shadow-sm hover:opacity-95 transition min-h-[48px] active:scale-[0.98]">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                + إضافة ولي أمر
            </a>
        @endcan
    </section>

    <!-- شبكة الكروت الإحصائية (عمودين على الهاتف و 4 على الكمبيوتر) -->
    <section aria-label="ملخص النظام" class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @can('finance.view')
            <article class="rounded-xl border border-[var(--brand-border)] bg-white p-3.5 sm:p-5 shadow-xs flex flex-col justify-between">
                <h2 class="text-xs sm:text-sm font-medium text-[var(--brand-muted)]">مبيعات الشهر</h2>
                <p class="mt-2 text-lg sm:text-2xl font-bold text-slate-900">{{ number_format($financial['sales']/100,2) }} <span class="text-xs font-normal text-slate-500">ج.س</span></p>
            </article>

            <article class="rounded-xl border border-[var(--brand-border)] bg-white p-3.5 sm:p-5 shadow-xs flex flex-col justify-between">
                <div>
                    <h2 class="text-xs sm:text-sm font-medium text-[var(--brand-muted)]">كاش وبنك مستلم ومؤكد <span class="sr-only">التحصيل المؤكد</span></h2>
                    <p class="mt-2 text-lg sm:text-2xl font-bold text-emerald-700">{{ number_format($financial['collections']/100,2) }} <span class="text-xs font-normal text-slate-500">ج.س</span></p>
                </div>
                <a href="{{ route('payments.index') }}" class="mt-2 block text-[11px] sm:text-xs font-medium text-[var(--brand-navy)] underline">عرض التفاصيل والمتبقي</a>
            </article>

            <article class="rounded-xl border border-[var(--brand-border)] bg-white p-3.5 sm:p-5 shadow-xs flex flex-col justify-between">
                <h2 class="text-xs sm:text-sm font-medium text-[var(--brand-muted)]">مصروفات الشهر</h2>
                <p class="mt-2 text-lg sm:text-2xl font-bold text-slate-900">{{ number_format($financial['expenses']/100,2) }} <span class="text-xs font-normal text-slate-500">ج.س</span></p>
            </article>

            <article class="rounded-xl border border-[var(--brand-border)] bg-white p-3.5 sm:p-5 shadow-xs flex flex-col justify-between">
                <h2 class="text-xs sm:text-sm font-medium text-[var(--brand-muted)]">الديون الحالية</h2>
                <p class="mt-2 text-lg sm:text-2xl font-bold text-rose-700">{{ number_format($financial['debts']/100,2) }} <span class="text-xs font-normal text-slate-500">ج.س</span></p>
            </article>
        @endcan

        @can('orders.view')
            <article class="rounded-xl border border-[var(--brand-border)] bg-white p-3.5 sm:p-5 shadow-xs flex flex-col justify-between">
                <h2 class="text-xs sm:text-sm font-medium text-[var(--brand-muted)]">الطلبات</h2>
                <p class="mt-2 text-xl sm:text-3xl font-bold text-[var(--brand-navy)]">{{ number_format($ordersCount) }}</p>
            </article>
        @endcan

        @can('customers.view')
            <article class="rounded-xl border border-[var(--brand-border)] bg-white p-3.5 sm:p-5 shadow-xs flex flex-col justify-between">
                <h2 class="text-xs sm:text-sm font-medium text-[var(--brand-muted)]">أولياء الأمور</h2>
                <p class="mt-2 text-xl sm:text-3xl font-bold text-[var(--brand-navy)]">{{ number_format($guardiansCount) }}</p>
            </article>
        @endcan

        @can('inventory.view')
            <article class="rounded-xl border border-[var(--brand-border)] bg-white p-3.5 sm:p-5 shadow-xs flex flex-col justify-between">
                <h2 class="text-xs sm:text-sm font-medium text-[var(--brand-muted)]">المخزون الجاهز</h2>
                <p class="mt-2 text-xl sm:text-3xl font-bold text-[var(--brand-navy)]">{{ number_format($readyStockUnits) }} <span class="text-xs font-normal text-slate-500">قطعة</span></p>
            </article>
        @endcan

        @can('finance.view')
            <article class="rounded-xl border border-[var(--brand-border)] bg-white p-3.5 sm:p-5 shadow-xs flex flex-col justify-between">
                <h2 class="text-xs sm:text-sm font-medium text-[var(--brand-muted)]">إشعارات بنك بانتظار المراجعة <span class="sr-only">تحويلات بانتظار التحقق</span></h2>
                <p class="mt-2 text-xl sm:text-3xl font-bold {{ $pendingTransfersCount > 0 ? 'text-amber-600' : 'text-slate-700' }}">{{ number_format($pendingTransfersCount) }}</p>
            </article>
        @endcan
    </section>

    @can('orders.view')
    <section class="mt-6 rounded-xl border border-[var(--brand-border)] bg-white p-4 sm:p-5 shadow-xs">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-slate-900">الإشعارات الداخلية</h2>
            <span class="text-xs text-[var(--brand-muted)]">تتحدث عند فتح لوحة التحكم</span>
        </div>
        <div class="mt-3 space-y-2">
            @forelse($notifications as $notification)
                <article class="flex flex-wrap items-center justify-between gap-2 rounded-lg {{ $notification->read_at ? 'bg-slate-50' : 'bg-amber-50/80 border border-amber-200/60' }} p-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ $notification->title }}</p>
                        <p class="mt-0.5 text-xs sm:text-sm text-slate-600">{{ $notification->body }}</p>
                    </div>
                    @if(!$notification->read_at)
                        <form method="POST" action="{{ route('notifications.read', $notification) }}">
                            @csrf
                            <button class="rounded-md border border-slate-300 bg-white px-3 py-1 text-xs font-semibold hover:bg-slate-50 min-h-[36px]">تمت القراءة</button>
                        </form>
                    @endif
                </article>
            @empty
                <p class="text-sm text-slate-500 py-2">لا توجد إشعارات مستحقة الآن.</p>
            @endforelse
        </div>
    </section>
    @endcan

    @if ($ordersCount === 0 && $guardiansCount === 0)
        <section class="mt-6 rounded-xl border border-dashed border-[var(--brand-gold)] bg-white p-6">
            <h2 class="font-bold">ابدأ بإضافة بيانات المشغل</h2>
            <p class="mt-2 text-sm leading-7 text-[var(--brand-muted)]">لا توجد سجلات تشغيلية بعد. استخدم أزرار الإجراءات السريعة في الأعلى لإضافة أولياء الأمور والأصناف والطلبات.</p>
        </section>
    @endif
@endsection

