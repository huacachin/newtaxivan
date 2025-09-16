<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DepartureController extends Controller
{
    public function __construct(){
        $this->middleware(['auth','permission:departures.view'])->only(['index']);
    }
    public function index()
    {
        return view ('departures.index');
    }

    public function monthly(){
        return view ('departures.monthly');
    }

}
