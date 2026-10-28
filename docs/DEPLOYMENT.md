# التطوير والنشر

## التطوير المحلي على Fedora

المشروع يحتاج PHP 8.3+، Composer 2، Node.js/npm، قاعدة MySQL 8/MariaDB متوافقة (أو SQLite للاختبارات)، وامتدادات PHP الظاهرة في Laravel deployment docs، ومنها `mbstring`, `zlib`, `xml`, `json` لتصدير PDF. بيئة المهمة الحالية وفرت PHP 8.5 وامتدادات PDO MySQL وmbstring وOpenSSL وfileinfo وغيرها؛ Composer كان غير مثبت وتم تنزيل PHAR رسمي والتحقق من توقيعه إلى `/tmp` لهذه الجلسة، وليس جزءاً من ملفات المشروع. استخدم Composer مثبتاً محلياً على جهازك.

```bash
php -v
php -m
composer --version
node --version
npm --version
```

خطوات التشغيل:

```bash
cp .env.example .env
# أنشئ قاعدة ومستخدم تطبيق محليين، ثم أضف بياناتهما إلى DB_* في .env
composer install
npm ci
npm run build
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan app:create-manager
php artisan serve
```

لا تستخدم حساب MySQL الإداري لتشغيل التطبيق. لا تنشئ مستخدم مدير بكلمة مرور افتراضية. `app:create-manager` يعمل من الطرفية فقط، ويُرفض إذا كان هناك مدير، ويأخذ كلمة المرور بسرية، ويسجل إنشاءه في التدقيق. migrations الحالية لم تُشغّل على قاعدة فعلية.

لإنشاء القاعدة يختلف الأمر بحسب إعداد Fedora المحلي؛ يجب ألا تمنح مستخدم التطبيق إلا صلاحيات القاعدة الخاصة بالمشروع اللازمة للتشغيل وترحيل التطوير. فعّل MySQL/MariaDB على جهازك وفق سياسة جهازك، ولا تُضمّن كلمة المرور في المستودع.

## إعداد الإنتاج (غير معتمد بعد)

لم يُختَر مزود استضافة نهائي. قبل الإطلاق، يجب إجراء تجربة نشر ببيانات اصطناعية وإثبات:

- PHP/الامتدادات، MySQL، TLS، وdocument root إلى `public`.
- بقاء `.env` و`vendor` و`storage/app/private` خارج الوصول العام.
- صلاحية كتابة `storage` و`bootstrap/cache` فقط للمستخدم المناسب.
- Composer/آلية build موثوقة، ترحيلات قابلة للمراجعة، نسخة رجوع، وحدود الطلبات والرفع.
- مكتبة PDF `tc-lib-pdf` وخطوط PDF العربية مجمعة؛ تأكد أن نسخة `vendor` المرفوعة تتضمن الحزم، وأن PHP على المضيف يتضمن الامتدادات الإلزامية.
- تنزيل المستندات المالية بعد تفويض الخادم فقط.
- نسخة خارجية مشفرة واستعادة ناجحة، أو توثيق صريح أن الهدف غير محقق.

إعدادات الإنتاج: `APP_ENV=production`, `APP_DEBUG=false`, مفتاح فريد سري في البيئة، HTTPS، `SESSION_SECURE_COOKIE=true`, وسياسة صلاحيات ملفات ضيقة. لا ترفع `.env`. لا تنفذ `migrate:fresh` على بيانات قائمة. قرار المزود وقيوده موثق في `HOSTING-COMPATIBILITY.md` ولم يُحسم.

## الاستعادة والنسخ

توجد أوامر `workshop:backup`, `workshop:backup-prune`, و`workshop:backup-restore`. النسخة ملف ZIP داخلي يحوي بيانات الجداول والملفات الخاصة، ثم تشفر بخوارزمية AES-256-GCM. يتطلب `APP_BACKUP_KEY` قيمة base64 لمفتاح عشوائي 32 بايت. لا يضمّن المفتاح داخل المستودع، ويجب حفظ نسخة منفصلة وآمنة منه. يحتفظ النظام بآخر 7 يومية و4 أسبوعية و3 شهرية.

القرص الافتراضي `backups` محلي في `storage/app/backups`، ولذلك ليس نسخة خارج الاستضافة. أضيف محول `league/flysystem-aws-s3-v3` لتفعيل قرص S3-compatible. استخدم `BACKUP_DISK=s3`، واملأ `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`، و`AWS_ENDPOINT` عند مزود متوافق (اترك endpoint فارغاً لخدمة AWS)، واضبط `AWS_USE_PATH_STYLE_ENDPOINT` بحسب المزود. لم يختبر اتصال فعلي أو مزود بعينه؛ استخدمنا credentials وهمية واختبار adapter دون اتصال. لا ترفع المفاتيح إلى GitHub؛ ضعها في إعداد أسرار المضيف. الجدولة معرفة يومياً وأسبوعياً وشهرياً بتوقيت `Africa/Khartoum`، لكن المضيف يحتاج تشغيل Scheduler عبر cron كل دقيقة أو وظيفة مكافئة؛ لم يثبت ذلك على مزود مجاني. اختبر الملف الخارجي بأمر `php artisan workshop:backup --frequency=daily` ثم استعادة النسخة إلى قاعدة معزولة قبل الاعتماد عليها. يشرح `docs/COMPLETION-ROADMAP.md` حدود إثبات RPO/RTO.

اختُبر إنشاء النسخة وتشفيرها والاحتفاظ بها، واختُبرت الاستعادة في SQLite مؤقتة مع استرجاع ملف خاص. لم تُختبر استعادة MySQL أو وجهة S3. لا تُعلن أهداف RPO/RTO أو نجاح النسخ الخارجية قبل إثبات cron والوجهة الخارجية واختبار استعادة شهري على بيئة الإنتاج، مع قياس وقت الاستعادة ونقطة البيانات. لا نرفع مستندات خاصة إلى مجلد عام أو مستودع Git.
