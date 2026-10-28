@extends('layouts.app')
@section('title', 'سجل التدقيق والمراجعة')
@section('content')
@php
$actionTranslations = [
    'order_created' => 'إنشاء طلب جديد',
    'order_status_updated' => 'تحديث حالة الطلب',
    'order_cancelled' => 'إلغاء الطلب',
    'payment_recorded' => 'تسجيل دفعة مالية جديدة',
    'payment_reversed' => 'إلغاء / تصحيح دفعة مالية',
    'bank_transfer_verified' => 'تأكيد إشعار تحويل بنكي',
    'bank_transfer_rejected' => 'رفض إشعار تحويل بنكي',
    'refund_approved' => 'اعتماد استرداد مالي',
    'refund_paid' => 'صرف استرداد نقدي',
    'expense_created' => 'تسجيل مصروف جديد',
    'expense_reversed' => 'إلغاء / تصحيح مصروف',
    'guardian_created' => 'إضافة ملف ولي أمر',
    'guardian_updated' => 'تعديل ملف ولي أمر',
    'guardian_archived' => 'أرشفة ملف ولي أمر',
    'child_created' => 'إضافة ملف طفل',
    'child_measurements_updated' => 'تحديث مقاسات الطفل',
    'child_archived' => 'أرشفة ملف طفل',
    'production_job_created' => 'بدء أمر إنتاج',
    'production_stage_updated' => 'تحديث مرحلة الإنتاج',
    'tailor_dispatched' => 'إرسال كمية للترزي',
    'tailor_received' => 'استلام ملابس من الترزي',
    'inventory_movement' => 'حركة مخزنية',
    'price_updated' => 'تحديث سعر الصنف',
    'school_created' => 'إضافة مدرسة جديدة',
    'user_login' => 'تسجيل دخول مستخدم',
    'user_logout' => 'تسجيل خروج مستخدم',
];

$subjectTranslations = [
    'Order' => 'طلب',
    'Payment' => 'دفعة مالية',
    'Refund' => 'استرداد مالي',
    'RefundTransaction' => 'صرف استرداد',
    'Expense' => 'مصروف',
    'Guardian' => 'ولي أمر',
    'Child' => 'طفل',
    'CurrentMeasurement' => 'مقاسات الطفل',
    'ProductionJob' => 'أمر إنتاج',
    'TailorDispatch' => 'إرسالية ترزي',
    'TailorReceipt' => 'استلام ترزي',
    'InventoryMovement' => 'حركة مخزون',
    'School' => 'مدرسة',
    'User' => 'مستخدم',
];

$fieldTranslations = [
    'operational_status' => 'الحالة التشغيلية',
    'verification_status' => 'حالة التحقق البنكي',
    'status' => 'الحالة',
    'stage' => 'المرحلة',
    'amount' => 'المبلغ',
    'quantity' => 'الكمية',
    'price' => 'السعر',
    'name' => 'الاسم',
    'phone' => 'الهاتف',
    'notes' => 'الملاحظات',
    'reason' => 'سبب التعديل',
    'deleted_at' => 'تاريخ الأرشفة',
    'is_active' => 'حالة التفعيل',
    'shoulder_cm' => 'الكتف',
    'chest_cm' => 'الصدر',
    'sleeve_cm' => 'الكم',
    'waist_circumference_cm' => 'الخصر',
    'length_cm' => 'الطول',
];
@endphp

<div class="mx-auto max-w-7xl space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-[var(--brand-gold)]">المراجعة والأمان</p>
            <h1 class="mt-1 text-2xl font-bold text-[var(--brand-navy)]">سجل التدقيق الداخلي</h1>
            <p class="mt-1 text-xs text-[var(--brand-muted)] sm:text-sm">سجل إلكتروني محمي للقراءة فقط يوثق جميع العمليات والتعديلات لضمان النزاهة والشفافية.</p>
        </div>
    </header>

    <!-- شريط تصفية سجل التدقيق -->
    <form method="GET" class="grid gap-3 rounded-xl border border-[var(--brand-border)] bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-700">نوع العملية</label>
            <select name="action" class="w-full rounded-lg border border-slate-300 p-2 text-xs focus:border-[var(--brand-navy)]">
                <option value="">كل العمليات</option>
                @foreach($actions as $action)
                    <option value="{{ $action }}" @selected(($data['action'] ?? '')===$action)>
                        {{ $actionTranslations[$action] ?? $action }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-700">من تاريخ</label>
            <input type="date" name="from" value="{{ $data['from'] ?? '' }}" class="w-full rounded-lg border border-slate-300 p-2 text-xs focus:border-[var(--brand-navy)]">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-700">إلى تاريخ</label>
            <input type="date" name="to" value="{{ $data['to'] ?? '' }}" class="w-full rounded-lg border border-slate-300 p-2 text-xs focus:border-[var(--brand-navy)]">
        </div>
        <div class="flex items-end">
            <button class="inline-flex min-h-[38px] w-full items-center justify-center rounded-lg bg-[var(--brand-navy)] px-4 py-2 text-xs font-bold text-white shadow-sm hover:opacity-95">
                تصفية النتائج
            </button>
        </div>
    </form>

    <!-- بطاقات سجل التدقيق -->
    <section class="space-y-3">
        @forelse($logs as $log)
            @php($baseSubject = class_basename($log->subject_type))
            @php($translatedSubject = $subjectTranslations[$baseSubject] ?? $baseSubject)
            @php($translatedAction = $actionTranslations[$log->action] ?? $log->action)

            <article class="rounded-xl border border-[var(--brand-border)] bg-white p-4 shadow-sm transition-all hover:border-slate-300">
                <div class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 pb-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-bold text-[var(--brand-navy)]">
                                {{ $translatedAction }}
                                <span class="sr-only">{{ $log->action }}</span>
                            </h2>
                            <span class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                                {{ $translatedSubject }}{{ $log->subject_id ? ' #'.$log->subject_id : '' }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-[var(--brand-muted)]">
                            بواسطة: <strong class="text-slate-800">{{ $log->user?->name ?? 'النظام الآلي' }}</strong>
                        </p>
                    </div>

                    <time class="rounded-lg bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-600 border border-slate-200">
                        {{ $log->occurred_at->timezone(config('app.display_timezone'))->format('d/m/Y - h:i A') }}
                    </time>
                </div>

                <!-- تفاصيل الحقول المتأثرة والسبب -->
                <div class="mt-3 space-y-2 text-xs">
                    @if($log->after_values['changed_fields'] ?? false)
                        @php($fields = (array) $log->after_values['changed_fields'])
                        @php($translatedFields = array_map(fn($f) => $fieldTranslations[$f] ?? $f, $fields))
                        <div class="flex flex-wrap items-center gap-1.5 text-slate-700">
                            <span class="font-semibold text-slate-600">الحقول المتأثرة:</span>
                            @foreach($translatedFields as $tf)
                                <span class="inline-flex rounded bg-slate-100 px-2 py-0.5 font-medium text-slate-800">{{ $tf }}</span>
                            @endforeach
                        </div>
                    @endif

                    @if($log->reason)
                        <div class="rounded-lg border border-amber-200/80 bg-amber-50/60 p-2.5 text-slate-800">
                            <span class="font-bold text-amber-900">السبب أو الملاحظة:</span> {{ $log->reason }}
                        </div>
                    @endif

                    @if($log->request_id)
                        <p class="text-[11px] text-[var(--brand-muted)]">
                            رمز التتبع التقني: <span dir="ltr" class="font-mono">{{ $log->request_id }}</span>
                        </p>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-[var(--brand-border)] bg-white p-8 text-center text-sm text-[var(--brand-muted)]">
                لا توجد أحداث تدقيق مطابقة للتصفية المختارة.
            </div>
        @endforelse

        <div class="mt-4">{{ $logs->links() }}</div>
    </section>
</div>
@endsection
