<?php

namespace App\Http\Controllers;

use App\Exports\ExpensesExport;
use App\Exports\GeneralReportExport;
use App\Exports\IncomesExport;
use App\Exports\RepEstDracoBaseExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class CashController extends Controller
{
    //CashController
    public function __construct()
    {
        $this->middleware(['auth', 'permission:cash.incomes'])->only([
            'incomes', 'createIncome', 'editIncome', 'exportIncomes',
        ]);

        $this->middleware(['auth', 'permission:cash.expenses'])->only([
            'expenses', 'createExpense', 'editExpense', 'exportExpenses',
        ]);

        $this->middleware(['auth', 'permission:cash.report-general'])->only(['generalReport']);
        $this->middleware(['auth', 'permission:cash.report-draco'])->only(['reportEstDracoBase']);
        $this->middleware(['auth', 'permission:cash.report-sal-pag-cont'])->only(['reportEstSalPagCont']);
        $this->middleware(['auth', 'permission:cash.report-caja-ma'])->only(['reportEstCajaMa']);
    }

    public function open()
    {
        return view('cash.open');
    }

    public function movementReport()
    {
        return view('cash.movement-report');

    }

    public function incomes()
    {
        return view('cash.incomes');
    }

    public function createIncome()
    {
        return view('cash.create-income');
    }

    public function editIncome(int $id)
    {
        return view('cash.edit-income', ['incomeId' => $id]);
    }

    public function expenses()
    {
        return view('cash.expenses');
    }

    public function createExpense()
    {
        return view('cash.create-expense');
    }

    public function editExpense(int $id)
    {
        return view('cash.edit-expense', ['expenseId' => $id]);
    }

    public function generalReport()
    {
        return view('cash.general-report');
    }

    public function reportEstDracoBase()
    {
        return view('cash.report-est-draco-base');
    }

    public function reportEstSalPagCont()
    {
        return view('cash.rep-est-sal-pag-cont');
    }

    public function reportEstCajaMa()
    {
        return view('cash.rep-est-caja-ma');
    }

    public function exportIncomes(Request $request)
    {
        $search = $request->query('search', '');
        $filterType = (int) $request->query('filterType', 1);
        $date_start = $request->query('date_start');
        $date_end = $request->query('date_end');

        $filename = 'ingresos_'.now()->format('Ymd_His').'.xls';

        $export = new IncomesExport($search, $filterType, $date_start, $date_end);
        $html = view('exports.incomes', ['rows' => $export->htmlData()])->render();

        return response($html)
            ->header('Content-Type', 'application/vnd.ms-excel')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    public function exportExpenses(Request $request)
    {
        $search = $request->query('search', '');
        $filterType = (int) $request->query('filterType', 1);
        $date_start = $request->query('date_start');
        $date_end = $request->query('date_end');
        $user_id = $request->query('user_id') ? (int) $request->query('user_id') : null;
        $headquarter_id = $request->query('headquarter_id') ? (int) $request->query('headquarter_id') : null;

        $filename = 'egresos_'.now()->format('Ymd_His').'.xls';

        $export = new ExpensesExport($search, $filterType, $date_start, $date_end, $user_id, $headquarter_id);
        $html = view('exports.expenses', ['rows' => $export->htmlData()])->render();

        return response($html)
            ->header('Content-Type', 'application/vnd.ms-excel')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    public function exportGeneralReport(Request $request)
    {
        $month = (string) $request->query('month', now()->format('Y-m'));
        $year = (int) $request->query('year', now()->year);

        return Excel::download(new GeneralReportExport($year, $month), "reporte_general_{$month}_{$year}.xlsx");
    }

    public function exportDracoReport(Request $request)
    {
        $year = (int) $request->query('year', now()->year);

        return Excel::download(new RepEstDracoBaseExport($year), "rep_est_draco_{$year}.xlsx");
    }
}
