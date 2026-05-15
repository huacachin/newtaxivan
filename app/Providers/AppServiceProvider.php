<?php

namespace App\Providers;

use App\Models\Driver;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Owner;
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
        // Garantiza que versioned_asset() esté disponible aunque no se haya
        // corrido `composer dump-autoload` después de cambiar composer.json.
        $helper = app_path('Helpers/assets.php');
        if (is_file($helper) && !function_exists('versioned_asset')) {
            require_once $helper;
        }
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
            $alerts = Cache::remember('header_expiring_alerts', 60, function () {
                $today = now(config('app.timezone', 'America/Lima'))->toDateString();

                $vehicleAlerts = Vehicle::query()
                    ->select('id','plate','soat_date','technical_review','certificate_date','status','termination_date')
                    ->where('status', 'active')
                    ->where(function ($w) use ($today) {
                        $w->whereNull('termination_date')
                          ->orWhere('termination_date', '>', $today);
                    })
                    ->get()
                    ->flatMap(fn ($v) => $v->expiringAlerts());

                $ownerAlerts = Owner::query()
                    ->select('id','name','document_expiration_date','status')
                    ->where('status', 'active')
                    ->whereNotNull('document_expiration_date')
                    ->get()
                    ->flatMap(fn ($o) => $o->expiringAlerts());

                $driverAlerts = Driver::query()
                    ->select('id','name','document_expiration_date',
                             'license_revalidation_date','road_education_expiration_date',
                             'credential_expiration_date','status')
                    ->where('status', 'active')
                    ->get()
                    ->flatMap(fn ($d) => $d->expiringAlerts());

                return $vehicleAlerts
                    ->concat($ownerAlerts)
                    ->concat($driverAlerts)
                    ->sortBy('days')   // más urgentes primero (vencidos = negativos)
                    ->values()
                    ->all();
            });

            // si quieres limitar cuántas mostrar en el dropdown:
            $view->with('vehicleExpAlerts', array_slice($alerts, 0, 10));
            $view->with('vehicleExpCount',  count($alerts));
        });
    }
}
