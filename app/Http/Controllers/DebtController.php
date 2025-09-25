<?php

namespace App\Http\Controllers;

use App\Exports\DebtsPerDaysExport;
use App\Exports\DelayDetailsExport;
use App\Exports\MonthlyDebtExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class DebtController extends Controller
{
    public function __construct(){
        $this->middleware(['auth','permission:debts.view'])->only([
            'debtPerDays','monthly','monthlyDetail'
        ]);
    }

    public function debtPerDays(){
        return view('debts.debt-per-days');
    }


    #TODO: Eliminar vista
    public function generate(){
        return view('debts.generate');
    }

    public function monthly(){
        return view('debts.monthly');
    }

    #TODO: Eliminar vista
    public function delete(){
        return view('debts.delete');
    }

    public function monthlyDetail($id){
        return view('debts.monthly-detail',compact('id'));
    }

    public function export(Request $request){
        $monthDate  = (string) $request->query('monthDate', now()->toDateString());
        $onlyActive = filter_var($request->query('onlyActive', true), FILTER_VALIDATE_BOOLEAN);
        $condition  = (string) $request->query('condition', '');

        $filename = 'debt_per_days_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new DebtsPerDaysExport($monthDate, $onlyActive, $condition),
            $filename
        );
    }

    public function exportDetail(Request $request){
        $monthDate   = (string) $request->query('monthDate', now()->toDateString());
        $onlyActive  = filter_var($request->query('onlyActive', true), FILTER_VALIDATE_BOOLEAN);
        $condition   = (string) $request->query('condition', '');
        $plateFilter = $request->query('plate'); // opcional

        // Excluir sedes (coma separada) — opcional
        $exclude     = $request->query('exclude_heads', 'Huachipa,Lima');
        $excludeArr  = array_filter(array_map('trim', explode(',', $exclude)));

        $filename = 'retrasos_detalle_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new DelayDetailsExport($monthDate, $onlyActive, $condition, $plateFilter, $excludeArr),
            $filename
        );
    }

    public function exportMonthly(Request $request){
        $month     = (string) $request->query('month', now()->startOfMonth()->toDateString());
        $search    = (string) $request->query('search', '');
        $condition = (string) $request->query('condition', '');

        $file = 'deuda_mensual_'.now()->format('Ymd_His').'.xlsx';
        return Excel::download(new MonthlyDebtExport($month, $search, $condition), $file);
    }
}
