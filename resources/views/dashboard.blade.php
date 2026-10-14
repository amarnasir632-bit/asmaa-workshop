@extends('layouts.app')

@section('title', 'الرئيسية')

@section('content')
    <div class="mb-7">
        <p class="text-sm font-semibold text-[var(--brand-gold)]">نظرة عامة</p>
        <h1 class="mt-1 text-2xl font-bold text-[var(--brand-navy)]">لوحة التحكم</h1>
        <p class="mt-2 text-sm text-[var(--brand-muted)]">ملخص مباشر للسجلات المتاحة لحسابك.</p>
    </div>

    <section aria-label="ملخص النظام" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @can('finance.view')
            <article class="rounded-xl border border-[var(--brand-border)] bg-white p-5 shadow-sm"><h2 class="text-sm text-[var(--brand-muted)]">مبيعات الشهر</h2><p class="mt-3 text-2xl font-bold">{{ number_format($financial['sales']/100,2) }} ج.س</p></article>
            <article class="rounded-xl border border-[var(--brand-border)] bg-white p-5 shadow-sm"><h2 class="text-sm text-[var(--brand-muted)]">التحصيل المؤكد</h2><p class="mt-3 text-2xl font-bold">{{ number_format($financial['collections']/100,2) }} ج.س</p><a href="{{ route('payments.index') }}" class="mt-2 block text-xs text-[var(--brand-navy)] underline">عرض المسجل والمعلق والمتبقي</a></article>
            <article class="rounded-xl border border-[var(--brand-border)] bg-white p-5 shadow-sm"><h2 class="text-sm text-[var(--brand-muted)]">مصروفات الشهر</h2><p class="mt-3 text-2xl font-bold">{{ number_format($financial['expenses']/100,2) }} ج.س</p></article>
            <article class="rounded-xl border border-[var(--brand-border)] bg-white p-5 shadow-sm"><h2 class="text-sm text-[var(--brand-muted)]">الديون الحالية</h2><p class="mt-3 text-2xl font-bold">{{ number_format($financial['debts']/100,2) }} ج.س</p></article>
        @endcan
        @can('orders.view')
            <article class="rounded-xl border border-[var(--brand-border)] bg-white p-5 shadow-sm">
                <h2 class="text-sm font-medium text-[var(--brand-muted)]">الطلبات</h2>
                <p class="mt-3 text-3xl font-bold text-[var(--brand-navy)]">{{ number_format($ordersCount) }}</p>
            </article>
        @endcan
        @can('customers.view')
            <article class="rounded-xl border border-[var(--brand-border)] bg-white p-5 shadow-sm">
                <h2 class="text-sm font-medium text-[var(--brand-muted)]">أولياء الأمور</h2>
                <p class="mt-3 text-3xl font-bold text-[var(--brand-navy)]">{{ number_format($guardiansCount) }}</p>
            </article>
        @endcan
        @can('inventory.view')
            <article class="rounded-xl border border-[var(--brand-border)] bg-white p-5 shadow-sm">
                <h2 class="text-sm font-medium text-[var(--brand-muted)]">وحدات المخزون الجاهز</h2>
                <p class="mt-3 text-3xl font-bold text-[var(--brand-navy)]">{{ number_format($readyStockUnits) }}</p>
            </article>
        @endcan
        @can('finance.view')
            <article class="rounded-xl border border-[var(--brand-border)] bg-white p-5 shadow-sm">
                <h2 class="text-sm font-medium text-[var(--brand-muted)]">تحويلات بانتظار التحقق</h2>
                <p class="mt-3 text-3xl font-bold text-[var(--brand-navy)]">{{ number_format($pendingTransfersCount) }}</p>
            </article>
        @endcan
    </section>

    @can('orders.view')
    <section class="mt-6 rounded-xl border border-[var(--brand-border)] bg-white p-4 shadow-sm"><div class="flex items-center justify-between"><h2 class="font-bold">الإشعارات الداخلية</h2><span class="text-xs text-[var(--brand-muted)]">تتحدث عند فتح لوحة التحكم؛ لا تُرسل خارج النظام</span></div>@forelse($notifications as $notification)<article class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg {{ $notification->read_at ? 'bg-slate-50' : 'bg-amber-50' }} p-3"><div><p class="text-sm font-semibold">{{ $notification->title }}</p><p class="mt-1 text-sm">{{ $notification->body }}</p></div>@if(!$notification->read_at)<form method="POST" action="{{ route('notifications.read',$notification) }}">@csrf<button class="rounded border px-3 py-1 text-xs">تمت القراءة</button></form>@endif</article>@empty<p class="mt-3 text-sm text-slate-500">لا توجد إشعارات مستحقة الآن.</p>@endforelse</section>
    @endcan

    @if ($ordersCount === 0 && $guardiansCount === 0)
        <section class="mt-6 rounded-xl border border-dashed border-[var(--brand-gold)] bg-white p-6">
            <h2 class="font-bold">ابدأ بإضافة بيانات المشغل</h2>
            <p class="mt-2 text-sm leading-7 text-[var(--brand-muted)]">لا توجد سجلات تشغيلية بعد. استخدم القائمة لإضافة أولياء الأمور والأصناف والطلبات.</p>
        </section>
    @endif
@endsection
