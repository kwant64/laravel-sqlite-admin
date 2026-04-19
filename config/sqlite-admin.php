<?php

declare(strict_types=1);

return [
    'enabled' => env('SQLITE_ADMIN_ENABLED', true),

    // URL pad, bijvoorbeeld: /sqlite-admin
    'path' => env('SQLITE_ADMIN_PATH', 'sqlite-admin'),

    // Middleware die op alle SQLite Admin routes draait.
    'middleware' => [
        'web',
        'auth',
    ],

    // Root folder waaruit databasebestanden gekozen mogen worden.
    'db_root' => env('SQLITE_ADMIN_DB_ROOT', database_path()),

    // Zet op true als je absolute paden wilt toestaan.
    'allow_absolute_paths' => (bool)env('SQLITE_ADMIN_ALLOW_ABSOLUTE_PATHS', false),

    'default_per_page' => (int)env('SQLITE_ADMIN_PER_PAGE', 50),
    'max_per_page' => (int)env('SQLITE_ADMIN_MAX_PER_PAGE', 500),

    // Session key voor het geselecteerde sqlite bestand.
    'session_key' => 'sqlite_admin.db_path',
];
