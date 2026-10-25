# تقرير التوافق مع InfinityFree (الخطة المجانية)

**تاريخ الفحص:** 17 أكتوبر 2026.
**نطاق الفحص:** مستودع المشروع الفعلي + معلومات عامة منشورة عن InfinityFree.
**لم يُنشأ حساب InfinityFree، ولم يُرفع أي ملف، ولم تُختبر الاستضافة فعلياً.**

## ملخص تنفيذي

المشروع **متوافق مبدئياً** مع InfinityFree من ناحية إصدار PHP وقاعدة البيانات، ويمكن بناء **حزمة نشر تجريبية** (تم بناؤها فعلاً: `build/infinityfree-htdocs.zip`).
لكن هناك ثلاثة قيود حقيقية يجب فهمها قبل الاستخدام:

1. **جذر الويب ثابت `htdocs`** ولا يمكن وضع ملفات Laravel خارجه → الحماية تعتمد على `.htaccess` (طبقات متعددة) وليست عزلاً فيزيائياً.
2. **لا SSH ولا Composer ولا Artisan ولا Cron** → الترحيل عبر ملف SQL، والنسخ الاحتياطي يدوي.
3. **تأكيد الامتدادات الفعلية على الخادم مطلوب** (`zip`, `fileinfo`, `pdo_mysql`, `mbstring`, `zlib`...) بعد إنشاء الحساب.

**الحكم:**
- **جاهزية بناء حزمة الرفع:** ✅ نعم.
- **نجاح النشر والتشغيل على InfinityFree:** ❌ لم يُختبر (يحتاج حساباً فعلياً من المستخدم).
- **جاهزية الاستخدام ببيانات حقيقية:** ❌ لا (تجريبي ببيانات اصطناعية فقط).

## جدول التوافق

| المتطلب | الحالة | الدليل من المستودع | الأثر على النشر | الحل المنفذ |
|---|---|---|---|---|
| إصدار PHP | ✅ Laravel 13.36 يتطلب PHP `^8.3`؛ الاستضافة أعلنت PHP 8.4 (سبتمبر 2026) | `composer.lock` (platform php `^8.3`)، `vendor/laravel/framework/composer.json` | لا أثر | لا شيء |
| امتدادات Laravel الأساسية | ⚠️ يجب التأكد على الخادم | `vendor/laravel/framework/composer.json`: ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, session, tokenizer, xml | تعطّل التطبيق إن غاب أحدها | أمر `app:host-check` + قائمة تحقق يدوية |
| امتداد `pdo_mysql` | ⚠️ يجب التأكد | `config/database.php` default `mysql` | لا اتصال بقاعدة البيانات | تأكيد عبر لوحة التحكم |
| امتداد `zip` (ZipArchive) | ⚠️ يجب التأكد | `app/Services/EncryptedBackupService.php` يستخدم `ZipArchive` | تعطّل النسخ الاحتياطي فقط (ليس الموقع) | موثّق كميزة اختيارية على الخطة المجانية |
| امتداد `zlib` (PDF) | ⚠️ يجب التأكد | `tecnickcom/tc-lib-pdf` يتطلب `ext-zlib` | تعطّل تصدير PDF فقط | تأكيد على الخادم |
| قاعدة البيانات | ✅ MySQL 8.0/MariaDB 11.4 | `config/database.php` (mysql/mariadb)، `.env.example` | لا أثر | MySQL هو الافتراضي |
| محرك التخزين | ⚠️ افتراضي الاستضافة MyISAM | `config/database.php` + `sql/01-schema.sql` | خطأ `#1071` عند الاستيراد | فرض `ENGINE=InnoDB ROW_FORMAT=DYNAMIC` صريحاً |
| migrations على MySQL | ✅ DDL يُولَّد بقواعد MySQL | 221 عبارة DDL ولّدتها `MySqlGrammar` في `sql/01-schema.sql` | لا يمكن تشغيل Artisan على الخادم | استيراد SQL عبر phpMyAdmin |
| تشغيل migrations فعلياً على MySQL | ✅ نُفّذ على MariaDB 11.4.13 | 52/52 اختباراً نجحت، واستيراد 47 جدولاً بلا أخطاء | لا أثر | لا شيء |
| اعتماديات Composer | ✅ تُبنى محلياً | `composer install --no-dev` نجح محلياً | لا Composer على الخادم | تضمين `vendor` الإنتاجية في الحزمة |
| بناء Vite/Node | ✅ يُبنى محلياً | `public/build/manifest.json` + أصول | لا Node على الخادم | تضمين `build` في الحزمة |
| الجلسات | ✅ `database` driver | `.env.example` `SESSION_DRIVER=database` | تعتمد على جدول `sessions` | يُنشأ ضمن المخطط المستورد |
| HTTPS والكوكيز | ✅ مضبوط | `env.production.example` + `URL::forceScheme` + `trustProxies` | كوكي آمن لا يعمل بدون HTTPS | تفعيل SSL المجاني في اللوحة |
| التخزين والملفات الخاصة | ⚠️ داخل `htdocs` | `config/filesystems.php` `private` → `storage/app/private` | خطر كشف الملفات إن غاب `.htaccess` | `.htaccess` يعتمد كطبقة حماية + اختبار يدوي |
| تنزيل المرفقات | ✅ بعد تفويض | `PaymentController::evidence` يفحص `finance.view` | لا أثر | مسار محمي بالخادم |
| PDF والعربية | ⚠️ توليد ناجح، التحقق البصري غير متوفر | توليد PDF محلياً نجح (73,901 بايت، `%PDF-`)؛ الخطوط في `resources/pdf-fonts/dejavu` | قد تكون بعض المحارف/الاتجاه غير مضبوطة بصرياً | الخطوط مضمّنة؛ يلزم فحص بصري بعد النشر |
| Queues | ✅ `sync` | `.env.example` | لا worker مطلوب | لا شيء |
| Scheduler/Cron | ❌ غير متوفر | `routes/console.php` يجدول النسخ | النسخ التلقائي لا يعمل | توثيق نسخ يدوي |
| الصلاحيات | ✅ RBAC خادمي | `config/permissions.php`، `EnsureActiveStaff`، Gates | لا أثر | لا شيء |
| جذر الويب `htdocs` | ⚠️ قيد المنصة | بنية الحزمة `htdocs/asmaa` + `htdocs/index.php` | لا يمكن وضع التطبيق خارج الجذر | تصميم الحزمة الحالي |
| `.env` والأسرار | ⚠️ داخل الجذر | `.gitignore` يستثني `.env`؛ الحزمة لا تتضمنه | يجب إنشاؤه على الخادم داخل `asmaa/` | منع الوصول عبر `.htaccess` |

## العقبات والقرارات

### 1. جذر الويب الثابت (`htdocs`)
لا تسمح الخطة المجانية بوضع ملفات خارج `htdocs` (و`open_basedir` يحصر الكتابة في `htdocs`). لذلك:
- يوضع التطبيق الكامل في `htdocs/asmaa/`.
- يُوضع فقط ما يجب أن يكون عاماً في `htdocs/`: `index.php`, `.htaccess`, `build/`, `icon.png`, `logo.png`, `favicon.ico`, `robots.txt`.
- يُمنع الوصول إلى `asmaa/` عبر `htdocs/asmaa/.htaccess` (`Require all denied`) وعبر قاعدة مطابقة في `htdocs/.htaccess`.

**الخطر المتبقي:** إذا تجاهل خادم InfinityFree ملفات `.htaccess` في المجلدات الفرعية، يصبح `asmaa/.env` و`asmaa/storage` و`asmaa/vendor` قابلاً للتنزيل. المنصة تعلن دعماً كاملاً لـ`.htaccess`، ويجب التأكد عملياً بعد الرفع (قسم الاختبار في `DEPLOYMENT-CHECKLIST.md`). لم يكن ممكناً تحقيق عزل فيزيائي لأن المنصة لا تسمح به.

### 2. غياب SSH/Composer/Artisan
كل عمليات البناء تتم على جهاز المطوّر، والترحيل عبر ملفات SQL. لهذا:
- `sql/01-schema.sql` (المخطط) و`sql/02-seed.sql` (الأدوار والصلاحيات والفئات — بلا مستخدمين).
- إنشاء المدير الأول عبر SQL مولّد محلياً من `tools/generate-manager-sql.php` (لا يوجد رابط عام للتهيئة).

### 3. غياب Cron
جدولة النسخ في `routes/console.php` لن تعمل. تُوثّق النسخ اليدوي، ولا يُعلن تحقق RPO/RTO.

### 4. حدود المنصة الفعلية (من مصادر عامة)
- مساحة 5 GB، حد **30,000 inode** لكل حساب، حد ملف 10 MB (رفع مدير الملفات)، 50,000 hit يومياً، حدود CPU/Entry-Process.
- حزمة الرفع الحالية: **~10,775 ملفاً** و**~21 MB** → داخل حد الـinodes، لكن رفع ملف ZIP بحجم 21 MB يتجاوز حد مدير الملفات (10 MB)، لذا يُرفع عبر FTP.

### 5. قاعدة البيانات: توافق MySQL لم يُختبر تنفيذياً
- المخطط مولّد بترجمة Laravel الرسمية `MySqlGrammar` (نفس ما يُنفّذ على MySQL)، ويشمل `ENUM` أصلية، و`utf8mb4_unicode_ci`، ومفاتيح أجنبية InnoDB.
- **لم يُنفّذ** الاستيراد أو الاختبارات على خادم MySQL/MariaDB فعلي (لا خادم في بيئة البناء). لا يجوز اعتبار نجاح اختبارات SQLite دليلاً على MySQL.

### 6. محرك التخزين الافتراضي MyISAM (خطأ `#1071` على الاستضافة)
اختبار الاستيراد الفعلي على InfinityFree أظهر:
```
#1071 - Specified key was too long; max key length is 1000 bytes
create table `password_reset_tokens` (... primary key (`email`)) ...
```
السبب: بعض الاستضافات تجعل `default_storage_engine=MyISAM` (حده 1000 بايت)، و`varchar(255)` بمحارف utf8mb4 = 1020 بايت. كذلك قد يجعل بعضها InnoDB بصيغة صف `COMPACT` (حده 767 بايت).
الحل المنفّذ: فرض `ENGINE=InnoDB ROW_FORMAT=DYNAMIC` صريحاً على كل الجداول في `01-schema.sql`، وإضافة `'engine' => env('DB_ENGINE', 'InnoDB')` في `config/database.php`. تم إعادة إنتاج الخطأ محلياً (فرض MyISAM) ثم التأكد أن المخطط المصحّح يستورد 47 جدولاً بلا أخطاء تحت MyISAM وInnoDB.

### 7. المجلدات الفارغة لا تُنشأ عند فك الضغط (خطأ `Please provide a valid cache path`)
اختبار الاستضافة الفعلي أظهر:
```
InvalidArgumentException: Please provide a valid cache path.
Illuminate/View/Compilers/Compiler.php (line 75)
```
السبب: `config/view.php` غير موجود في المستودع، فيُستخدم افتراضي الإطار
`env('VIEW_COMPILED_PATH', realpath(storage_path('framework/views')))`. و`realpath()` تُرجع `false` إذا كان المجلد غير موجود، فيصبح مسار Blade فارغاً. مدير ملفات InfinityFree لا يُنشئ المجلدات الفارغة عند فك الـZIP (`storage/framework/views`، `storage/app/private`... إلخ).
الحل المنفّذ: `htdocs/index.php` يُنشئ المجلدات المطلوبة (`bootstrap/cache`, `storage/app/private`, `storage/app/backups`, `storage/framework/{cache/data,sessions,views}`, `storage/logs`) عبر `mkdir` قبل إقلاع Laravel. وأُضيفت ملفات `.gitkeep` داخل كل مجلد في الحزمة. تم التحقق محلياً بحذف كل هذه المجلدات ثم تشغيل الموقع: الدخول والصفحات المحمية وتصدير PDF كلها نجحت.

## ما لم يُنفّذ ويجب التحقق منه

- الرفع والتشغيل الفعلي على InfinityFree (PHP 8.4، الامتدادات، `.htaccess`، HTTPS).
- الفحص البصري لتوليد PDF العربي (RTL/تشكيل الحروف).
- الفحص البصري للواجهة على المتصفح.
- النسخ الاحتياطي/الاستعادة على الاستضافة.

## ما نُفّذ فعلياً على MySQL/MariaDB

- استيراد المخطط الكامل (47 جدولاً) والبيانات الأساسية وحساب المدير بلا أخطاء، بما في ذلك تحت `default_storage_engine=MyISAM`.
- مجموعة الاختبارات كاملة: 52/52 نجحت، 364 assertion على MariaDB 11.4.13.
- تشغيل الحزمة الفعلية على MySQL: تسجيل الدخول، الصفحات المحمية، وتصدير PDF (`%PDF-`، 84KB).
