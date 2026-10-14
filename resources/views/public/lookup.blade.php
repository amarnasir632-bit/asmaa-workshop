<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('icon.png') }}">
    <title>استعلام عن الطلب · مشغل أسماء</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 antialiased"><main class="mx-auto max-w-xl p-4 py-10 sm:py-16">
    <section class="rounded-2xl border border-[var(--brand-border)] bg-white p-5 shadow-sm sm:p-8">
        <div class="mb-6 flex flex-col items-center text-center"><img src="{{ asset('logo.png') }}" alt="مشغل أسماء" class="h-20 w-auto"><h1 class="mt-4 text-2xl font-bold text-[var(--brand-navy)]">استعلام عن حالة الطلب</h1><p class="mt-2 text-sm text-slate-600">أدخل اسم ولي الأمر أو الطفل كما سُجل في الطلب.</p></div>
        @if($errors->any())<div role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-800">يرجى مراجعة الاسم ورقم التواصل المدخلين.</div>@endif
        @if(isset($message))<div role="status" class="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">{{ $message }}</div>@endif
        @if(!empty($needsPhone))<div role="status" class="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">الاسم يحتاج إلى رقم التواصل المسجل للمساعدة في تحديد الطلب.</div>@endif
        @if(isset($result))<div role="status" class="mb-5 grid grid-cols-2 gap-3"><div class="rounded-xl bg-slate-50 p-4 text-center"><p class="text-sm text-slate-600">الجاهزية</p><p class="mt-1 text-lg font-bold text-[var(--brand-navy)]">{{ $result['ready'] ? 'جاهز' : 'غير جاهز' }}</p></div><div class="rounded-xl bg-slate-50 p-4 text-center"><p class="text-sm text-slate-600">السداد</p><p class="mt-1 text-lg font-bold text-[var(--brand-navy)]">{{ $result['paid'] ? 'مسدد' : 'غير مسدد' }}</p></div></div>@endif
        <form method="POST" action="{{ route('public.lookup.search') }}" class="space-y-4">@csrf
            <label class="block text-sm font-semibold">الاسم<input required minlength="2" maxlength="160" name="name" value="{{ old('name') }}" autocomplete="name" class="mt-1 w-full rounded-lg border-slate-300"></label>
            @if(!empty($needsPhone) || old('phone'))<label class="block text-sm font-semibold">رقم التواصل المسجل<input name="phone" value="{{ old('phone') }}" inputmode="tel" autocomplete="tel" class="mt-1 w-full rounded-lg border-slate-300"></label>@endif
            <button class="w-full rounded-lg bg-[var(--brand-navy)] px-4 py-3 font-bold text-white">بحث</button>
        </form>
        <p class="mt-5 border-t pt-4 text-xs leading-6 text-slate-500">يعرض الاستعلام حالة الجاهزية والسداد فقط. البحث بالاسم وحده لا يثبت هوية المستعلم ولا يوفر سرية قوية.</p>
    </section>
</main></body>
</html>
