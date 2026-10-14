INSERT INTO roles (name, label) VALUES
    ('manager', 'مدير'),
    ('employee', 'موظف')
ON CONFLICT (name) DO NOTHING;

INSERT INTO permissions (name, label) VALUES
    ('users.manage', 'إدارة المستخدمين والصلاحيات'),
    ('customers.view', 'عرض أولياء الأمور والأطفال'),
    ('customers.create', 'إنشاء أولياء الأمور والأطفال'),
    ('customers.update', 'تعديل أولياء الأمور والأطفال'),
    ('measurements.view', 'عرض المقاسات'),
    ('measurements.update', 'تسجيل المقاسات'),
    ('catalog.view', 'عرض المدارس والأصناف'),
    ('catalog.manage', 'إدارة المدارس والأصناف'),
    ('orders.view', 'عرض الطلبات'),
    ('orders.create', 'إنشاء الطلبات'),
    ('orders.update', 'تعديل البيانات التشغيلية للطلبات'),
    ('production.manage', 'إدارة الإنتاج والترزي'),
    ('deliveries.create', 'تسجيل التسليم بعد تحقق الشروط'),
    ('inventory.view', 'عرض المخزون'),
    ('inventory.sell', 'بيع المخزون الجاهز'),
    ('inventory.manage', 'اعتماد الجرد وتصحيح المخزون'),
    ('payments.record', 'تسجيل الدفعات'),
    ('payments.verify', 'اعتماد التحويلات البنكية'),
    ('finance.view', 'عرض البيانات المالية'),
    ('expenses.record', 'تسجيل المصروفات'),
    ('orders.cancel', 'اعتماد إلغاء الطلب'),
    ('refunds.approve', 'اعتماد الاستردادات'),
    ('audit.view', 'عرض سجل التدقيق'),
    ('history.correct', 'تصحيح السجلات التاريخية الحساسة'),
    ('history.manage', 'إدارة سجل ترحيل البيانات التاريخية ومراجعته'),
    ('reports.operational', 'عرض التقارير التشغيلية'),
    ('reports.financial', 'عرض التقارير المالية')
ON CONFLICT (name) DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
CROSS JOIN permissions
WHERE roles.name = 'manager'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
JOIN permissions ON permissions.name IN (
    'customers.view', 'customers.create', 'customers.update',
    'measurements.view', 'measurements.update', 'catalog.view',
    'orders.view', 'orders.create', 'orders.update',
    'production.manage', 'deliveries.create', 'inventory.view', 'inventory.sell',
    'payments.record', 'expenses.record', 'reports.operational'
)
WHERE roles.name = 'employee'
ON CONFLICT DO NOTHING;

INSERT INTO garment_types (name, requires_size, requires_color, is_active, created_at, updated_at)
VALUES
    ('قمصان', 1, 0, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('بناطيل', 1, 0, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('تنانير', 1, 0, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('زي كامل أو مريلة', 1, 0, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('أخرى', 1, 0, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON CONFLICT (name) DO NOTHING;

INSERT INTO expense_categories (name, is_active, created_at, updated_at)
VALUES
    ('أجور الترزي والخياطة', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('النقل', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('الخامات', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('المصروفات التشغيلية', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('أخرى', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON CONFLICT (name) DO NOTHING;

CREATE TABLE auth_login_attempts (
    identity_hash TEXT PRIMARY KEY,
    attempts INTEGER NOT NULL DEFAULT 0,
    window_started_at INTEGER NOT NULL
);
