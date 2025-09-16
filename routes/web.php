<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\{
    DashboardController, VehicleController, OwnerController, DriverController,
    CostPerPlateController, DspController, UserController, ConceptController,
    DepartureController, PaymentController, DebtController, CashController
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

    Route::get('/admin', fn() => 'ok')->middleware(['auth','role:admin']);

    // Dashboard (raíz autenticada)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    // Configuraciones
    Route::resource('vehicles', VehicleController::class)->names('settings.vehicles');
    Route::resource('owners', OwnerController::class)->names('settings.owners');
    Route::resource('drivers', DriverController::class)->names('settings.drivers');

    // Costo por placa
    Route::get('cost-per-plate', [CostPerPlateController::class,'index'])->name('settings.cost-per-plate.index');
    Route::get('cost-per-plate/day/{year}/{month}', [CostPerPlateController::class,'day'])->name('settings.cost-per-plate.cost-per-plate-day');
    Route::get('cost-per-plate/calendar/{plate}/{year}/{month}', [CostPerPlateController::class,'calendar'])->name('settings.cost-per-plate.calendar');

    // Eliminar deudas - salidas y pagos
    Route::get('debts-departures-payments', [DspController::class,'index'])->name('settings.dsp.index');

    // Usuarios
    Route::resource('users', UserController::class)->names('settings.users');

    // Conceptos
    Route::resource('concepts', ConceptController::class)->names('settings.concepts');

    // Salidas
    Route::get('departures', [DepartureController::class,'index'])->name('departures.index');
    Route::get('departures/monthly', [DepartureController::class,'monthly'])->name('departures.monthly');

    // Pagos
    Route::resource('payments', PaymentController::class)->names('payments');

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
    Route::get('cash/expenses',[CashController::class,'expenses'])->name('cash.expenses');
    Route::get('cash/report/general',[CashController::class,'generalReport'])->name('cash.report.general');
    Route::get('cash/report/est-draco-base',[CashController::class,'reportEstDracoBase'])->name('cash.report.est-draco-base');
    Route::get('cash/report/est-sal-pag-cont',[CashController::class,'reportEstSalPagCont'])->name('cash.report.est-sal-pag-cont');
    Route::get('cash/report/est-caja-ma',[CashController::class,'reportEstCajaMa'])->name('cash.report.est-caja-ma');

});
