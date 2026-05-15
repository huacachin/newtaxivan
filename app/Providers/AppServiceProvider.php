<?php

namespace App\Providers;

use App\Models\Expense;
use App\Models\Income;
use App\Policies\ExpensePolicy;
use App\Policies\IncomePolicy;
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

        // Las alertas del header ahora las maneja el componente Livewire
        // App\Livewire\Header\ExpiringDropdown, que se re-renderiza cuando
        // recibe el evento 'expiration-saved'. Ya no hace falta View Composer.
    }
}
