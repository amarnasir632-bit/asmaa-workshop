# مشغل أسماء

نظام إدارة الطلبات والإنتاج والمخزون والمبيعات لمشغل أسماء. التطبيق عربي RTL ومبني على Laravel وBlade وTailwind CSS وقاعدة علائقية. متطلبات النظام المرجعية في `SRS.md`؛ انظر `docs/IMPLEMENTATION-STATUS.md` للحالة الفعلية والقيود.

## الحالة

التطبيق قيد التنفيذ المحلي. migrations تختبر داخل SQLite المؤقت، ولا توجد قاعدة تشغيل أو بيانات حقيقية أو نشر عام. راجع `docs/IMPLEMENTATION-STATUS.md` للتغطية والقرارات المتبقية.

بدأ مسار نقل منفصل إلى Cloudflare Workers وD1. تتضمن النسخة الحالية مخطط البيانات والمصادقة وواجهات API أولية لمعظم وظائف التشغيل، لكنها لا تملك تكافؤاً وظيفياً مع Laravel ولا تصلح لبيانات حقيقية أو استخدام تشغيلي. لم تُنشر على Cloudflare، وما زالت وظائف الواجهة والاستيراد والتقارير والمرفقات والنسخ بحاجة إكمال واختبار. اتبع [دليل النقل](docs/CLOUDFLARE-MIGRATION.md) و[حالة التنفيذ](docs/IMPLEMENTATION-STATUS.md).

## المتطلبات المحلية على Fedora

- PHP 8.3 أو أحدث، مع `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `curl`.
- Composer 2.
- MySQL 8 أو MariaDB متوافق، أو SQLite للاختبارات فقط.
- Node.js إصدار حديث متوافق مع Vite وnpm لبناء CSS/JS.

تحقق من البيئة:

```bash
php -v
php -m
composer --version
node --version
npm --version
mysql --version
```

## تشغيل محلي بعد إعداد MySQL

1. أنشئ قاعدة ومستخدم تطبيق محلياً بصلاحيات محدودة، ثم انسخ `.env.example` إلى `.env` واضبط `DB_*` و`APP_URL`.
2. ثبّت حزم PHP وNode وابنِ ملفات الواجهة.
3. أنشئ مفتاح التطبيق، ثم نفذ migrations على القاعدة المحلية فقط.
4. أنشئ بيانات الأدوار الأساسية، ثم أول حساب مدير من أمر CLI التفاعلي؛ لا توجد كلمة مرور افتراضية.

```bash
cp .env.example .env
composer install
npm ci
npm run build
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan app:create-manager
php artisan serve
```

استخدم `php artisan test` لاختبارات PHP. لا تشغل `migrate:fresh` على قاعدة بيانات فيها بيانات تحتاج الاحتفاظ بها.

## النسخ الاحتياطي والاستعادة

تتوفر أوامر لإنشاء نسخة مشفرة بـAES-256-GCM تشمل بيانات قاعدة البيانات والملفات الخاصة، مع الاحتفاظ بـ7 يومية و4 أسبوعية و3 شهرية. يتطلب التشغيل مفتاحاً سرياً في `APP_BACKUP_KEY`. اختر وسيطاً خارجياً متوافقاً مع S3 عبر إعدادات `AWS_*` و`BACKUP_DISK=s3` قبل بيانات الإنتاج؛ الإعداد الافتراضي محلي ولا يحقق نسخة خارج الاستضافة.

```bash
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
php artisan workshop:backup --frequency=daily
php artisan schedule:list
php artisan schedule:run
php artisan workshop:backup-restore daily/اسم-النسخة.backup
```

أمر الاستعادة يطلب تأكيدين ويمحو بيانات القاعدة والملفات الخاصة الحالية قبل استبدالها. جُرّبت الاستعادة على قاعدة SQLite مؤقتة فقط. لا تعتبر RPO/RTO أو تشغيل الجدولة اليومية متحققاً قبل تكوين وسيط خارجي ومهمة cron واختبار الاستعادة شهرياً. احتفظ بمفتاح النسخ خارج المستودع وفي مكان مستقل عن النسخ؛ فقدانه يمنع فكها.

## فحوص GitHub

ملف `.github/workflows/quality.yml` يشغل اختبارات SQLite والبناء وPint وComposer، ثم يشغل الاختبارات على MySQL 8.4. لم نستطع تشغيل خدمة MySQL في بيئة البناء الحالية؛ نتيجة MySQL الفعلية ستظهر تلقائياً بعد رفع المشروع إلى GitHub. لا يحتوي workflow على أسرار إنتاج.

## التصميم والوثائق

- مراجعة SRS: `docs/REQUIREMENTS-REVIEW.md`
- نموذج البيانات: `docs/DATABASE-DESIGN.md`
- الصلاحيات: `docs/ACCESS-MATRIX.md`
- التوافق والاستضافة: `docs/HOSTING-COMPATIBILITY.md`
- خطة التنفيذ: `docs/IMPLEMENTATION-PLAN.md`
- دليل الهوية: `docs/BRAND-GUIDELINES.md`
- مصفوفة المتطلبات والاختبارات: `docs/REQUIREMENTS-MATRIX.md`
- خارطة الإكمال والتسليم: `docs/COMPLETION-ROADMAP.md`

لا ترفع `.env` أو مستندات مالية إلى Git. لا ترفع بيانات حقيقية قبل تجهيز النسخ الاحتياطي واختبار الاستعادة ومراجعة أمنية. لم يُعتمد مزود استضافة بعد.
# asmaa-workshop
