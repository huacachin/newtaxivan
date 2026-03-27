<?php

namespace App\Http\Controllers;

use App\Exports\PaymentsDailyExport;
use App\Exports\PaymentsExport;
use App\Exports\PaymentsMonthlyExport;
use App\Exports\PaymentsStatsExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PaymentController extends Controller
{
    public function __construct(){
        $this->middleware(['auth','permission:payments'])->only(['index','add','edit','export']);
        $this->middleware(['auth', 'role:director|gerente|administrador'])->only(['monthly','daily','stats','exportMonthly','exportDaily','exportStats']);
    }
    public function index()
    {
        return view('payments.index');
    }

    public function daily(){
        return view('payments.daily');
    }

    public function monthly(){
        return view('payments.monthly');
    }

    public function stats(){
        return view('payments.stats');
    }

    public function add(){
        return view('payments.add');
    }

    public function edit($id){
        return view('payments.edit',compact('id'));
    }

    public function export(Request $request){

        $search        = (string) $request->query('search', '');
        $filter        = (string) $request->query('filter', '');
        $date_start    = $request->query('date_start');
        $date_end      = $request->query('date_end');
        $headquarterId = $request->query('headquarter_id', '');
        $type          = $request->query('type', '');

        $filename = 'pagos_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new PaymentsExport($search, $filter, $date_start, $date_end, $headquarterId, $type),
            $filename
        );
    }

    public function exportMonthly(Request $request){
        $year  = (int) $request->input('year', date('Y'));
        $month = (int) $request->input('month', date('n'));
        $cond  = $request->input('mov'); // opcional: GN/DT/EX/EX5

        $file = sprintf('reporte_pagos_%02d_%d.xlsx', $month, $year);

        return Excel::download(new PaymentsMonthlyExport($year, $month, $cond), $file);
    }

    public function exportDaily (Request $request){
        $year = (int) $request->integer('year', now()->year);
        $month = (int) $request->integer('month', now()->month);
        $mode = $request->get('mode', 'Pago'); // 'Pago' | 'Caja'

        $file = sprintf('reporte-diario-%d-%02d-%s.xlsx', $year, $month, strtolower($mode));
        return Excel::download(new PaymentsDailyExport($year, $month, $mode), $file);
    }

    public function exportStats(Request $request){
        $year  = (int) $request->integer('year');
        $month = (int) $request->integer('month');
        $file  = sprintf('reporte-estadistico-%04d-%02d.xlsx', $year, $month);
        return Excel::download(new PaymentsStatsExport($year, $month), $file);
    }

}
