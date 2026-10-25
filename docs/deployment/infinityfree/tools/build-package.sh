#!/usr/bin/env bash
#
# Builds the InfinityFree upload package.
#
# Requirements on the BUILD machine only (never on the host):
#   php >= 8.3, composer 2, npm (Node 20+)
#
# Usage (from the repository root):
#   bash docs/deployment/infinityfree/tools/build-package.sh
#
# Output:
#   build/infinityfree-htdocs.zip   -> upload/extract inside htdocs
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/../../../.." && pwd)"
DEPLOY_DIR="$ROOT/docs/deployment/infinityfree"
BUILD_DIR="$ROOT/build/infinityfree"
STAGE="$BUILD_DIR/stage"
HTDOCS="$STAGE/htdocs"
APP="$HTDOCS/asmaa"
ZIP_OUT="$ROOT/build/infinityfree-htdocs.zip"

for tool in php composer npm tar zip; do
    if ! command -v "$tool" >/dev/null 2>&1; then
        echo "خطأ: الأداة '$tool' غير متوفرة على جهاز البناء." >&2
        exit 1
    fi
done

echo "==> تنظيف مجلد البناء"
rm -rf "$BUILD_DIR"
mkdir -p "$APP"

echo "==> نسخ ملفات التطبيق (باستثناء أدوات التطوير والأسرار)"
tar -C "$ROOT" \
    --exclude='./.git' \
    --exclude='./.github' \
    --exclude='./node_modules' \
    --exclude='./cloudflare' \
    --exclude='./vendor' \
    --exclude='./tests' \
    --exclude='./docs' \
    --exclude='./build' \
    --exclude='./.env' \
    --exclude='./.env.*' \
    --exclude='./phpunit.xml' \
    --exclude='./.phpunit.result.cache' \
    --exclude='./.neon' \
    --exclude='./neon.ts' \
    --exclude='./render.yaml' \
    --exclude='./Dockerfile' \
    --exclude='./.dockerignore' \
    --exclude='./.npmrc' \
    --exclude='./storage/framework/cache/data/*' \
    --exclude='./storage/framework/sessions/*' \
    --exclude='./storage/framework/views/*' \
    --exclude='./storage/framework/testing' \
    --exclude='./storage/logs/*' \
    --exclude='./storage/app/private/*' \
    --exclude='./storage/app/backups/*' \
    -cf - . | tar -C "$APP" -xf -

# Keep the empty runtime directories Laravel needs (with their .gitignore files).
mkdir -p "$APP/storage/app/private" "$APP/storage/app/backups" \
    "$APP/storage/framework/cache/data" "$APP/storage/framework/sessions" \
    "$APP/storage/framework/views" "$APP/storage/logs" "$APP/bootstrap/cache"

# Some host file managers skip empty directories when extracting a ZIP, so put a
# small placeholder inside each runtime directory to force its creation.
for dir in storage/app/private storage/app/backups storage/framework/cache/data \
    storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; do
    touch "$APP/$dir/.gitkeep"
done

rm -f "$APP/bootstrap/cache/"*.php

echo "==> تثبيت اعتماديات Composer الإنتاجية"
( cd "$APP" && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts )
# Regenerate the optimized autoloader and the package manifest without dev providers.
( cd "$APP" && composer dump-autoload --no-dev --optimize --no-interaction )

# Drop the bundled font-mirror build utilities inside tc-lib-pdf-font: they are
# not used at runtime and push the account close to the InfinityFree inode limit.
rm -rf "$APP/vendor/tecnickcom/tc-lib-pdf-font/util"

echo "==> بناء أصول الواجهة عبر Vite"
( cd "$APP" && npm ci --no-audit --no-fund && npm run build )

echo "==> إزالة ملفات البناء والتطوير غير اللازمة على الخادم"
rm -rf "$APP/node_modules" \
    "$APP/.vscode" \
    "$APP/font" \
    "$APP/vite.config.js" \
    "$APP/package.json" \
    "$APP/package-lock.json" \
    "$APP/README.md" \
    "$APP/SRS.md" \
    "$APP/.editorconfig" \
    "$APP/.gitattributes" \
    "$APP/.gitignore" \
    "$APP/icon.png" \
    "$APP/logo.png" \
    "$APP/resources/js" \
    "$APP/resources/css"

echo "==> تجميع جذر الويب (htdocs)"
cp -a "$APP/public/." "$HTDOCS/"
cp "$DEPLOY_DIR/htdocs-index.php" "$HTDOCS/index.php"
cp "$DEPLOY_DIR/htaccess/htdocs.htaccess" "$HTDOCS/.htaccess"
cp "$DEPLOY_DIR/htaccess/app.htaccess" "$APP/.htaccess"

echo "==> التحقق من عدم وجود أسرار أو بيانات محلية في الحزمة"
for forbidden in ".env" "database/database.sqlite" "storage/logs/laravel.log" ".phpunit.result.cache"; do
    if [ -e "$APP/$forbidden" ]; then
        echo "خطأ: ملف ممنوع موجود في الحزمة: $forbidden" >&2
        exit 1
    fi
done
if grep -rIl --include='*.php' -e 'DATABASE_URL=' -e 'APP_KEY=base64:' "$APP/config" "$APP/app" >/dev/null 2>&1; then
    echo "تحذير: تحقق يدوياً من عدم وجود أسرار مضمّنة في ملفات PHP." >&2
fi

echo "==> إنشاء ملف ZIP (المحتويات في الجذر لاستخراجها داخل htdocs مباشرة)"
rm -f "$ZIP_OUT"
( cd "$HTDOCS" && zip -qr "$ZIP_OUT" . )

echo
echo "تم إنشاء الحزمة: $ZIP_OUT"
echo "الحجم: $(du -h "$ZIP_OUT" | cut -f1)"
echo "عدد الملفات داخل الحزمة: $(find "$HTDOCS" -type f | wc -l)"
