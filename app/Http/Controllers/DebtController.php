<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
}
