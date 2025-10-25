<?php

namespace App\Http\Controllers;

use App\Exports\ExpensesExport;
use App\Exports\GeneralReportExport;
use App\Exports\IncomesExport;
use App\Exports\RepEstDracoBaseExport;
use App\Livewire\Cash\Expenses;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class CashController extends Controller
{



    public function __construct(){
        $this->middleware(['auth','permission:cash.incomes'])->only([
            'incomes','exportIncomes'
        ]);

        $this->middleware(['auth','permission:cash.expenses'])->only([
            'expenses','exportExpenses'
        ]);

        $this->middleware(['auth','permission:cash.reports'])->only([
            'movementReport','generalReport',
            'reportEstDracoBase','reportEstSalPagCont','reportEstCajaMa'
        ]);
    }

    public function open()
    {
        return view('cash.open');
    }

    public function movementReport()
    {
        return view('cash.movement-report');

    }

    public function incomes(){
        return view('cash.incomes');
    }

    public function expenses(){
        return view('cash.expenses');
    }

    public function generalReport(){
        return view('cash.general-report');
    }

    public function reportEstDracoBase(){
        return view('cash.report-est-draco-base');
    }

    public function reportEstSalPagCont(){
        return view('cash.rep-est-sal-pag-cont');
    }

    public function reportEstCajaMa(){
        return view('cash.rep-est-caja-ma');
    }

    public function exportIncomes(Request $request){
        $search     = $request->query('search', '');
        $filterType = (int) $request->query('filterType', 1);
        $date_start = $request->query('date_start');
        $date_end   = $request->query('date_end');

        $filename = 'incomes_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new IncomesExport($search, $filterType, $date_start, $date_end),
            $filename
        );
    }

    public function exportExpenses(Request $request){
        $search     = $request->query('search', '');
        $filterType = (int) $request->query('filterType', 1);
        $date_start = $request->query('date_start');
        $date_end   = $request->query('date_end');

        $filename = 'expenses_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new ExpensesExport($search, $filterType, $date_start, $date_end),
            $filename
        );
    }

    public function exportGeneralReport(Request $request){
        $month = (string) $request->query('month', now()->format('Y-m'));
        $year = (int) $request->query('year', now()->year);
        return Excel::download(new GeneralReportExport($year,$month), "reporte_general_{$month}_{$year}.xlsx");
    }

    public function exportDracoReport(Request $request){
        $year = (int) $request->query('year', now()->year);
        return Excel::download(new RepEstDracoBaseExport($year), "rep_est_draco_{$year}.xlsx");
    }
}
