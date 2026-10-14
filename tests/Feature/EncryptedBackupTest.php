<?php

namespace Tests\Feature;

use App\Services\EncryptedBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EncryptedBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['backup.key' => base64_encode(random_bytes(32)), 'backup.disk' => 'backups']);
        Storage::fake('backups');
        Storage::fake('private');
    }

    public function test_backup_encrypts_database_and_private_files_before_writing_to_backup_disk(): void
    {
        Storage::disk('private')->put('payment-evidence/synthetic-proof.txt', 'SYNTHETIC-PRIVATE-CONTENT');
        $path = app(EncryptedBackupService::class)->create('daily');
        $encrypted = Storage::disk('backups')->get('workshop/'.$path);

        $this->assertSame('daily', dirname($path));
        $this->assertStringContainsString('aes-256-gcm', $encrypted);
        $this->assertStringNotContainsString('SYNTHETIC-PRIVATE-CONTENT', $encrypted);
        $this->assertStringNotContainsString('database.json', $encrypted);
    }

    public function test_backup_retention_keeps_only_configured_count_per_frequency(): void
    {
        config(['backup.retention.daily' => 7]);
        foreach (range(1, 9) as $number) {
            Storage::disk('backups')->put('workshop/daily/'.sprintf('%02d', $number).'.backup', 'encrypted');
        }

        $deleted = app(EncryptedBackupService::class)->prune();

        $this->assertCount(2, $deleted);
        $this->assertCount(7, Storage::disk('backups')->allFiles('workshop/daily'));
    }

    public function test_backup_disk_is_configurable_for_an_s3_compatible_external_destination(): void
    {
        config(['backup.disk' => 's3']);
        Storage::fake('s3');
        Storage::disk('private')->put('payment-evidence/s3-fixture.txt', 'SYNTHETIC-S3-CONTENT');

        $path = app(EncryptedBackupService::class)->create('daily');

        Storage::disk('s3')->assertExists('workshop/'.$path);
        $this->assertStringContainsString('aes-256-gcm', Storage::disk('s3')->get('workshop/'.$path));
        $this->assertStringNotContainsString('SYNTHETIC-S3-CONTENT', Storage::disk('s3')->get('workshop/'.$path));
    }
}
