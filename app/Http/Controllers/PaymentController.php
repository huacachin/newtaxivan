<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(){
        $this->middleware(['auth','permission:payments.view'])->only(['index']);
    }
    public function index()
    {
        return view('payments.index');
    }

}
