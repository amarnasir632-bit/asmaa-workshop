<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// The application lives in htdocs/asmaa because InfinityFree fixes the web root
// at htdocs and does not allow files above it.
$base = __DIR__.'/asmaa';

// Empty directories are not always created when a ZIP is extracted by the host
// file manager. The framework requires these writable runtime directories to
// exist (Blade needs storage/framework/views or it throws "Please provide a
// valid cache path"). Recreate them on demand before Laravel boots.
foreach ([
    'bootstrap/cache',
    'storage/app/private',
    'storage/app/backups',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
] as $dir) {
    $path = $base.'/'.$dir;
    if (! is_dir($path)) {
        @mkdir($path, 0777, true);
    }
}

if (file_exists($maintenance = $base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $base.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $base.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
