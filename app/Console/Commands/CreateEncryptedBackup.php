<?php

namespace App\Console\Commands;

use App\Services\EncryptedBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateEncryptedBackup extends Command
{
    protected $signature = 'workshop:backup {--frequency=daily}';

    protected $description = 'Create an encrypted database and private-files backup';

    public function handle(EncryptedBackupService $backups): int
    {
        try {
            $path = $backups->create((string) $this->option('frequency'));
            $this->info('تم إنشاء نسخة مشفرة: '.$path);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('Encrypted backup failed.', ['exception_class' => $exception::class]);
            $this->error('فشل إنشاء النسخة. راجع سجل النظام دون نشر مفاتيح النسخ.');

            return self::FAILURE;
        }
    }
}
