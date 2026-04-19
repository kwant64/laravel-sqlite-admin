<?php

declare(strict_types=1);

namespace PhpLiteAdmin\LaravelSqliteAdmin;

use Illuminate\Support\ServiceProvider;

class SqliteAdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sqlite-admin.php', 'sqlite-admin');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'sqlite-admin');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/sqlite-admin.php' => config_path('sqlite-admin.php'),
            ], 'sqlite-admin-config');
        }
    }
}
