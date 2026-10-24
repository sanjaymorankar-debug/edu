<?php

namespace App\Providers;

use App\Database\PostgresConnector;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Passes channel_binding (part of every Neon URL) through to libpq.
        $this->app->bind('db.connector.pgsql', PostgresConnector::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
