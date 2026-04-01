<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\{
    DashboardController, VehicleController, OwnerController, DriverController,
    CostPerPlateController, DspController, UserController, ConceptController,
    DepartureController, PaymentController, DebtController, CashController,
    HeadquarterController
};

// === Público (solo invitados) ===
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('auth.index'))->name('login');
    // Si quieres que / también vaya al login cuando no hay sesión:
    Route::get('/', fn () => redirect()->route('login'));
});

// === Logout (POST con CSRF) ===
Route::post('/logout', function (Request $request) {
    auth()->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('login');
})->name('logout');

// === Protegido (requiere auth) ===
Route::middleware('auth')->group(function () {

    // Dashboard (raíz autenticada)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    // Configuraciones
    Route::get('vehicles', [VehicleController::class,'index'])->name('settings.vehicles.index');
    Route::get('vehicles/create', [VehicleController::class,'create'])->name('settings.vehicles.create')->middleware('role:director|gerente|administrador');
    Route::get('vehicles/{id}/edit', [VehicleController::class,'edit'])->name('settings.vehicles.edit')->middleware('role:director|gerente|administrador');
    Route::get('owners', [OwnerController::class,'index'])->name('settings.owners.index');
    Route::get('owners/create', [OwnerController::class,'create'])->name('settings.owners.create')->middleware('role:director|gerente|administrador');
    Route::get('owners/{id}/edit', [OwnerController::class,'edit'])->name('settings.owners.edit')->middleware('role:director|gerente|administrador');
    Route::get('drivers', [DriverController::class,'index'])->name('settings.drivers.index');
    Route::get('drivers/create', [DriverController::class,'create'])->name('settings.drivers.create')->middleware('role:director|gerente|administrador');
    Route::get('drivers/{id}/edit', [DriverController::class,'edit'])->name('settings.drivers.edit')->middleware('role:director|gerente|administrador');

    // Costo por placa
    Route::get('cost-per-plate', [CostPerPlateController::class,'index'])->name('settings.cost-per-plate.index');
    Route::get('cost-per-plate/day/{year}/{month}', [CostPerPlateController::class,'day'])->name('settings.cost-per-plate.cost-per-plate-day');
    Route::get('cost-per-plate/calendar/{plate}/{year}/{month}', [CostPerPlateController::class,'calendar'])->name('settings.cost-per-plate.calendar');

    // Eliminar deudas - salidas y pagos
    //Route::get('debts-departures-payments', [DspController::class,'index'])->name('settings.dsp.index');

    // Usuarios
    Route::get('users', [UserController::class,'index'])->name('settings.users.index');
    Route::get('users/create', [UserController::class,'create'])->name('settings.users.create')->middleware('role:director');
    Route::get('users/{user}/edit', [UserController::class,'edit'])->name('settings.users.edit');
    Route::get('users/{user}/perms', [UserController::class,'perms'])->name('settings.users.perms')->middleware('role:director|gerente|administrador');

    // Conceptos
    Route::resource('concepts', ConceptController::class)->names('settings.concepts');

    // Sucursales
    Route::get('headquarters', [HeadquarterController::class,'index'])->name('settings.headquarters.index');
    Route::get('headquarters/create', [HeadquarterController::class,'create'])->name('settings.headquarters.create')->middleware('role:director|gerente|administrador');
    Route::get('headquarters/{id}/edit', [HeadquarterController::class,'edit'])->name('settings.headquarters.edit')->middleware('role:director');

    // Salidas
    Route::get('departures', [DepartureController::class,'index'])->name('departures.index');
    Route::get('departures/monthly', [DepartureController::class,'monthly'])->name('departures.monthly');
    Route::get('departures/rmp', [DepartureController::class,'rmp'])->name('departures.rmp');
    Route::get('departures/stats', [DepartureController::class,'stats'])->name('departures.stats');
    Route::get('departures/by-debt',[DepartureController::class,'byDebt'])->name('departures.by-debt');
    Route::get('departures/add',[DepartureController::class,'add'])->name('departures.add');
    Route::get('departures/edit/{id}',[DepartureController::class,'edit'])->name('departures.edit')->middleware('role:director|gerente|administrador');

    // Pagos
    Route::get('payments', [PaymentController::class,'index'])->name('payments.index');
    Route::get('payments/daily', [PaymentController::class,'daily'])->name('payments.daily');
    Route::get('payments/monthly', [PaymentController::class,'monthly'])->name('payments.monthly');
    Route::get('payments/stats', [PaymentController::class,'stats'])->name('payments.stats');
    Route::get('payments/add',[PaymentController::class,'add'])->name('payments.add');
    Route::get('payments/edit/{id}',[PaymentController::class,'edit'])->name('payments.edit')->middleware('role:director|gerente|administrador');

    // Deudas
    Route::get('debts-per-days', [DebtController::class,'debtPerDays'])->name('debts.debt-per-days');
    Route::get('debt-generate',[DebtController::class,'generate'])->name('debts.generate');
    Route::get('monthly-debt',[DebtController::class,'monthly'])->name('debts.monthly');
    Route::get('monthly-debt/{id}',[DebtController::class,'monthlyDetail'])->name('debts.monthly.detail');
    Route::get('delete-debt',[DebtController::class,'delete'])->name('debts.delete');

    // Caja
    Route::get('cash/open',[CashController::class,'open'])->name('cash.open');
    Route::get('cash/report/movement',[CashController::class,'movementReport'])->name('cash.report.movement');
    Route::get('cash/incomes',[CashController::class,'incomes'])->name('cash.incomes');
    Route::get('cash/incomes/create',[CashController::class,'createIncome'])->name('cash.incomes.create')->middleware('role:director|gerente|administrador');
    Route::get('cash/incomes/{id}/edit',[CashController::class,'editIncome'])->name('cash.incomes.edit')->middleware('role:director|gerente|administrador');
    Route::get('cash/expenses',[CashController::class,'expenses'])->name('cash.expenses');
    Route::get('cash/expenses/create',[CashController::class,'createExpense'])->name('cash.expenses.create')->middleware('role:director|gerente|administrador');
    Route::get('cash/expenses/{id}/edit',[CashController::class,'editExpense'])->name('cash.expenses.edit')->middleware('role:director|gerente|administrador');
    Route::get('cash/report/general',[CashController::class,'generalReport'])->name('cash.report.general');
    Route::get('cash/report/est-draco-base',[CashController::class,'reportEstDracoBase'])->name('cash.report.est-draco-base');
    Route::get('cash/report/est-sal-pag-cont',[CashController::class,'reportEstSalPagCont'])->name('cash.report.est-sal-pag-cont');
    Route::get('cash/report/est-caja-ma',[CashController::class,'reportEstCajaMa'])->name('cash.report.est-caja-ma');

    // Auditoría (solo Director)
    Route::get('audit-logs', fn() => view('audit-logs.index'))->name('audit.logs.index')->middleware('role:director');

    //Exportar a excel
    Route::get('/exports/vehicles', [VehicleController::class, 'export'])
        ->name('exports.vehicles');
    Route::get('/exports/owners', [OwnerController::class, 'export'])
        ->name('exports.owners');
    Route::get('/exports/drivers', [DriverController::class, 'export'])
        ->name('exports.drivers');
    Route::get('/exports/departures', [DepartureController::class, 'export'])
        ->name('exports.departures');
    Route::get('/exports/incomes', [CashController::class, 'exportIncomes'])
        ->name('exports.incomes');
    Route::get('/exports/expenses', [CashController::class, 'exportExpenses'])
        ->name('exports.expenses');
    Route::get('/exports/payments', [PaymentController::class, 'export'])
        ->name('exports.payments');
    Route::get('/exports/debts-per-days', [DebtController::class, 'export'])
        ->name('exports.debts-per-days');
    Route::get('/exports/debts-per-days-detail', [DebtController::class, 'exportDetail'])
        ->name('exports.debts-per-days-detail');
    Route::get('/exports/debts-monthly', [DebtController::class, 'exportMonthly'])
        ->name('exports.debts-monthly');
    Route::get('/exports/cash-general-report', [CashController::class, 'exportGeneralReport'])
        ->name('exports.cash-general-report');
    Route::get('/exports/cash-draco-report', [CashController::class, 'exportDracoReport'])
        ->name('exports.cash-draco-report');
    Route::get('/exports/departures-monthly-export', [DepartureController::class, 'exportMonthly'])
        ->name('exports.departures-monthly-export');
    Route::get('/exports/departures-rmp-report', [DepartureController::class, 'exportRmp'])
        ->name('exports.departures-rmp-report');
    Route::get('/exports/departures-stats-report', [DepartureController::class, 'exportStats'])
        ->name('exports.departures-stats-report');

    Route::get('/exports/payments-monthly', [PaymentController::class, 'exportMonthly'])
        ->name('exports.payments-monthly');

    Route::get('/exports/payments-daily', [PaymentController::class, 'exportDaily'])
        ->name('exports.payments-daily');

    Route::get('/exports/payments-stats', [PaymentController::class, 'exportStats'])
        ->name('exports.payments-stats');

    Route::get('/exports/users', [UserController::class, 'export'])
        ->name('exports.users');

    Route::get('/exports/concepts', [ConceptController::class, 'export'])
        ->name('exports.concepts');
    Route::get('/exports/headquarters', [HeadquarterController::class, 'export'])
        ->name('exports.headquarters');

});
