<?php

namespace App\Http\Controllers;

use App\Models\CostPerPlate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CostPerPlateController extends Controller
{

    public function __construct(){
        $this->middleware(['auth','permission:configuracion.cost-per-plate'])->only(['index','day','calendar','export']);
    }

   public function index(){
       return view('cost-per-plate.index');
   }

   public function day($year,$month){
       return view('cost-per-plate.cost-per-plate-day',compact('year','month'));
   }

   public function calendar($plate,$year,$month){
        return view('cost-per-plate.calendar',compact('plate','year','month'));
    }

    public function export()
    {
        $result = CostPerPlate::from('cost_per_plates as c')
            ->join('vehicles as v', 'v.id', '=', 'c.vehicle_id')
            ->where('v.status', 'active')
            ->selectRaw('c.year, c.month, COUNT(DISTINCT c.vehicle_id) as plates, MIN(c.amount) as amount')
            ->groupBy('c.year', 'c.month')
            ->orderByDesc('c.year')->orderByDesc('c.month')
            ->get();

        $html = view('exports.cost-per-plate', compact('result'))->render();

        return response($html)
            ->header('Content-Type', 'application/vnd.ms-excel')
            ->header('Content-Disposition', 'attachment; filename="costo-por-placa.xls"');
    }

    public function exportDay(Request $request)
    {
        $year  = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);
        $plate = $request->get('plate');

        $now = now('America/Lima');
        $daysInMonth = \Carbon\Carbon::create($year, $month, 1)->daysInMonth;
        $dom = min($now->day, $daysInMonth);
        $targetDate = \Carbon\Carbon::create($year, $month, $dom)->toDateString();

        $result = DB::table('cost_per_plate_days as d')
            ->join('vehicles as v', 'v.id', '=', 'd.vehicle_id')
            ->where('v.status', 'active')
            ->when($plate, fn($q) => $q->where('v.plate', 'like', '%'.$plate.'%'))
            ->where('d.year', $year)
            ->where('d.month', $month)
            ->whereDate('d.date', $targetDate)
            ->groupBy('v.plate', 'd.year', 'd.month')
            ->orderByRaw('COALESCE(v.sort_order, 999999)')
            ->get([
                'v.plate',
                'd.year',
                'd.month',
                DB::raw('SUM(d.amount) as amount'),
            ]);

        $months = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
        $monthName = $months[$month] ?? $month;

        $html = view('exports.cost-per-plate-day', compact('result', 'year', 'month', 'monthName', 'targetDate'))->render();

        $file = sprintf('costo-por-placa-dia-%04d-%02d.xls', $year, $month);

        return response($html)
            ->header('Content-Type', 'application/vnd.ms-excel')
            ->header('Content-Disposition', "attachment; filename=\"{$file}\"");
    }

}
