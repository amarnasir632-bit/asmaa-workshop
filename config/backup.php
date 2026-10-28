<?php

return [
    'disk' => env('BACKUP_DISK', 'backups'),
    'key' => env('APP_BACKUP_KEY'),
    'directory' => 'workshop',
    'retention' => ['daily' => 7, 'weekly' => 4, 'monthly' => 3],
];
