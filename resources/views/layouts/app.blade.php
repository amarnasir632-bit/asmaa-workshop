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
<body class="min-h-screen antialiased bg-[var(--brand-surface)] text-[var(--brand-ink)]">
    <div class="min-h-screen lg:flex">
        <!-- Desktop Sidebar -->
        <aside class="hidden lg:flex lg:w-64 lg:flex-col bg-[var(--brand-navy)] text-white shrink-0">
            <div class="border-b border-white/15 px-5 py-5">
                <a href="{{ route('dashboard') }}" class="inline-flex rounded-lg bg-white px-3 py-2" aria-label="مشغل أسماء، الرئيسية">
                    <img src="{{ asset('logo.png') }}" alt="مشغل أسماء" class="h-14 w-auto max-w-full object-contain">
                </a>
                <p class="mt-1 text-sm text-white/75">إدارة الطلبات والإنتاج والمخزون</p>
            </div>
            <nav aria-label="القائمة الرئيسية للكمبيوتر" class="flex-1 flex flex-col gap-1 p-3 overflow-y-auto">
                <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('dashboard*') ? 'bg-white/20 text-white font-bold' : 'hover:bg-white/10 text-white/90' }}">الرئيسية</a>
                @can('customers.view')
                    <a href="{{ route('guardians.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('guardians*') ? 'bg-white/20 text-white font-bold' : 'hover:bg-white/10 text-white/90' }}">أولياء الأمور والأطفال</a>
                @endcan
                @can('catalog.view')
                    <a href="{{ route('catalog.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('catalog*') ? 'bg-white/20 text-white font-bold' : 'hover:bg-white/10 text-white/90' }}">المدارس والأصناف</a>
                @endcan
                @can('orders.view')
                    <a href="{{ route('orders.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold transition {{ (request()->routeIs('orders*') || request()->routeIs('school-contracts*')) ? 'bg-white/20 text-white font-bold' : 'hover:bg-white/10 text-white/90' }}">الطلبات</a>
                @endcan
                @can('production.manage')
                    <a href="{{ route('production.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('production*') ? 'bg-white/20 text-white font-bold' : 'hover:bg-white/10 text-white/90' }}">الإنتاج والترزي</a>
                @endcan
                @can('inventory.view')
                    <a href="{{ route('inventory.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('inventory*') ? 'bg-white/20 text-white font-bold' : 'hover:bg-white/10 text-white/90' }}">المخزون</a>
                @endcan
                @can('expenses.record')
                    <a href="{{ route('expenses.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('expenses*') ? 'bg-white/20 text-white font-bold' : 'hover:bg-white/10 text-white/90' }}">المصروفات</a>
                @endcan
                @can('payments.record')
                    <a href="{{ route('payments.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('payments*') ? 'bg-white/20 text-white font-bold' : 'hover:bg-white/10 text-white/90' }}">الدفعات والتحصيل</a>
                @endcan
                @if(auth()->user()->can('reports.operational') || auth()->user()->can('reports.financial'))
                    <a href="{{ route('reports.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('reports*') ? 'bg-white/20 text-white font-bold' : 'hover:bg-white/10 text-white/90' }}">التقارير</a>
                @endif
                @can('audit.view')
                    <a href="{{ route('audit.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('audit*') ? 'bg-white/20 text-white font-bold' : 'hover:bg-white/10 text-white/90' }}">سجل التدقيق</a>
                @endcan
                @can('history.manage')
                    <a href="{{ route('history.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('history*') ? 'bg-white/20 text-white font-bold' : 'hover:bg-white/10 text-white/90' }}">الترحيل التاريخي</a>
                @endcan
                @can('users.manage')
                    <a href="{{ route('users.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('users*') ? 'bg-white/20 text-white font-bold' : 'hover:bg-white/10 text-white/90' }}">المستخدمون والصلاحيات</a>
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

        <!-- Mobile Top Bar (Compact Header without horizontal scroll nav) -->
        <header class="lg:hidden flex items-center justify-between bg-[var(--brand-navy)] text-white px-4 py-3 border-b border-white/10 sticky top-0 z-40">
            <a href="{{ route('dashboard') }}" class="inline-flex rounded-md bg-white px-2 py-1 items-center">
                <img src="{{ asset('logo.png') }}" alt="مشغل أسماء" class="h-8 w-auto object-contain">
            </a>
            <div class="flex items-center gap-2">
                <span class="text-xs text-white/80 font-medium">{{ auth()->user()->name }}</span>
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
            </div>
        </header>

        <!-- Mobile Slide-over Drawer Backdrop & Panel -->
        <div id="mobile-drawer-backdrop" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs transition-opacity" aria-hidden="true"></div>
        <aside id="mobile-drawer-panel" class="fixed inset-y-0 right-0 z-50 w-72 max-w-[85vw] bg-[var(--brand-navy)] text-white p-5 flex flex-col justify-between shadow-2xl transition-transform duration-300 ease-in-out translate-x-full md:hidden">
            <div>
                <div class="flex items-center justify-between border-b border-white/15 pb-4">
                    <div>
                        <p class="font-bold text-base text-white">مشغل أسماء</p>
                        <p class="text-xs text-white/70">{{ auth()->user()->name }} ({{ auth()->user()->hasRole('manager') ? 'مدير' : 'موظف' }})</p>
                    </div>
                    <button type="button" id="mobile-drawer-close" class="rounded-lg p-2 text-white/80 hover:bg-white/10 focus:outline-hidden" aria-label="إغلاق القائمة">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <nav class="mt-4 flex flex-col gap-1.5 overflow-y-auto max-h-[calc(100vh-180px)]">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[var(--brand-gold)] px-2 pt-1 pb-0.5">الأقسام الإضافية</p>
                    @can('customers.view')
                        <a href="{{ route('guardians.index') }}" class="rounded-lg px-3 py-2.5 text-sm font-semibold hover:bg-white/10 flex items-center gap-2.5 {{ request()->routeIs('guardians*') ? 'bg-white/20' : '' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            أولياء الأمور والأطفال
                        </a>
                    @endcan
                    @can('catalog.view')
                        <a href="{{ route('catalog.index') }}" class="rounded-lg px-3 py-2.5 text-sm font-semibold hover:bg-white/10 flex items-center gap-2.5 {{ request()->routeIs('catalog*') ? 'bg-white/20' : '' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            المدارس والأصناف
                        </a>
                    @endcan
                    @can('inventory.view')
                        <a href="{{ route('inventory.index') }}" class="rounded-lg px-3 py-2.5 text-sm font-semibold hover:bg-white/10 flex items-center gap-2.5 {{ request()->routeIs('inventory*') ? 'bg-white/20' : '' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                            المخزون
                        </a>
                    @endcan
                    @can('expenses.record')
                        <a href="{{ route('expenses.index') }}" class="rounded-lg px-3 py-2.5 text-sm font-semibold hover:bg-white/10 flex items-center gap-2.5 {{ request()->routeIs('expenses*') ? 'bg-white/20' : '' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            المصروفات
                        </a>
                    @endcan
                    @if(auth()->user()->can('reports.operational') || auth()->user()->can('reports.financial'))
                        <a href="{{ route('reports.index') }}" class="rounded-lg px-3 py-2.5 text-sm font-semibold hover:bg-white/10 flex items-center gap-2.5 {{ request()->routeIs('reports*') ? 'bg-white/20' : '' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                            التقارير
                        </a>
                    @endif
                    @can('audit.view')
                        <a href="{{ route('audit.index') }}" class="rounded-lg px-3 py-2.5 text-sm font-semibold hover:bg-white/10 flex items-center gap-2.5 {{ request()->routeIs('audit*') ? 'bg-white/20' : '' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            سجل التدقيق
                        </a>
                    @endcan
                    @can('history.manage')
                        <a href="{{ route('history.index') }}" class="rounded-lg px-3 py-2.5 text-sm font-semibold hover:bg-white/10 flex items-center gap-2.5 {{ request()->routeIs('history*') ? 'bg-white/20' : '' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            الترحيل التاريخي
                        </a>
                    @endcan
                    @can('users.manage')
                        <a href="{{ route('users.index') }}" class="rounded-lg px-3 py-2.5 text-sm font-semibold hover:bg-white/10 flex items-center gap-2.5 {{ request()->routeIs('users*') ? 'bg-white/20' : '' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            المستخدمون والصلاحيات
                        </a>
                    @endcan
                </nav>
            </div>
            <div class="border-t border-white/15 pt-4">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="w-full flex items-center justify-center gap-2 rounded-lg bg-white/10 px-4 py-3 text-sm font-semibold text-white hover:bg-white/20 transition min-h-[44px]" type="submit">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        تسجيل الخروج
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="min-w-0 flex-1 p-4 sm:p-6 lg:p-8 pb-24 md:pb-8">
            @if (session('status'))
                <div role="status" class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-emerald-900 font-medium flex items-center gap-2 shadow-xs">
                    <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif
            @yield('content')
        </main>
    </div>

    <!-- Mobile Bottom Navigation Bar (md:hidden) -->
    <nav aria-label="شريط التنقل السفلي" class="fixed bottom-0 inset-x-0 z-40 bg-white border-t border-slate-200 shadow-lg md:hidden min-h-[64px] flex items-stretch justify-around px-1 py-1">
        <!-- الرئيسية -->
        <a href="{{ route('dashboard') }}" class="flex-1 flex flex-col items-center justify-center py-1 text-center transition {{ request()->routeIs('dashboard*') ? 'text-[var(--brand-navy)] font-bold' : 'text-slate-500 hover:text-slate-900' }}">
            <svg class="w-6 h-6 mb-0.5 {{ request()->routeIs('dashboard*') ? 'stroke-[2.5]' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            <span class="text-[11px] leading-tight">الرئيسية</span>
        </a>

        <!-- الطلبات -->
        @can('orders.view')
            <a href="{{ route('orders.index') }}" class="flex-1 flex flex-col items-center justify-center py-1 text-center transition {{ (request()->routeIs('orders*') || request()->routeIs('school-contracts*')) ? 'text-[var(--brand-navy)] font-bold' : 'text-slate-500 hover:text-slate-900' }}">
                <svg class="w-6 h-6 mb-0.5 {{ (request()->routeIs('orders*') || request()->routeIs('school-contracts*')) ? 'stroke-[2.5]' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                <span class="text-[11px] leading-tight">الطلبات</span>
            </a>
        @endcan

        <!-- الدفعات -->
        @can('payments.record')
            <a href="{{ route('payments.index') }}" class="flex-1 flex flex-col items-center justify-center py-1 text-center transition {{ request()->routeIs('payments*') ? 'text-[var(--brand-navy)] font-bold' : 'text-slate-500 hover:text-slate-900' }}">
                <svg class="w-6 h-6 mb-0.5 {{ request()->routeIs('payments*') ? 'stroke-[2.5]' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span class="text-[11px] leading-tight">الدفعات</span>
            </a>
        @endcan

        <!-- الإنتاج -->
        @can('production.manage')
            <a href="{{ route('production.index') }}" class="flex-1 flex flex-col items-center justify-center py-1 text-center transition {{ request()->routeIs('production*') ? 'text-[var(--brand-navy)] font-bold' : 'text-slate-500 hover:text-slate-900' }}">
                <svg class="w-6 h-6 mb-0.5 {{ request()->routeIs('production*') ? 'stroke-[2.5]' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879a3 3 0 11-4.242-4.242L10.757 8.5m3.364 5.621l4.243-4.242M12 12l-2.879-2.879a3 3 0 10-4.242 4.242L7.757 16.5"></path></svg>
                <span class="text-[11px] leading-tight">الإنتاج</span>
            </a>
        @endcan

        <!-- المزيد (Slide-over Drawer Trigger) -->
        <button type="button" id="mobile-more-btn" class="flex-1 flex flex-col items-center justify-center py-1 text-center text-slate-500 hover:text-slate-900 transition">
            <svg class="w-6 h-6 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
            <span class="text-[11px] leading-tight">المزيد</span>
        </button>
    </nav>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const moreBtn = document.getElementById('mobile-more-btn');
            const drawerBackdrop = document.getElementById('mobile-drawer-backdrop');
            const drawerPanel = document.getElementById('mobile-drawer-panel');
            const drawerClose = document.getElementById('mobile-drawer-close');

            function openDrawer() {
                if (!drawerBackdrop || !drawerPanel) return;
                drawerBackdrop.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
                requestAnimationFrame(() => {
                    drawerPanel.classList.remove('translate-x-full');
                });
            }

            function closeDrawer() {
                if (!drawerBackdrop || !drawerPanel) return;
                drawerPanel.classList.add('translate-x-full');
                document.body.classList.remove('overflow-hidden');
                setTimeout(() => {
                    drawerBackdrop.classList.add('hidden');
                }, 300);
            }

            if (moreBtn) moreBtn.addEventListener('click', openDrawer);
            if (drawerClose) drawerClose.addEventListener('click', closeDrawer);
            if (drawerBackdrop) drawerBackdrop.addEventListener('click', closeDrawer);
        });
    </script>
</body>
</html>

