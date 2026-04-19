<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PhpLiteAdmin\LaravelSqliteAdmin\Http\Controllers\SqliteAdminController;

if ((bool)config('sqlite-admin.enabled', true)) {
    Route::middleware(config('sqlite-admin.middleware', ['web', 'auth']))
        ->prefix(trim((string)config('sqlite-admin.path', 'sqlite-admin'), '/'))
        ->group(function (): void {
            Route::match(['get', 'post'], '/', [SqliteAdminController::class, 'index'])
                ->name('sqlite-admin.index');
            Route::get('/download', [SqliteAdminController::class, 'download'])
                ->name('sqlite-admin.download');
        });
}
