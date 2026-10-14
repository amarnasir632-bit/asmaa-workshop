<?php

namespace App\Console\Commands;

use App\Services\EncryptedBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RestoreEncryptedBackup extends Command
{
    protected $signature = 'workshop:backup-restore {path : Relative backup path under the configured backup disk}';

    protected $description = 'Interactively restore an encrypted database and private-files backup';

    public function handle(EncryptedBackupService $backups): int
    {
        if (! $this->confirm('تحذير: ستُستبدل بيانات قاعدة البيانات والملفات الخاصة الحالية. هل راجعت النسخة وتريد الاستعادة؟', false)) {
            $this->warn('أُلغيت الاستعادة ولم تُمس البيانات الحالية.');

            return self::FAILURE;
        }
        if (! $this->confirm('أكد الاستعادة النهائية لهذه البيئة؟', false)) {
            $this->warn('أُلغيت الاستعادة ولم تُمس البيانات الحالية.');

            return self::FAILURE;
        }

        try {
            $result = $backups->restore((string) $this->argument('path'));
            $this->info('اكتملت الاستعادة. الجداول: '.$result['tables'].'؛ الملفات الخاصة: '.$result['private_files']);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('Encrypted backup restore failed.', ['exception_class' => $exception::class]);
            $this->error('فشلت الاستعادة. راجع سجل النظام وتحقق من توافق schema والمفتاح.');

            return self::FAILURE;
        }
    }
}
