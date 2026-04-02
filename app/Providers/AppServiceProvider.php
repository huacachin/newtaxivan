<?php

namespace App\Providers;

use App\Models\Expense;
use App\Models\Income;
use App\Models\Vehicle;
use App\Policies\ExpensePolicy;
use App\Policies\IncomePolicy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

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
        Gate::policy(Income::class, IncomePolicy::class);
        Gate::policy(Expense::class, ExpensePolicy::class);

        Gate::before(function ($user, $ability, $arguments) {
            // No hacer bypass para policies de Income/Expense
            if (!empty($arguments) && ($arguments[0] instanceof Income || $arguments[0] instanceof Expense)) {
                return null;
            }
            return $user->hasAnyRole('director','gerente') ? true : null;
        });

        View::composer('layout.header', function ($view) {
            // Cachea 60s para no pegar a DB en cada request
            $alerts = Cache::remember('vehicle_expiring_alerts', 60, function () {
                return Vehicle::query()
                    ->select('id','plate','soat_date','technical_review','certificate_date')
                    ->get()
                    ->flatMap(fn($v) => $v->expiringAlerts())   // une SD/RT/CD por vehículo
                    ->sortBy('days')                            // los más urgentes primero
                    ->values()
                    ->all();
            });

            // si quieres limitar cuántas mostrar en el dropdown:
            $view->with('vehicleExpAlerts', array_slice($alerts, 0, 10));
            $view->with('vehicleExpCount',  count($alerts));
        });
    }
}
