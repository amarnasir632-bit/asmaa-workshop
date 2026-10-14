<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use Tests\TestCase;

class S3BackupDiskTest extends TestCase
{
    public function test_laravel_can_build_an_s3_compatible_disk_without_contacting_a_provider(): void
    {
        config([
            'filesystems.disks.s3' => [
                'driver' => 's3',
                'key' => 'synthetic-access-key',
                'secret' => 'synthetic-secret-key',
                'region' => 'us-east-1',
                'bucket' => 'asmaa-backup-test',
                'endpoint' => 'https://s3.example.invalid',
                'use_path_style_endpoint' => true,
                'throw' => true,
                'report' => true,
            ],
        ]);
        Storage::forgetDisk('s3');

        $this->assertInstanceOf(AwsS3V3Adapter::class, Storage::disk('s3')->getAdapter());
    }
}
