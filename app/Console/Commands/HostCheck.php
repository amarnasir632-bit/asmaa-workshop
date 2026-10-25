<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HostCheck extends Command
{
    protected $signature = 'app:host-check';

    protected $description = 'فحص توافق المضيف مع متطلبات التطبيق قبل النشر';

    public function handle(): int
    {
        $failures = 0;

        $phpOk = version_compare(PHP_VERSION, '8.3.0', '>=');
        $this->line(sprintf('%s إصدار PHP: %s (المطلوب 8.3+)', $phpOk ? '[OK]' : '[!!]', PHP_VERSION));
        $failures += $phpOk ? 0 : 1;

        $required = ['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'mbstring', 'openssl', 'pcre', 'pdo_mysql', 'session', 'tokenizer', 'xml', 'zip', 'zlib'];
        foreach ($required as $extension) {
            $loaded = extension_loaded($extension);
            $this->line(sprintf('%s امتداد %s', $loaded ? '[OK]' : '[!!]', $extension));
            $failures += $loaded ? 0 : 1;
        }

        $this->line(sprintf('%s APP_ENV=%s', config('app.env') === 'production' ? '[OK]' : '[!!]', config('app.env')));
        if (config('app.env') !== 'production') {
            $failures++;
        }
        $this->line(sprintf('%s APP_DEBUG=%s', config('app.debug') ? '[!!]' : '[OK]', config('app.debug') ? 'true' : 'false'));
        if (config('app.debug')) {
            $failures++;
        }
        $this->line(sprintf('%s APP_KEY %s', config('app.key') ? '[OK]' : '[!!]', config('app.key') ? 'مضبوط' : 'مفقود'));
        if (! config('app.key')) {
            $failures++;
        }
        $this->line(sprintf('%s APP_URL=%s', config('app.url') ? '[OK]' : '[!!]', config('app.url') ?: 'مفقود'));

        foreach (['private' => storage_path('app/private'), 'framework' => storage_path('framework'), 'logs' => storage_path('logs')] as $label => $path) {
            $writable = is_dir($path) ? is_writable($path) : is_writable(dirname($path));
            $this->line(sprintf('%s كتابة %s', $writable ? '[OK]' : '[!!]', $label));
            $failures += $writable ? 0 : 1;
        }

        $fontDirectory = resource_path('pdf-fonts/dejavu/dejavusans.json');
        $this->line(sprintf('%s ملفات خط PDF', is_file($fontDirectory) ? '[OK]' : '[!!]'));
        $failures += is_file($fontDirectory) ? 0 : 1;

        try {
            DB::connection()->getPdo();
            $this->line(sprintf('[OK] اتصال قاعدة البيانات (%s)', DB::connection()->getDriverName()));
        } catch (Throwable $exception) {
            $this->line(sprintf('[!!] اتصال قاعدة البيانات: %s', $exception->getMessage()));
            $failures++;
        }

        try {
            Storage::disk('private')->put('.host-check', 'ok');
            Storage::disk('private')->delete('.host-check');
            $this->line('[OK] قراءة/كتابة التخزين الخاص');
        } catch (Throwable $exception) {
            $this->line(sprintf('[!!] التخزين الخاص: %s', $exception->getMessage()));
            $failures++;
        }

        $this->newLine();
        $this->line($failures === 0 ? 'كل الفحوص ناجحة.' : "عدد الفحوص الفاشلة: {$failures}");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
