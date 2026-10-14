<?php

namespace Tests\Feature;

use App\Services\EncryptedBackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EncryptedBackupRestoreTest extends TestCase
{
    public function test_encrypted_backup_can_restore_database_and_private_file_in_isolated_sqlite_database(): void
    {
        $oldDefault = config('database.default');
        $databasePath = tempnam(sys_get_temp_dir(), 'asmaa-restore-test-');
        $this->assertNotFalse($databasePath);
        config(['database.connections.backup_test' => [
            'driver' => 'sqlite', 'database' => $databasePath, 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'database.default' => 'backup_test']);
        DB::purge('backup_test');
        config(['backup.key' => base64_encode(random_bytes(32)), 'backup.disk' => 'backups']);
        Storage::fake('backups');
        Storage::fake('private');

        try {
            Artisan::call('migrate:fresh', ['--force' => true]);
            DB::table('guardians')->insert(['name' => 'سجل احتياطي اصطناعي', 'phone' => '000000777', 'created_at' => now(), 'updated_at' => now()]);
            Storage::disk('private')->put('evidence/test.txt', 'PRIVATE-RESTORE-FIXTURE');
            $backups = app(EncryptedBackupService::class);
            $path = $backups->create('daily');

            DB::table('guardians')->update(['name' => 'بيانات بعد النسخة']);
            Storage::disk('private')->put('evidence/test.txt', 'CHANGED-AFTER-BACKUP');
            $result = $backups->restore($path);

            $this->assertSame('سجل احتياطي اصطناعي', DB::table('guardians')->value('name'));
            $this->assertSame('PRIVATE-RESTORE-FIXTURE', Storage::disk('private')->get('evidence/test.txt'));
            $this->assertGreaterThan(0, $result['tables']);
            $this->assertSame(1, $result['private_files']);
        } finally {
            DB::purge('backup_test');
            config(['database.default' => $oldDefault]);
            @unlink($databasePath);
        }
    }
}
