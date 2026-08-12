<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // La vista por defecto de Laravel usa Tailwind, que aquí no existe;
        // la del panel está en resources/views/vendor/pagination/panel.blade.php.
        Paginator::defaultView('pagination::panel');
    }
}
