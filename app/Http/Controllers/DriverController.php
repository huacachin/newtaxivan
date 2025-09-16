<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function __construct(){
        $this->middleware(['auth','permission:drivers.view'])->only(['index']);
    }
    public function index()
    {
        return view('drivers.index');
    }
}
