<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function __construct(){
        $this->middleware(['auth','permission:vehicles.view'])->only(['index']);
    }
    public function index()
    {
        return view('vehicles.index');
    }


}
