<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CostPerPlateController extends Controller
{

    public function __construct(){
        $this->middleware(['auth','permission:cost-per-plate.view'])->only(['index','day','calendar']);
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

}
