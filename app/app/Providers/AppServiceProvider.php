<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
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
        try {
            if (config('database.default') === 'pgsql') {
                $options = config('database.connections.pgsql.options');
                if (!is_array($options)) {
                    $options = [];
                }
                $options[\PDO::PGSQL_ATTR_INIT_COMMAND] = "SET TIME ZONE 'Asia/Tokyo'";
                config(['database.connections.pgsql.options' => $options]);

                DB::statement("SET TIME ZONE 'Asia/Tokyo'");
            }
        } catch (\Throwable $e) {
            // DB未起動時などは黙って通す
        }

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
