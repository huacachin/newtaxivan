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

}
