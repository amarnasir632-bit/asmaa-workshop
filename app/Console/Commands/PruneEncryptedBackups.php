<?php

namespace App\Console\Commands;

use App\Services\EncryptedBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class PruneEncryptedBackups extends Command
{
    protected $signature = 'workshop:backup-prune';

    protected $description = 'Apply daily, weekly and monthly encrypted backup retention';

    public function handle(EncryptedBackupService $backups): int
    {
        try {
            $deleted = $backups->prune();
            $this->info('تم تطبيق الاحتفاظ؛ حُذفت '.count($deleted).' نسخة قديمة.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('Encrypted backup retention failed.', ['exception_class' => $exception::class]);
            $this->error('تعذر تطبيق سياسة الاحتفاظ بالنسخ.');

            return self::FAILURE;
        }
    }
}
