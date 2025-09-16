<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CashController extends Controller
{



    public function __construct(){
        $this->middleware(['auth','permission:cash.view'])->only([
            'open','incomes','expenses'
        ]);

        $this->middleware(['auth','permission:cash.report'])->only([
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
}
