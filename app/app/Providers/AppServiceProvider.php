<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use App\Support\RoleHelper;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $helpers = base_path('helpers.php');
        if (is_file($helpers)) {
            require_once $helpers;
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::if('role', function (...$roles) {
            return RoleHelper::currentHasRole($roles);
        });

        Blade::if('admin', function () {
            return RoleHelper::currentHasRole(['admin']);
        });

        Blade::if('sales', function () {
            return RoleHelper::currentHasRole(['sales']);
        });

        Blade::if('customer', function () {
            return RoleHelper::currentHasRole(['customer']);
        });
    }
}
