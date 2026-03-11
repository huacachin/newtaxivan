<?php

namespace App\Http\Controllers;

use App\Exports\DeparturesExport;
use App\Exports\DeparturesMonthly;
use App\Exports\DeparturesMonthlyByStopExport;
use App\Exports\DeparturesStatsMonthlyExport;
use App\Exports\DeparturesWorkbookExport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class DepartureController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:departures'])->only(['index','add','edit','export','byDebt']);
        $this->middleware(['auth', 'role:admin'])->only(['monthly','rmp','stats','exportMonthly','exportRmp','exportStats']);
    }

    public function index()
    {
        return view('departures.index');
    }

    public function monthly()
    {
        return view('departures.monthly');
    }

    public function rmp(){
        return view('departures.rmp');
    }

    public function stats(){
        return view('departures.stats');
    }

    public function byDebt(){
        return view('departures.by-debt');
    }

    public function add(){
        return view('departures.add');
    }

    public function edit($id){
        return view('departures.edit',compact('id'));
    }

    public function export(Request $request)
    {
        $searchType = (int)$request->query('searchType', 1);
        $searchText = $request->query('searchText');
        $fromDate = $request->query('fromDate');
        $toDate = $request->query('toDate');
        $groupMode = filter_var($request->query('groupMode', false), FILTER_VALIDATE_BOOLEAN);

        $filename = 'salidas_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download(
            new DeparturesExport($searchType, $searchText, $fromDate, $toDate, $groupMode),
            $filename
        );
    }

    public function exportRmp(Request $request){
        $now   = CarbonImmutable::now();
        $year  = (int) ($request->integer('year') ?: $now->year);
        $month = (int) ($request->integer('month') ?: $now->month);

        $file = sprintf('Reporte_Mensual_Por_Paradero.VT.xlsx', $year, $month);

        return Excel::download(new DeparturesMonthlyByStopExport($year, $month), $file);
    }

    public function exportMonthly(Request $request){
        $now   = CarbonImmutable::now();
        $year  = (int) ($request->integer('year') ?: $now->year);
        $month = (int) ($request->integer('month') ?: $now->month);

        $file = sprintf('REPORTE_MENSUAL_VT_%04d_%02d.xlsx', $year, $month);

        return Excel::download(new DeparturesMonthly($year, $month), $file);
    }

    public function exportStats(Request $request){
        $now   = CarbonImmutable::now();
        $year  = (int) ($request->integer('year')  ?: $now->year);
        $month = (int) ($request->integer('month') ?: $now->month);

        $file = sprintf('Estadistico_Salidas_%04d_%02d.xlsx', $year, $month);

        return Excel::download(new DeparturesStatsMonthlyExport($year, $month), $file);
    }

}
