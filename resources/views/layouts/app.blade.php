<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('icon.png') }}">
    <title>@yield('title', 'لوحة التحكم') · مشغل أسماء</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen antialiased">
    <div class="min-h-screen lg:flex">
        <aside class="bg-[var(--brand-navy)] text-white lg:flex lg:w-64 lg:flex-col">
            <div class="border-b border-white/15 px-5 py-5">
                <a href="{{ route('dashboard') }}" class="inline-flex rounded-lg bg-white px-3 py-2" aria-label="مشغل أسماء، الرئيسية"><img src="{{ asset('logo.png') }}" alt="مشغل أسماء" class="h-14 w-auto max-w-full object-contain"></a>
                <p class="mt-1 text-sm text-white/75">إدارة الطلبات والإنتاج والمخزون</p>
            </div>
            <nav aria-label="القائمة الرئيسية" class="flex gap-2 overflow-x-auto p-3 lg:flex-1 lg:flex-col">
                <a href="{{ route('dashboard') }}" class="rounded-lg bg-white/10 px-3 py-2 text-sm font-semibold">الرئيسية</a>
                @can('customers.view')
                    <a href="{{ route('guardians.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold hover:bg-white/10">أولياء الأمور والأطفال</a>
                @endcan
                @can('catalog.view')
                    <a href="{{ route('catalog.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold hover:bg-white/10">المدارس والأصناف</a>
                @endcan
                @can('orders.view')
                    <a href="{{ route('orders.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold hover:bg-white/10">الطلبات</a>
                @endcan
                @can('production.manage')
                    <a href="{{ route('production.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold hover:bg-white/10">الإنتاج والترزي</a>
                @endcan
                @can('inventory.view')
                    <a href="{{ route('inventory.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold hover:bg-white/10">المخزون</a>
                @endcan
                @can('expenses.record')
                    <a href="{{ route('expenses.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold hover:bg-white/10">المصروفات</a>
                @endcan
                @can('payments.record')
                    <a href="{{ route('payments.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold hover:bg-white/10">الدفعات والتحصيل</a>
                @endcan
                @if(auth()->user()->can('reports.operational') || auth()->user()->can('reports.financial'))
                    <a href="{{ route('reports.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold hover:bg-white/10">التقارير</a>
                @endif
                @can('audit.view')
                    <a href="{{ route('audit.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold hover:bg-white/10">سجل التدقيق</a>
                @endcan
                @can('history.manage')
                    <a href="{{ route('history.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold hover:bg-white/10">الترحيل التاريخي</a>
                @endcan
                @can('users.manage')
                    <a href="{{ route('users.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold hover:bg-white/10">المستخدمون والصلاحيات</a>
                @endcan
            </nav>
            <div class="flex items-center justify-between border-t border-white/15 px-4 py-3">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-white/70">{{ auth()->user()->hasRole('manager') ? 'مدير' : 'موظف' }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="rounded-md px-3 py-2 text-sm hover:bg-white/10" type="submit">خروج</button>
                </form>
            </div>
        </aside>
        <main class="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
            @if (session('status'))
                <div role="status" class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-emerald-900">{{ session('status') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
