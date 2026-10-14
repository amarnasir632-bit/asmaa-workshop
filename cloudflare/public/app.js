const loginPanel = document.querySelector("#login-panel");
const accountPanel = document.querySelector("#account-panel");
const workPanel = document.querySelector("#work-panel");
const staffPanel = document.querySelector("#staff-panel");
const notice = document.querySelector("#notice");
let csrfToken = "";
let selectedGuardianId = null;
let currentPermissions = [];
let guardians = [];
let catalog = null;
let orders = [];

const messages = {
    unauthenticated: "بيانات الدخول غير صحيحة أو الحساب غير مفعل.",
    login_rate_limited: "محاولات كثيرة. انتظر دقيقة ثم حاول مرة أخرى.",
    internal_error: "تعذر إكمال الطلب الآن.",
    csrf_failed: "انتهت صلاحية الجلسة. سجّل الدخول من جديد.",
    last_active_manager: "يجب إبقاء مدير نشط واحد على الأقل.",
    cannot_disable_or_demote_self: "لا يمكن تغيير دور المدير الحالي أو إيقاف حسابه.",
    forbidden: "ليس لديك صلاحية لتنفيذ هذا الإجراء.",
    username_or_email_in_use: "اسم المستخدم أو البريد مسجل مسبقاً.",
    username_or_email_in_use_or_manager_protection: "تعذر التحديث بسبب تكرار البيانات أو حماية آخر مدير.",
    invalid_school_data: "تحقق من بيانات المدرسة.",
    school_name_in_use: "اسم المدرسة مسجل مسبقاً.",
    invalid_grade_data: "تحقق من بيانات المرحلة.",
    grade_already_exists: "المرحلة مسجلة لهذه المدرسة.",
    invalid_garment_type_data: "تحقق من بيانات نوع الملابس.",
    garment_type_name_in_use: "نوع الملابس مسجل مسبقاً.",
    invalid_variant_data: "تحقق من بيانات تركيبة المخزون.",
    required_variant_attribute_missing: "أدخل المقاس أو اللون المطلوب لهذا النوع.",
    variant_already_exists: "تركيبة المخزون موجودة مسبقاً.",
    sku_in_use: "رمز الصنف مستخدم مسبقاً.",
    invalid_order_data: "تحقق من بيانات الطلب والأسعار والتواريخ.",
    insufficient_stock: "الكمية المطلوبة غير متوفرة في المخزون.",
    order_transaction_failed: "تعذر حفظ الطلب بسبب تعارض في المخزون أو قاعدة البيانات.",
    invalid_payment_data: "تحقق من بيانات الدفعة.",
    payment_exceeds_order_or_transaction_failed: "الدفعة تتجاوز المتبقي أو تعذر حفظها.",
    transaction_reference_in_use: "رقم العملية مسجل مسبقاً لهذا الطلب.",
    invalid_delivery_data: "تحقق من بيانات التسليم.",
    invalid_delivery_items: "تحقق من كميات بنود التسليم.",
    positive_delivery_quantity_required: "أدخل كمية موجبة لبند واحد على الأقل.",
    order_must_be_fully_paid: "يجب سداد الطلب بالكامل قبل تسجيل التسليم.",
    overdelivery: "الكمية تتجاوز المتبقي للتسليم.",
    delivery_item_not_in_order: "أحد البنود لا يتبع هذا الطلب.",
    cancelled_order: "لا يمكن تسجيل تسليم لطلب ملغي.",
    delivery_transaction_failed: "تعذر حفظ بيانات التسليم.",
    guardian_not_found: "ملف ولي الأمر غير موجود أو مؤرشف.",
    child_guardian_mismatch: "الطفل المحدد لا يتبع ولي الأمر المختار.",
    garment_type_not_found: "نوع الملابس المحدد غير متاح.",
    variant_not_active: "تركيبة المخزون المحددة غير نشطة.",
    invalid_opening_batch: "تحقق من بيانات الجرد الافتتاحي.",
    invalid_opening_entries: "تحقق من أصناف وكميات الجرد.",
    opening_batch_not_draft: "دفعة الجرد ليست مسودة.",
    opening_balance_already_initialized: "سبق تسجيل حركة لهذا الصنف؛ استخدم تصحيح المخزون.",
    invalid_adjustment: "أدخل سبباً وبنداً واحداً على الأقل للتصحيح.",
    invalid_adjustment_items: "تحقق من أصناف وقيم التصحيح.",
    adjustment_not_pending: "طلب التصحيح ليس بانتظار القرار.",
    adjustment_would_make_negative_or_failed: "سيجعل التصحيح رصيد الصنف سالباً أو تعذر اعتماده.",
    rejection_reason_required: "سبب الرفض مطلوب.",
    invalid_production_job: "تحقق من بيانات سجل الإنتاج.",
    production_quantity_must_match_order_item: "يجب أن تطابق كمية الإنتاج كمية بند الطلب.",
    production_job_exists: "يوجد سجل إنتاج لهذا البند بالفعل.",
    invalid_production_stage: "تحقق من مرحلة الإنتاج وسبب التغيير.",
    invalid_tailor_dispatch: "تحقق من بيانات الإرسال إلى الترزي.",
    dispatch_exceeds_production_quantity: "الكمية المرسلة تتجاوز المتبقي للإنتاج.",
    invalid_tailor_receipt: "تحقق من بيانات استلام الترزي.",
    invalid_tailor_receipt_items: "تحقق من بنود وكميات الاستلام.",
    receipt_exceeds_dispatch_quantity: "كمية الاستلام تتجاوز ما أُرسل إلى الترزي.",
    invalid_expense_data: "تحقق من بيانات المصروف.",
    expense_category_not_found: "فئة المصروف غير متاحة.",
    invalid_cancellation_data: "تحقق من سبب الإلغاء والخصم.",
    cancellation_discount_exceeds_total: "خصم الإلغاء لا يجوز أن يتجاوز قيمة الطلب.",
    order_already_cancelled: "سبق إلغاء هذا الطلب.",
    invalid_refund_data: "تحقق من بيانات الاسترداد.",
    refund_exceeds_available: "مبلغ الاسترداد يتجاوز التحصيل المؤكد والمتاح.",
    original_payment_not_confirmed: "اختر دفعة مؤكدة تخص هذا الطلب.",
    returned_item_inspection_required: "يلزم اختيار القطعة المسلمة وتسجيل فحصها قبل الاسترداد.",
    returned_quantity_exceeds_delivery: "كمية الإرجاع تتجاوز الكمية المسلمة.",
    invalid_refund_transaction: "تحقق من بيانات الدفع الفعلي للاسترداد.",
    refund_payment_exceeds_approved: "مبلغ الدفع يتجاوز اعتماد الاسترداد المتبقي.",
    invalid_refund_correction: "تحقق من مبلغ وسبب تصحيح الاسترداد.",
    invalid_report_filters: "تحقق من نطاق التقرير.",
    invalid_history_batch: "تحقق من وصف ومواعيد دفعة الترحيل.",
    invalid_history_record: "تحقق من بيانات السجل التاريخي.",
    history_duplicate_correction: "يوجد سجل تاريخي مطابق لهذا التصحيح.",
    invalid_history_review: "أدخل نتيجة المراجعة وملاحظاتها.",
    invalid_history_correction: "تحقق من النص وسبب التصحيح.",
};

function showNotice(message, isError = false) {
    notice.textContent = message;
    notice.dataset.kind = isError ? "error" : "success";
}

async function readJson(response) {
    const body = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw new Error(messages[body.error] ?? "تعذر تنفيذ الطلب.");
    }

    return body;
}

async function api(path, options = {}) {
    const headers = new Headers(options.headers ?? {});
    if (options.body) headers.set("content-type", "application/json");
    if (options.method && options.method !== "GET") headers.set("x-csrf-token", csrfToken);

    return readJson(await fetch(path, {
        ...options,
        headers,
        credentials: "same-origin",
    }));
}

function createRecordButton(label, id, action) {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "secondary compact";
    button.dataset.id = id;
    button.dataset.action = action;
    button.textContent = label;
    return button;
}

function setOptions(select, records, getLabel, placeholder) {
    select.replaceChildren();
    if (placeholder !== null) {
        const empty = document.createElement("option");
        empty.value = "";
        empty.textContent = placeholder;
        select.append(empty);
    }
    for (const record of records) {
        const option = document.createElement("option");
        option.value = record.id;
        option.textContent = getLabel(record);
        select.append(option);
    }
}

function fillGuardianSelects() {
    for (const id of ["order-guardian", "payment-order"]) {
        const select = document.querySelector(`#${id}`);
        if (!select) continue;
        setOptions(select, id === "order-guardian" ? guardians : orders, (record) =>
            id === "order-guardian"
                ? `${record.name} — ${record.phone}`
                : `${record.number} — ${record.customer_name_snapshot} — ${record.approved_total} ج.س`,
        "اختر...");
    }
}

async function loadGuardians(search = "") {
    const list = document.querySelector("#guardian-list");
    list.replaceChildren();
    const result = await api(`/api/guardians?q=${encodeURIComponent(search)}&limit=50`);
    guardians = result.data;
    fillGuardianSelects();
    for (const guardian of result.data) {
        const item = document.createElement("li");
        const text = document.createElement("span");
        text.textContent = `${guardian.name} — ${guardian.phone} (${guardian.children_count})`;
        item.append(text, createRecordButton("فتح الملف", guardian.id, "open-guardian"));
        list.append(item);
    }
    if (result.data.length === 0) {
        const empty = document.createElement("li");
        empty.textContent = "لا توجد ملفات مطابقة.";
        list.append(empty);
    }
}

async function loadGuardian(id) {
    const guardian = await api(`/api/guardians/${id}`);
    selectedGuardianId = guardian.id;
    document.querySelector("#guardian-detail-title").textContent = guardian.name;
    const childList = document.querySelector("#child-list");
    childList.replaceChildren();
    setOptions(
        document.querySelector("#order-child"),
        guardian.children,
        (child) => `${child.name} — ${child.school_name ?? "دون مدرسة"}`,
        "دون طفل محدد",
    );
    for (const child of guardian.children) {
        const item = document.createElement("li");
        const summary = document.createElement("span");
        const measurementText = child.shoulder_cm === null
            ? "لم تسجل المقاسات"
            : `الكتف ${child.shoulder_cm} سم · الصدر ${child.chest_cm ?? "—"} سم · الطول ${child.length_cm ?? "—"} سم`;
        summary.textContent = `${child.name} · ${child.school_name ?? "دون مدرسة"} · ${measurementText}`;
        item.append(summary, createRecordButton("مقاسات", child.id, "measure-child"));
        childList.append(item);
    }
    if (guardian.children.length === 0) {
        const empty = document.createElement("li");
        empty.textContent = "لا يوجد أطفال في الملف بعد.";
        childList.append(empty);
    }
    document.querySelector("#guardian-detail").hidden = false;
}

function appendCatalogRecords(list, records, label, route) {
    for (const record of records) {
        const item = document.createElement("li");
        const text = document.createElement("span");
        text.textContent = `${label(record)}${record.is_active ? "" : " — موقوف"}`;
        item.append(text);
        if (currentPermissions.includes("catalog.manage") && record.is_active) {
            item.append(createRecordButton("إيقاف", record.id, `deactivate:${route}`));
        }
        list.append(item);
    }
}

async function loadCatalog() {
    catalog = await api("/api/catalog");
    const activeSchools = catalog.schools.filter((record) => record.is_active);
    const activeGrades = catalog.grades.filter((record) => record.is_active);
    const activeGarments = catalog.garment_types.filter((record) => record.is_active);
    const activeVariants = catalog.variants.filter((record) => record.is_active);
    setOptions(document.querySelector("#grade-school"), activeSchools, (record) => record.name, "اختر المدرسة");
    setOptions(document.querySelector("#variant-school"), activeSchools, (record) => record.name, "اختياري");
    setOptions(document.querySelector("#variant-grade"), activeGrades, (record) =>
        `${record.name} — ${catalog.schools.find((school) => school.id === record.school_id)?.name ?? ""}`,
    "اختياري");
    setOptions(document.querySelector("#variant-garment"), activeGarments, (record) => record.name, "اختر النوع");
    setOptions(document.querySelector("#order-garment"), activeGarments, (record) => record.name, "اختياري");
    setOptions(document.querySelector("#order-variant"), activeVariants, (record) =>
        `${record.sku ? `${record.sku} — ` : ""}${record.garment_type_name} ${record.size} ${record.color} — متاح ${record.quantity_available}`,
    "اختر الصنف");

    const list = document.querySelector("#catalog-list");
    list.replaceChildren();
    appendCatalogRecords(list, catalog.schools, (record) => `مدرسة: ${record.name}`, "schools");
    appendCatalogRecords(list, catalog.grades, (record) =>
        `مرحلة ${record.name} — ${catalog.schools.find((school) => school.id === record.school_id)?.name ?? ""}`,
    "grades");
    appendCatalogRecords(list, catalog.garment_types, (record) => `نوع: ${record.name}`, "garment-types");
    appendCatalogRecords(list, catalog.variants, (record) =>
        `مخزون: ${record.garment_type_name} ${record.size} ${record.color} — ${record.sku ?? "دون رمز"} — متاح ${record.quantity_available}`,
    "variants");
}

async function loadOrders() {
    if (!currentPermissions.includes("orders.view")) return;
    const result = await api("/api/orders?limit=50");
    orders = result.data;
    fillGuardianSelects();
    const list = document.querySelector("#orders-list");
    list.replaceChildren();
    for (const order of orders) {
        const item = document.createElement("li");
        const text = document.createElement("span");
        text.textContent = `${order.number} — ${order.customer_name_snapshot} — ${order.approved_total} ج.س — ${order.operational_status}`;
        item.append(text, createRecordButton("التفاصيل", order.id, "open-order"));
        list.append(item);
    }
    if (orders.length === 0) {
        const empty = document.createElement("li");
        empty.textContent = "لا توجد طلبات.";
        list.append(empty);
    }
}

async function loadOrder(id) {
    const order = await api(`/api/orders/${id}`);
    document.querySelector("#order-detail-title").textContent =
        `${order.number} — ${order.guardian_name ?? order.customer_name_snapshot}`;
    const summary = order.financial_summary;
    document.querySelector("#order-financial-summary").textContent =
        `الإجمالي ${order.approved_total} ج.س · المدفوع ${summary.net_paid} ج.س · المتبقي ${summary.due} ج.س`;
    const itemList = document.querySelector("#order-items-list");
    const quantityList = document.querySelector("#delivery-quantities");
    itemList.replaceChildren();
    quantityList.replaceChildren();
    for (const item of order.items) {
        const remaining = Number(item.quantity) - Number(item.delivered_quantity);
        const listItem = document.createElement("li");
        listItem.textContent = `${item.description_snapshot} — ${item.quantity} × ${item.unit_price} ج.س — تم تسليم ${item.delivered_quantity} — متبقي ${remaining}`;
        itemList.append(listItem);

        if (remaining > 0) {
            const label = document.createElement("label");
            const input = document.createElement("input");
            input.type = "number";
            input.min = "0";
            input.max = String(remaining);
            input.value = "0";
            input.dataset.orderItemId = item.id;
            label.textContent = `${item.description_snapshot} — كمية التسليم (المتبقي ${remaining})`;
            label.append(input);
            quantityList.append(label);
        }
    }
    const deliveryForm = document.querySelector("#delivery-form");
    deliveryForm.dataset.orderId = order.id;
    deliveryForm.hidden = !currentPermissions.includes("deliveries.create")
        || !summary.paid
        || order.operational_status === "cancelled"
        || quantityList.childElementCount === 0;
    if (!deliveryForm.hidden) {
        const deliveredAt = deliveryForm.elements.delivered_at;
        if (!deliveredAt.value) {
            const localNow = new Date(Date.now() - new Date().getTimezoneOffset() * 60_000);
            deliveredAt.value = localNow.toISOString().slice(0, 16);
        }
    }
    document.querySelector("#order-detail").hidden = false;
}

async function loadPayments() {
    if (!currentPermissions.includes("payments.record")) return;
    const result = await api("/api/payments");
    const list = document.querySelector("#payments-list");
    list.replaceChildren();
    for (const payment of result.data) {
        const item = document.createElement("li");
        const text = document.createElement("span");
        text.textContent = `${payment.order_number} — ${payment.amount} ج.س — ${payment.method === "cash" ? "نقداً" : "تحويل بنكي"} — ${payment.verification_status}`;
        item.append(text);
        if (payment.method === "bank_transfer" && payment.verification_status === "pending"
            && currentPermissions.includes("payments.verify")) {
            item.append(createRecordButton("اعتماد التحويل", payment.id, "verify-payment"));
        }
        list.append(item);
    }
    if (result.data.length === 0) {
        const empty = document.createElement("li");
        empty.textContent = "لا توجد دفعات.";
        list.append(empty);
    }
}

function currentLocalDate() {
    return new Date(Date.now() - new Date().getTimezoneOffset() * 60_000).toISOString().slice(0, 10);
}

async function loadInventory() {
    if (!currentPermissions.includes("inventory.view")) return;
    const result = await api("/api/inventory");
    const active = result.variants.filter((variant) => variant.is_active);
    for (const id of ["opening-variant", "adjustment-variant"]) {
        const select = document.querySelector(`#${id}`);
        if (select) setOptions(select, active, (variant) =>
            `${variant.sku ? `${variant.sku} — ` : ""}${variant.garment_type_name} ${variant.size} ${variant.color}`,
        "اختر الصنف");
    }
    const list = document.querySelector("#inventory-list");
    list.replaceChildren();
    for (const variant of result.variants) {
        const item = document.createElement("li");
        item.textContent = `${variant.garment_type_name} ${variant.size} ${variant.color} — ${variant.school_name ?? "عام"} — متاح ${variant.quantity_available}`;
        list.append(item);
    }
    const openingList = document.querySelector("#opening-list");
    openingList.replaceChildren();
    for (const batch of result.opening_batches) {
        const item = document.createElement("li");
        const text = document.createElement("span");
        text.textContent = `${batch.reference} — ${batch.counted_on} — ${batch.status} — ${batch.entry_count} أصناف`;
        item.append(text);
        if (batch.status === "draft" && currentPermissions.includes("inventory.manage")) {
            item.append(createRecordButton("اعتماد الجرد", batch.id, "approve-opening"));
        }
        openingList.append(item);
    }
    const adjustments = document.querySelector("#adjustment-list");
    adjustments.replaceChildren();
    for (const adjustment of result.adjustments) {
        const item = document.createElement("li");
        const text = document.createElement("span");
        text.textContent = `${adjustment.reference} — ${adjustment.reason} — ${adjustment.status} — ${adjustment.item_count} أصناف`;
        item.append(text);
        if (adjustment.status === "pending" && currentPermissions.includes("inventory.manage")) {
            item.append(createRecordButton("اعتماد", adjustment.id, "approve-adjustment"));
            item.append(createRecordButton("رفض", adjustment.id, "reject-adjustment"));
        }
        adjustments.append(item);
    }
}

async function loadProduction() {
    if (!currentPermissions.includes("production.manage")) return;
    const result = await api("/api/production");
    setOptions(document.querySelector("#production-item"), result.eligible_items, (item) =>
        `${item.order_number} — ${item.customer_name_snapshot} — ${item.description_snapshot} (${item.quantity})`,
    "اختر بند الطلب");
    const list = document.querySelector("#production-list");
    list.replaceChildren();
    for (const job of result.jobs) {
        const item = document.createElement("li");
        const label = document.createElement("span");
        label.textContent = `${job.order_number} — ${job.description_snapshot} — الإنتاج ${job.quantity_received}/${job.quantity} مستلم، ${job.quantity_sent} مرسل — ${job.stage}`;
        item.append(label);
        item.append(createRecordButton("تغيير المرحلة", job.id, "production-stage"));
        item.append(createRecordButton("إرسال للترزي", job.id, "production-dispatch"));
        list.append(item);
    }
    const dispatchList = document.createElement("li");
    dispatchList.textContent = "إرساليات الترزي:";
    list.append(dispatchList);
    for (const dispatch of result.dispatches) {
        const item = document.createElement("li");
        const remaining = Number(dispatch.quantity_sent) - Number(dispatch.quantity_received);
        const label = document.createElement("span");
        label.textContent = `إرسال ${dispatch.id} — ${dispatch.recipient_name} — ${dispatch.quantity_received}/${dispatch.quantity_sent} مستلم`;
        item.append(label);
        if (remaining > 0) {
            const receive = createRecordButton("تسجيل استلام", dispatch.id, "production-receipt");
            receive.dataset.dispatchItemId = dispatch.dispatch_item_id;
            receive.dataset.remaining = remaining;
            item.append(receive);
        }
        list.append(item);
    }
    if (result.jobs.length === 0) {
        const empty = document.createElement("li");
        empty.textContent = "لا توجد سجلات إنتاج بعد.";
        list.append(empty);
    }
}

async function loadExpenses() {
    if (!currentPermissions.includes("expenses.record")) return;
    const categories = catalog?.expense_categories.filter((category) => category.is_active) ?? [];
    setOptions(document.querySelector("#expense-category"), categories, (category) => category.name, "اختر الفئة");
    const result = await api("/api/expenses");
    const list = document.querySelector("#expenses-list");
    list.replaceChildren();
    for (const expense of result.data) {
        const item = document.createElement("li");
        item.textContent = `${expense.spent_on} — ${expense.description} — ${expense.amount} ج.س — ${expense.category_name}`;
        list.append(item);
    }
    if (result.data.length === 0) {
        const empty = document.createElement("li");
        empty.textContent = "لا توجد مصروفات مسجلة.";
        list.append(empty);
    }
}

async function loadReport() {
    if (!currentPermissions.includes("reports.operational") && !currentPermissions.includes("reports.financial")) return;
    const from = document.querySelector("#report-from").value;
    const to = document.querySelector("#report-to").value;
    if (!from || !to) return;
    const report = await api(`/api/reports?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`);
    const summary = report.financial
        ? `المبيعات ${report.financial.sales} ج.س · المقبوض نقداً ${report.financial.cash_collections} · التحويلات المؤكدة ${report.financial.verified_bank_collections} · الاستردادات ${report.financial.refunds} · المصروفات ${report.financial.expenses} · الديون الحالية ${report.financial.debts} ج.س`
        : `عدد الطلبات ${report.orders.length} · سجلات الإنتاج ${report.production_jobs.length} · أصناف المخزون ${report.inventory.length}`;
    document.querySelector("#report-summary").textContent = summary;
    const list = document.querySelector("#report-orders");
    list.replaceChildren();
    for (const order of report.orders) {
        const item = document.createElement("li");
        item.textContent = `${order.number} — ${order.customer_name_snapshot} — ${order.created_on} — ${order.operational_status}${report.financial ? ` — ${order.approved_total} ج.س` : ""}`;
        list.append(item);
    }
}

async function loadAudit() {
    if (!currentPermissions.includes("audit.view")) return;
    const result = await api("/api/audit?limit=50");
    const list = document.querySelector("#audit-list");
    list.replaceChildren();
    for (const log of result.data) {
        const item = document.createElement("li");
        item.textContent = `${log.occurred_at} — ${log.user_name ?? "النظام"} — ${log.action} — ${log.subject_type} #${log.subject_id ?? "—"}${log.reason ? ` — ${log.reason}` : ""}`;
        list.append(item);
    }
}

let selectedHistoryBatchId = null;

async function loadHistory() {
    if (!currentPermissions.includes("history.manage")) return;
    const result = await api("/api/history");
    const list = document.querySelector("#history-batches");
    list.replaceChildren();
    for (const batch of result.data) {
        const item = document.createElement("li");
        const text = document.createElement("span");
        text.textContent = `${batch.source_description} — ${batch.imported_on} — ${batch.record_count} سجلات — ${batch.issue_count} مشكلات`;
        item.append(text, createRecordButton("فتح", batch.id, "open-history"));
        list.append(item);
    }
}

async function loadHistoryBatch(id) {
    const batch = await api(`/api/history/${id}`);
    selectedHistoryBatchId = batch.id;
    document.querySelector("#history-detail-title").textContent =
        `${batch.source_description} — سجلات ${batch.records_seen} — بحاجة مراجعة ${batch.records_flagged}`;
    document.querySelector("#history-detail").hidden = false;
    const list = document.querySelector("#history-records");
    list.replaceChildren();
    for (const record of batch.records) {
        const item = document.createElement("li");
        const details = document.createElement("span");
        details.textContent = `${record.record_type} — ${record.confidence} — ${record.review_status} — ${record.transcription.slice(0, 180)}`;
        item.append(details);
        if (currentPermissions.includes("history.manage") && record.review_status === "pending") {
            const review = createRecordButton("مراجعة", record.id, "review-history");
            item.append(review);
        }
        list.append(item);
    }
    for (const issue of batch.issues) {
        const item = document.createElement("li");
        item.textContent = `مشكلة: ${issue.issue_type} — ${issue.description}`;
        list.append(item);
    }
}

async function loadUsers() {
    const result = await api("/api/users");
    const list = document.querySelector("#staff-list");
    list.replaceChildren();
    for (const user of result.data) {
        const item = document.createElement("li");
        const label = document.createElement("span");
        label.textContent = `${user.name} · ${user.username} · ${user.role === "manager" ? "مدير" : "موظف"} · ${user.is_active ? "نشط" : "موقوف"}`;
        const role = document.createElement("select");
        role.setAttribute("aria-label", `دور ${user.username}`);
        for (const [value, text] of [["employee", "موظف"], ["manager", "مدير"]]) {
            const option = document.createElement("option");
            option.value = value;
            option.textContent = text;
            role.append(option);
        }
        role.value = user.role;
        role.dataset.field = "role";
        const activeLabel = document.createElement("label");
        const active = document.createElement("input");
        active.type = "checkbox";
        active.checked = Boolean(user.is_active);
        active.dataset.field = "is_active";
        activeLabel.append(active, " نشط");
        const save = createRecordButton("حفظ", user.id, "save-staff");
        save.dataset.name = user.name;
        save.dataset.username = user.username;
        save.dataset.email = user.email ?? "";
        item.append(label, role, activeLabel, save);
        list.append(item);
    }
}

async function loadAccount() {
    let result;
    try {
        result = await readJson(await fetch("/api/me", { credentials: "same-origin" }));
    } catch (error) {
        csrfToken = "";
        loginPanel.hidden = false;
        accountPanel.hidden = true;
        workPanel.hidden = true;
        if (error.message !== messages.unauthenticated) showNotice(error.message, true);
        return;
    }

    csrfToken = result.csrfToken;
    document.querySelector("#user-name").textContent = result.user.name;
    document.querySelector("#account-message").textContent = "تم التحقق من الجلسة.";
    loginPanel.hidden = true;
    accountPanel.hidden = false;
    currentPermissions = result.permissions;
    workPanel.hidden = !result.permissions.includes("customers.view");
    staffPanel.hidden = !result.permissions.includes("users.manage");
    document.querySelector("#catalog-panel").hidden = !result.permissions.includes("catalog.view");
    document.querySelector("#catalog-management").hidden = !result.permissions.includes("catalog.manage");
    document.querySelector("#orders-panel").hidden = !(
        result.permissions.includes("orders.view") || result.permissions.includes("orders.create")
    );
    document.querySelector("#order-form").hidden = !result.permissions.includes("orders.create");
    document.querySelector("#orders-list").hidden = !result.permissions.includes("orders.view");
    document.querySelector("#payments-panel").hidden = !result.permissions.includes("payments.record");
    document.querySelector("#payment-form").hidden = !result.permissions.includes("payments.record");
    document.querySelector("#inventory-panel").hidden = !result.permissions.includes("inventory.view");
    document.querySelector("#opening-form").hidden = !result.permissions.includes("inventory.manage");
    document.querySelector("#adjustment-form").hidden = !result.permissions.includes("inventory.manage");
    document.querySelector("#production-panel").hidden = !result.permissions.includes("production.manage");
    document.querySelector("#expenses-panel").hidden = !result.permissions.includes("expenses.record");
    const canReport = result.permissions.includes("reports.operational") || result.permissions.includes("reports.financial");
    document.querySelector("#reports-panel").hidden = !canReport;
    document.querySelector("#audit-panel").hidden = !result.permissions.includes("audit.view");
    document.querySelector("#history-panel").hidden = !result.permissions.includes("history.manage");
    document.querySelector("#history-batch-form").hidden = !result.permissions.includes("history.manage");
    document.querySelector("#opening-date").value = currentLocalDate();
    document.querySelector("#expense-date").value = currentLocalDate();
    const firstOfMonth = `${currentLocalDate().slice(0, 7)}-01`;
    document.querySelector("#report-from").value ||= firstOfMonth;
    document.querySelector("#report-to").value ||= currentLocalDate();
    document.querySelector("#history-date").value = currentLocalDate();

    if (!workPanel.hidden) {
        try {
            await loadGuardians();
        } catch (error) {
            showNotice(error.message, true);
        }
    }
    if (!document.querySelector("#catalog-panel").hidden) {
        try {
            await loadCatalog();
        } catch (error) {
            showNotice(error.message, true);
        }
    }
    if (result.permissions.includes("orders.view")) {
        try {
            await loadOrders();
        } catch (error) {
            showNotice(error.message, true);
        }
    }
    if (!document.querySelector("#payments-panel").hidden) {
        try {
            await loadPayments();
        } catch (error) {
            showNotice(error.message, true);
        }
    }
    if (!document.querySelector("#inventory-panel").hidden) {
        try {
            await loadInventory();
        } catch (error) {
            showNotice(error.message, true);
        }
    }
    if (!document.querySelector("#production-panel").hidden) {
        try {
            await loadProduction();
        } catch (error) {
            showNotice(error.message, true);
        }
    }
    if (!document.querySelector("#expenses-panel").hidden) {
        try {
            await loadExpenses();
        } catch (error) {
            showNotice(error.message, true);
        }
    }
    if (canReport) {
        try {
            await loadReport();
        } catch (error) {
            showNotice(error.message, true);
        }
    }
    if (!document.querySelector("#audit-panel").hidden) {
        try {
            await loadAudit();
        } catch (error) {
            showNotice(error.message, true);
        }
    }
    if (!document.querySelector("#history-panel").hidden) {
        try {
            await loadHistory();
        } catch (error) {
            showNotice(error.message, true);
        }
    }
    if (!staffPanel.hidden) {
        try {
            await loadUsers();
        } catch (error) {
            showNotice(error.message, true);
        }
    }
}

document.querySelector("#login-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const button = form.querySelector("button");
    button.disabled = true;

    try {
        await readJson(await fetch("/api/login", {
            method: "POST",
            credentials: "same-origin",
            headers: { "content-type": "application/json" },
            body: JSON.stringify({
                login: form.elements.login.value,
                password: form.elements.password.value,
            }),
        }));
        form.reset();
        showNotice("");
        await loadAccount();
    } catch (error) {
        showNotice(error.message, true);
    } finally {
        button.disabled = false;
    }
});

document.querySelector("#logout-button").addEventListener("click", async () => {
    try {
        await api("/api/logout", {
            method: "POST",
        });
        await loadAccount();
        showNotice("تم تسجيل الخروج.");
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#guardian-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    try {
        await api("/api/guardians", {
            method: "POST",
            body: JSON.stringify(Object.fromEntries(new FormData(form))),
        });
        form.reset();
        await loadGuardians(document.querySelector("#guardian-search").value);
        showNotice("تم حفظ ملف ولي الأمر.");
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#guardian-search").addEventListener("input", async (event) => {
    try {
        await loadGuardians(event.currentTarget.value);
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#guardian-list").addEventListener("click", async (event) => {
    const button = event.target.closest("button[data-action='open-guardian']");
    if (!button) return;
    try {
        await loadGuardian(button.dataset.id);
        document.querySelector("#guardian-detail").scrollIntoView({ behavior: "smooth", block: "nearest" });
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#child-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    if (selectedGuardianId === null) return;

    const form = event.currentTarget;
    try {
        await api(`/api/guardians/${selectedGuardianId}/children`, {
            method: "POST",
            body: JSON.stringify(Object.fromEntries(new FormData(form))),
        });
        form.reset();
        await Promise.all([
            loadGuardian(selectedGuardianId),
            loadGuardians(document.querySelector("#guardian-search").value),
        ]);
        showNotice("تمت إضافة الطفل.");
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#child-list").addEventListener("click", async (event) => {
    const button = event.target.closest("button[data-action='measure-child']");
    if (!button) return;

    const names = {
        shoulder_cm: "الكتف",
        chest_cm: "الصدر",
        sleeve_cm: "الكم",
        waist_circumference_cm: "محيط الوسط",
        length_cm: "الطول",
    };
    const measurements = {};
    for (const [field, label] of Object.entries(names)) {
        const value = window.prompt(`أدخل قياس ${label} بالسنتيمتر، أو اتركه فارغاً:`);
        if (value === null) return;
        measurements[field] = value === "" ? null : Number(value);
    }

    try {
        await api(`/api/children/${button.dataset.id}/measurements`, {
            method: "POST",
            body: JSON.stringify(measurements),
        });
        await loadGuardian(selectedGuardianId);
        showNotice("تم تحديث المقاسات الحالية. الطلبات القديمة تحتفظ بلقطاتها.");
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#archive-guardian").addEventListener("click", async () => {
    if (selectedGuardianId === null || !window.confirm("أرشفة ملف ولي الأمر مع الاحتفاظ بالسجلات؟")) return;

    try {
        await api(`/api/guardians/${selectedGuardianId}/archive`, { method: "POST" });
        selectedGuardianId = null;
        document.querySelector("#guardian-detail").hidden = true;
        await loadGuardians(document.querySelector("#guardian-search").value);
        showNotice("تمت أرشفة الملف.");
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#staff-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    try {
        await api("/api/users", {
            method: "POST",
            body: JSON.stringify(Object.fromEntries(new FormData(form))),
        });
        form.reset();
        await loadUsers();
        showNotice("تم إنشاء الحساب. أرسل بيانات الدخول عبر قناة آمنة.");
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#staff-list").addEventListener("click", async (event) => {
    const button = event.target.closest("button[data-action='save-staff']");
    if (!button) return;

    const item = button.closest("li");
    try {
        await api(`/api/users/${button.dataset.id}`, {
            method: "PATCH",
            body: JSON.stringify({
                name: button.dataset.name,
                username: button.dataset.username,
                email: button.dataset.email || null,
                role: item.querySelector("[data-field='role']").value,
                is_active: item.querySelector("[data-field='is_active']").checked,
            }),
        });
        await loadUsers();
        showNotice("تم تحديث الحساب.");
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#catalog-management").addEventListener("submit", async (event) => {
    const form = event.target.closest("form");
    if (!form) return;
    event.preventDefault();

    const routeByForm = {
        "school-form": "/api/catalog/schools",
        "grade-form": "/api/catalog/grades",
        "garment-form": "/api/catalog/garment-types",
        "variant-form": "/api/catalog/variants",
    };
    const route = routeByForm[form.id];
    if (!route) return;

    const data = Object.fromEntries(new FormData(form));
    if (form.id === "garment-form") {
        data.requires_size = form.elements.requires_size.checked;
        data.requires_color = form.elements.requires_color.checked;
    }
    try {
        await api(route, { method: "POST", body: JSON.stringify(data) });
        form.reset();
        await loadCatalog();
        showNotice("تمت إضافة البيانات إلى الكتالوج.");
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#catalog-list").addEventListener("click", async (event) => {
    const button = event.target.closest("button[data-action^='deactivate:']");
    if (!button || !window.confirm("إيقاف هذا العنصر؟ ستبقى السجلات السابقة محفوظة.")) return;
    const route = button.dataset.action.slice("deactivate:".length);
    try {
        await api(`/api/catalog/${route}/${button.dataset.id}/deactivate`, { method: "POST" });
        await loadCatalog();
        showNotice("تم إيقاف العنصر.");
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#order-type").addEventListener("change", (event) => {
    document.querySelector("#order-variant").required = event.currentTarget.value === "ready";
});

document.querySelector("#order-guardian").addEventListener("change", async (event) => {
    const guardianId = event.currentTarget.value;
    if (!guardianId) {
        setOptions(document.querySelector("#order-child"), [], () => "", "دون طفل محدد");
        return;
    }
    try {
        await loadGuardian(guardianId);
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#order-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    try {
        const data = Object.fromEntries(new FormData(form));
        const created = await api("/api/orders", {
            method: "POST",
            body: JSON.stringify({
                guardian_id: data.guardian_id,
                type: data.type,
                due_on: data.due_on || null,
                items: [{
                    description: data.description,
                    child_id: data.child_id || null,
                    garment_type_id: data.garment_type_id || null,
                    inventory_variant_id: data.inventory_variant_id || null,
                    quantity: Number(data.quantity),
                    unit_price: data.unit_price,
                    discount: data.discount || "0",
                }],
            }),
        });
        form.reset();
        document.querySelector("#order-quantity").value = "1";
        document.querySelector("#order-discount").value = "0";
        document.querySelector("#order-variant").required = false;
        await loadOrders();
        if (currentPermissions.includes("orders.view")) await loadOrder(created.id);
        showNotice(`تم إنشاء الطلب ${created.number}.`);
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#orders-list").addEventListener("click", async (event) => {
    const button = event.target.closest("button[data-action='open-order']");
    if (!button) return;
    try {
        await loadOrder(button.dataset.id);
        document.querySelector("#order-detail").scrollIntoView({ behavior: "smooth", block: "nearest" });
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#delivery-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    try {
        const data = Object.fromEntries(new FormData(form));
        const items = [...form.querySelectorAll("[data-order-item-id]")].map((input) => ({
            order_item_id: Number(input.dataset.orderItemId),
            quantity_delivered: Number(input.value),
        }));
        await api(`/api/orders/${form.dataset.orderId}/deliveries`, {
            method: "POST",
            body: JSON.stringify({
                received_by_name: data.received_by_name,
                delivered_at: new Date(data.delivered_at).toISOString(),
                items,
            }),
        });
        await loadOrder(form.dataset.orderId);
        await loadOrders();
        showNotice("تم تسجيل التسليم.");
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#payment-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    try {
        const data = Object.fromEntries(new FormData(form));
        await api("/api/payments", {
            method: "POST",
            body: JSON.stringify({
                order_id: data.order_id,
                amount: data.amount,
                method: data.method,
                paid_at: new Date(data.paid_at).toISOString(),
                transaction_reference: data.transaction_reference || null,
            }),
        });
        form.reset();
        await Promise.all([loadPayments(), loadOrders()]);
        showNotice("تم تسجيل الدفعة. التحويل البنكي يظل معلقاً حتى اعتماده.");
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#payments-list").addEventListener("click", async (event) => {
    const button = event.target.closest("button[data-action='verify-payment']");
    if (!button || !window.confirm("اعتماد استلام هذا التحويل البنكي؟")) return;
    try {
        await api(`/api/payments/${button.dataset.id}/verify`, { method: "POST" });
        await loadPayments();
        showNotice("تم اعتماد التحويل البنكي.");
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#opening-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const data = Object.fromEntries(new FormData(form));
    try {
        const result = await api("/api/inventory/opening-batches", {
            method: "POST",
            body: JSON.stringify({
                counted_on: data.counted_on,
                entries: [{
                    inventory_variant_id: data.inventory_variant_id,
                    quantity_counted: Number(data.quantity_counted),
                    difference_reason: data.difference_reason || null,
                }],
            }),
        });
        form.reset();
        document.querySelector("#opening-date").value = currentLocalDate();
        await loadInventory();
        showNotice(`حُفظت دفعة الجرد ${result.reference} كمسودة؛ لا تدخل في الرصيد قبل اعتمادها.`);
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#adjustment-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const data = Object.fromEntries(new FormData(form));
    try {
        const result = await api("/api/inventory/adjustments", {
            method: "POST",
            body: JSON.stringify({
                reason: data.reason,
                items: [{
                    inventory_variant_id: data.inventory_variant_id,
                    quantity_delta: Number(data.quantity_delta),
                    reason: data.reason,
                }],
            }),
        });
        form.reset();
        await loadInventory();
        showNotice(`أُرسل طلب التصحيح ${result.reference} للاعتماد.`);
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#opening-list").addEventListener("click", async (event) => {
    const button = event.target.closest("button[data-action='approve-opening']");
    if (!button || !window.confirm("اعتماد هذه الدفعة وإضافة كمياتها إلى الأرصدة؟")) return;
    try {
        await api(`/api/inventory/opening-batches/${button.dataset.id}/approve`, { method: "POST", body: "{}" });
        await loadInventory();
        showNotice("اعتُمد الرصيد الافتتاحي وسُجلت حركاته.");
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#adjustment-list").addEventListener("click", async (event) => {
    const button = event.target.closest("button[data-action='approve-adjustment'], button[data-action='reject-adjustment']");
    if (!button) return;
    const reject = button.dataset.action === "reject-adjustment";
    const rejectionReason = reject ? window.prompt("سبب رفض التصحيح:") : null;
    if (reject && (!rejectionReason || !rejectionReason.trim())) return;
    if (!reject && !window.confirm("اعتماد تصحيح المخزون وتسجيل الحركات؟")) return;
    try {
        await api(`/api/inventory/adjustments/${button.dataset.id}/${reject ? "reject" : "approve"}`, {
            method: "POST",
            body: JSON.stringify(reject ? { rejection_reason: rejectionReason.trim() } : {}),
        });
        await loadInventory();
        showNotice(reject ? "رُفض طلب التصحيح وسُجل السبب." : "اعتُمد تصحيح المخزون.");
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#production-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const data = Object.fromEntries(new FormData(form));
    try {
        await api("/api/production/jobs", {
            method: "POST",
            body: JSON.stringify({
                order_item_id: data.order_item_id,
                quantity: Number(data.quantity),
                expected_on: data.expected_on || null,
            }),
        });
        form.reset();
        await loadProduction();
        showNotice("تم إنشاء سجل الإنتاج.");
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#production-list").addEventListener("click", async (event) => {
    const button = event.target.closest("button[data-action^='production-']");
    if (!button) return;
    try {
        if (button.dataset.action === "production-stage") {
            const stage = window.prompt("المرحلة: not_started / sewing / with_tailor / waiting_completion / completed", "sewing");
            if (!stage) return;
            const reason = window.prompt("سبب تغيير المرحلة:");
            if (!reason?.trim()) return;
            await api(`/api/production/jobs/${button.dataset.id}/stage`, {
                method: "PATCH",
                body: JSON.stringify({ stage, reason: reason.trim() }),
            });
        } else if (button.dataset.action === "production-dispatch") {
            const recipient = window.prompt("اسم الترزي المستلم:");
            if (!recipient?.trim()) return;
            const quantity = Number(window.prompt("الكمية المرسلة:", "1"));
            const agreedCost = window.prompt("تكلفة متفق عليها (اختياري):");
            await api(`/api/production/jobs/${button.dataset.id}/dispatches`, {
                method: "POST",
                body: JSON.stringify({
                    recipient_name: recipient.trim(),
                    sent_on: currentLocalDate(),
                    quantity_sent: quantity,
                    agreed_cost: agreedCost || null,
                }),
            });
        } else if (button.dataset.action === "production-receipt") {
            const quantity = Number(window.prompt(`الكمية المستلمة (المتبقي ${button.dataset.remaining}):`, button.dataset.remaining));
            await api(`/api/production/dispatches/${button.dataset.id}/receipts`, {
                method: "POST",
                body: JSON.stringify({
                    received_on: currentLocalDate(),
                    items: [{ dispatch_item_id: button.dataset.dispatchItemId, quantity_received: quantity }],
                }),
            });
        }
        await loadProduction();
        if (currentPermissions.includes("orders.view")) await loadOrders();
        showNotice("تم تحديث سجل الإنتاج أو الترزي.");
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#expenses-panel").addEventListener("submit", async (event) => {
    if (event.target.id !== "expense-form") return;
    event.preventDefault();
    const form = event.target;
    const data = Object.fromEntries(new FormData(form));
    try {
        await api("/api/expenses", {
            method: "POST",
            body: JSON.stringify({
                expense_category_id: data.expense_category_id,
                amount: data.amount,
                spent_on: data.spent_on,
                description: data.description,
            }),
        });
        form.reset();
        document.querySelector("#expense-date").value = currentLocalDate();
        await loadExpenses();
        showNotice("تم تسجيل المصروف.");
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#report-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    try {
        await loadReport();
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#history-batch-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const data = Object.fromEntries(new FormData(form));
    try {
        const batch = await api("/api/history", {
            method: "POST",
            body: JSON.stringify(data),
        });
        form.reset();
        document.querySelector("#history-date").value = currentLocalDate();
        await loadHistory();
        await loadHistoryBatch(batch.id);
        showNotice("أُنشئت دفعة الترحيل.");
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#history-batches").addEventListener("click", async (event) => {
    const button = event.target.closest("button[data-action='open-history']");
    if (!button) return;
    try {
        await loadHistoryBatch(button.dataset.id);
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#history-record-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    if (selectedHistoryBatchId === null) return;
    const data = Object.fromEntries(new FormData(event.currentTarget));
    try {
        await api(`/api/history/${selectedHistoryBatchId}/records`, {
            method: "POST",
            body: JSON.stringify(data),
        });
        event.currentTarget.reset();
        await Promise.all([loadHistory(), loadHistoryBatch(selectedHistoryBatchId)]);
        showNotice("أُضيف السجل إلى قائمة المراجعة.");
    } catch (error) {
        showNotice(error.message, true);
    }
});

document.querySelector("#history-records").addEventListener("click", async (event) => {
    const button = event.target.closest("button[data-action='review-history']");
    if (!button) return;
    const notes = window.prompt("ملاحظات مراجعة المدير:");
    if (!notes?.trim()) return;
    try {
        await api(`/api/history/records/${button.dataset.id}/review`, {
            method: "PATCH",
            body: JSON.stringify({ review_status: "reviewed", review_notes: notes.trim() }),
        });
        await Promise.all([loadHistory(), loadHistoryBatch(selectedHistoryBatchId)]);
        showNotice("تمت مراجعة السجل.");
    } catch (error) {
        showNotice(error.message, true);
    }
});

await loadAccount();
