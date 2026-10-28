# توافق الاستضافة المجانية

**تاريخ البحث:** 14 أكتوبر 2026. فُحصت صفحات ووثائق عامة فقط؛ لم يُنشأ حساب أو تطبيق تجريبي، ولم يختبر الوصول من السودان أو قاعدة بيانات حقيقية. الخصائص المجانية قابلة للتغيير. دعم PHP لا يثبت دعم Laravel.

## المتطلبات التقنية

Laravel 13 يتطلب PHP 8.3+ وامتدادات ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, PDO, session, tokenizer, xml وفق وثائق النشر. يجب أن يكون جذر الويب `public` لا جذر المشروع، مع كتابة `storage` و`bootstrap/cache`. يلزم تجهيز Composer محلياً أو تشغيله على الخادم، قاعدة PDO متوافقة، HTTPS، ورفع آمن. النسخ اليومية واختبار الاستعادة تحتاجان جدولة أو إجراءً موثقاً؛ طلب URL من خدمة خارجية ليس مكافئاً تلقائياً لـCron.

يضيف تصدير PDF حزمة `tecnickcom/tc-lib-pdf` (8.80.1) ومتطلبات PHP 8.2+ وComposer وامتدادات `mbstring`, `zlib`, `xml`, `json`؛ ملفات DejaVu المرخصة مرفقة لتصدير PDF؛ Tajawal المحلي ينسخ إلى أصول الواجهة عبر Vite. PDF لا يستخدم Tajawal بعد لأن علامات اتجاهه تظهر كمحارف مربعة في الاختبار البصري. النسخ الخارجي يستخدم محول `league/flysystem-aws-s3-v3` مع إعداد قرص S3-compatible؛ ثبتت الحزمة واختبر إنشاء adapter بإعداد اصطناعي، ولم يُختبر باتصال مزود فعلي. يلزم رفع حزمة `vendor` مبنية محلياً كما في Laravel، لأن مزودي المجاني لا يثبتون Composer على الخادم وفق المعلومات المتاحة.

## ما جرى فحصه

| الخيار | ما تؤكده المصادر | القيود والمجهول | التقييم |
|---|---|---|---|
| **InfinityFree مجاني** | صفحة المزود تعلن PHP 8.4 وMySQL 8/MariaDB 11.4 وSSL وFTP/مدير ملفات. الدعم المنشور يقول لا SSH ولا Composer/CLI على الخادم ولا Cron مجاني. دليل المنتدى الخاص بـLaravel يقول إن جذر الويب ثابت `htdocs`، ويوضح رفع الاعتماديات المبنية محلياً وقواعد rewrite واستيراد المخطط عبر phpMyAdmin. | لم تتحقق الامتدادات أو حجم الملف/المهل/الموارد أو الوصول من السودان أو نسخة خارجية آلية. نشر كامل الشجرة داخل `htdocs` يتطلب اختبار رفض الوصول إلى `.env` و`vendor` والسجلات. شروط النسخ الخارجي تحتاج مراجعة. | تجربة فقط، لا إنتاج لبيانات حقيقية حالياً. |
| **AwardSpace مجاني/مشترك** | صفحات المزود تذكر PHP/MySQL؛ جدول الاستضافة المشتركة يعرض SSH/Cron لكن لا يثبت أنها ضمن المجاني. FAQ المجاني يذكر إصدارات PHP 5/7 وMySQL 5.7، ويذكر FAQ قديم تعطيل `exec` و`curl_init`. | معلومات الخطة المجانية قديمة/متعارضة؛ لم يثبت PHP 8.3 أو الامتدادات أو Composer/SSH/Cron في المجاني أو PostgreSQL أو الوصول من السودان. لا نستخدم مواصفات المدفوع كدليل للمجاني. | غير مؤهل حتى تحقق مباشر. |
| **استضافة PHP مشتركة غير محددة** | يمكن لبعض الخطط تشغيل أطر PHP عند دعم المتطلبات وجذر آمن ورفع بناء محلي. | لا خطة بعينها فُحصت؛ CLI/Composer/Cron/الملفات تختلف. | اختر بعد اختبار فعلي لا بالاسم. |
| **Vercel** | لم يُعتمد. | لم يثبت بهذا البحث دعم PHP/Laravel وقاعدة علائقية بخطة مجانية مناسبة. | غير موصى به. |

## التوصية

1. حافظ على تطبيق PHP خادمي وقاعدة علائقية وإعدادات بيئية وملفات قابلة للنقل.
2. Laravel + Blade مرشح أول للصيانة والتعلم بشرط خطة تثبت المتطلبات وجذر `public` أو عزلاً آمناً للملفات الخاصة، وتحديث الاعتماديات والترحيلات.
3. بناء Composer محلياً قد يتجاوز غياب CLI، لكنه لا يحل تلقائياً مشكلات جذر الويب أو النسخ أو قيود الخدمة.
4. إذا تعذر الجذر الآمن، قيّم CodeIgniter 4 كبديل أخف ذي وثائق للنشر المشترك وفصل الملفات؛ اختبر قبل أي قرار.
5. لا تعتمد InfinityFree أو AwardSpace للإنتاج الحساس قبل تجربة اصطناعية تختبر عزل الملفات والامتدادات والرفع والترحيل والنسخ والاستعادة والوصول من السودان.
6. عند غياب Cron، يمكن تصدير يدوي مشفر مؤقتاً وفق إجراء موثق، لكن هذا لا يحقق RPO 24 ساعة ولا الاحتفاظ اليومي/الأسبوعي/الشهري ولا اختبار الاستعادة الشهري. أي أتمتة مجانية تحتاج التحقق من التشفير والخصوصية والسياسة ولا تعد منجزة الآن.

## قائمة تحقق قبل الاختيار

- تجربة اصطناعية تثبت جذر الويب ورفض الوصول المباشر إلى الملفات الحساسة.
- فحص إصدار PHP والامتدادات وPDO الفعلية.
- حصة/اتصالات قاعدة البيانات وإمكان تصديرها واستعادتها.
- HTTPS والرفع والحجم والنوع وتخزين الملفات خارج العام.
- تثبيت الاعتماديات والترحيلات دون SSH وآلية نشر رجوعي.
- حدود الطلب والمهلة والتخزين وقياس 50 متزامناً.
- Cron أو بديل آمن، واستعادة ناجحة.
- التسجيل والوصول المستقر من السودان وشروط معالجة البيانات.

## المصادر

- [Laravel 13 deployment](https://laravel.com/framework/docs/deployment)
- [InfinityFree free hosting](https://www.infinityfree.com/)
- [InfinityFree support: Laravel on free hosting](https://forum.infinityfree.com/t/how-to-install-a-laravel-site-on-infinityfree/118578)
- [InfinityFree support: Cron disabled](https://forum.infinityfree.com/t/cron-job-feature-is-now-disabled/80495)
- [AwardSpace shared hosting table](https://www.awardspace.com/web-hosting/shared-hosting/)
- [AwardSpace free hosting FAQ (قديمة/قد تتغير)](https://www.awardspace.com/free-hosting/?product=marketing)
- [AwardSpace FAQ](https://www.awardspace.com/faq.html)
- [CodeIgniter 4 requirements](https://codeigniter.com/user_guide/intro/requirements.html)
- [CodeIgniter 4 shared-hosting deployment](https://codeigniter.com/user_guide/installation/deployment.html)
- [CodeIgniter 4 installation](https://codeigniter.com/user_guide/installation/)
