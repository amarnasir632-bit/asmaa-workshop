<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class EncryptedBackupService
{
    private const CIPHER = 'aes-256-gcm';

    public function create(string $frequency): string
    {
        $this->assertFrequency($frequency);
        $key = $this->key();
        $zipPath = tempnam(sys_get_temp_dir(), 'asmaa-backup-');
        if ($zipPath === false) {
            throw new RuntimeException('تعذر إنشاء ملف مؤقت للنسخ الاحتياطي.');
        }

        try {
            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('تعذر فتح ملف النسخة المؤقت.');
            }
            $snapshot = DB::transaction(function () use ($zip): array {
                $tables = $this->tables();
                $tableData = [];
                foreach ($tables as $table) {
                    $tableData[$table] = $table === 'sessions'
                        ? []
                        : DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
                }
                $migrations = Schema::hasTable('migrations')
                    ? DB::table('migrations')->orderBy('migration')->pluck('migration')->all()
                    : [];
                $zip->addFromString('database.json', json_encode([
                    'format' => 1,
                    'driver' => DB::connection()->getDriverName(),
                    'migrations' => $migrations,
                    'tables' => $tableData,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

                foreach (Storage::disk('private')->allFiles() as $file) {
                    $localPath = Storage::disk('private')->path($file);
                    if (is_file($localPath)) {
                        $zip->addFile($localPath, 'private/'.$file);
                    }
                }

                return ['tables' => count($tableData), 'migrations' => $migrations];
            });
            $zip->addFromString('manifest.json', json_encode([
                'application' => config('app.name'),
                'created_at_utc' => now('UTC')->toIso8601String(),
                'frequency' => $frequency,
                'table_count' => $snapshot['tables'],
                'migration_count' => count($snapshot['migrations']),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $zip->close();

            $plain = file_get_contents($zipPath);
            if ($plain === false) {
                throw new RuntimeException('تعذر قراءة النسخة المؤقتة.');
            }
            $nonce = random_bytes(12);
            $tag = '';
            $ciphertext = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, 'asmaa-workshop-backup-v1');
            if ($ciphertext === false) {
                throw new RuntimeException('تعذر تشفير النسخة الاحتياطية.');
            }
            $filename = $frequency.'/'.now('UTC')->format('Y-m-d_H-i-s').'.backup';
            $envelope = json_encode([
                'version' => 1,
                'cipher' => self::CIPHER,
                'nonce' => base64_encode($nonce),
                'tag' => base64_encode($tag),
                'ciphertext' => base64_encode($ciphertext),
            ], JSON_THROW_ON_ERROR);
            if (! Storage::disk(config('backup.disk'))->put(config('backup.directory').'/'.$filename, $envelope, ['visibility' => 'private'])) {
                throw new RuntimeException('تعذر حفظ النسخة على وسيط التخزين المحدد.');
            }

            return $filename;
        } finally {
            @unlink($zipPath);
        }
    }

    public function prune(): array
    {
        $disk = Storage::disk(config('backup.disk'));
        $deleted = [];
        foreach (config('backup.retention') as $frequency => $keep) {
            $directory = config('backup.directory').'/'.$frequency;
            $files = collect($disk->allFiles($directory))->filter(fn ($path) => str_ends_with($path, '.backup'))
                ->sortDesc()->values();
            foreach ($files->slice((int) $keep) as $oldFile) {
                $disk->delete($oldFile);
                $deleted[] = $oldFile;
            }
        }

        return $deleted;
    }

    public function restore(string $relativePath): array
    {
        $this->key();
        if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            throw new RuntimeException('مسار النسخة غير صالح.');
        }
        $path = config('backup.directory').'/'.$relativePath;
        $envelopeContents = Storage::disk(config('backup.disk'))->get($path);
        $envelope = json_decode($envelopeContents, true, flags: JSON_THROW_ON_ERROR);
        if (($envelope['version'] ?? null) !== 1 || ($envelope['cipher'] ?? null) !== self::CIPHER) {
            throw new RuntimeException('صيغة النسخة أو خوارزمية التشفير غير معروفة.');
        }
        $nonce = base64_decode($envelope['nonce'] ?? '', true);
        $tag = base64_decode($envelope['tag'] ?? '', true);
        $ciphertext = base64_decode($envelope['ciphertext'] ?? '', true);
        if ($nonce === false || $tag === false || $ciphertext === false) {
            throw new RuntimeException('ملف النسخة غير صالح.');
        }
        $plain = openssl_decrypt($ciphertext, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $nonce, $tag, 'asmaa-workshop-backup-v1');
        if ($plain === false) {
            throw new RuntimeException('تعذر فك تشفير النسخة؛ تحقق من مفتاح النسخ الاحتياطي.');
        }
        $zipPath = tempnam(sys_get_temp_dir(), 'asmaa-restore-');
        if ($zipPath === false || file_put_contents($zipPath, $plain) === false) {
            throw new RuntimeException('تعذر إنشاء ملف استعادة مؤقت.');
        }

        try {
            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('ملف النسخة المشفر لا يحتوي أرشيفاً صالحاً.');
            }
            $database = json_decode($zip->getFromName('database.json') ?: '', true, flags: JSON_THROW_ON_ERROR);
            $currentMigrations = Schema::hasTable('migrations') ? DB::table('migrations')->orderBy('migration')->pluck('migration')->all() : [];
            if (($database['format'] ?? null) !== 1 || ($database['migrations'] ?? null) !== $currentMigrations) {
                throw new RuntimeException('نسخة قاعدة البيانات لا تطابق migrations الحالية؛ لن تبدأ الاستعادة.');
            }
            foreach (array_keys($database['tables'] ?? []) as $table) {
                if (! Schema::hasTable($table)) {
                    throw new RuntimeException('النسخة تحتوي جدولاً غير موجود في النظام الحالي.');
                }
            }
            $privateFiles = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if (! str_starts_with($name, 'private/')) {
                    continue;
                }
                $relative = substr($name, strlen('private/'));
                if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/')) {
                    throw new RuntimeException('مسار مرفق غير صالح داخل النسخة.');
                }
                $content = $zip->getFromIndex($index);
                if ($content === false) {
                    throw new RuntimeException('تعذر قراءة أحد المرفقات من النسخة.');
                }
                $privateFiles[$relative] = $content;
            }
            $zip->close();

            $connection = DB::connection();
            Schema::disableForeignKeyConstraints();
            try {
                DB::transaction(function () use ($database): void {
                    foreach (array_reverse(array_keys($database['tables'])) as $table) {
                        DB::table($table)->delete();
                    }
                    foreach ($database['tables'] as $table => $rows) {
                        foreach (array_chunk($rows, 200) as $chunk) {
                            DB::table($table)->insert($chunk);
                        }
                    }
                });
                foreach (Storage::disk('private')->allFiles() as $file) {
                    Storage::disk('private')->delete($file);
                }
                foreach ($privateFiles as $file => $contents) {
                    Storage::disk('private')->put($file, $contents);
                }
            } finally {
                Schema::enableForeignKeyConstraints();
            }

            return ['tables' => count($database['tables']), 'private_files' => count($privateFiles)];
        } finally {
            @unlink($zipPath);
        }
    }

    private function tables(): array
    {
        $driver = DB::connection()->getDriverName();
        $tables = match ($driver) {
            'sqlite' => DB::select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%'"),
            'mysql', 'mariadb' => DB::select("select table_name as name from information_schema.tables where table_schema = database() and table_type = 'BASE TABLE'"),
            'pgsql' => DB::select("select table_name as name from information_schema.tables where table_schema = current_schema() and table_type = 'BASE TABLE'"),
            default => throw new RuntimeException('محرك قاعدة البيانات هذا غير مدعوم للنسخ الاحتياطي.'),
        };

        return collect($tables)->pluck('name')->sort()->values()->all();
    }

    private function key(): string
    {
        $key = base64_decode((string) config('backup.key'), true);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('اضبط APP_BACKUP_KEY بمفتاح base64 عشوائي طوله 32 بايت قبل النسخ أو الاستعادة.');
        }

        return $key;
    }

    private function assertFrequency(string $frequency): void
    {
        if (! array_key_exists($frequency, config('backup.retention'))) {
            throw new RuntimeException('تكرار النسخ يجب أن يكون daily أو weekly أو monthly.');
        }
    }
}
