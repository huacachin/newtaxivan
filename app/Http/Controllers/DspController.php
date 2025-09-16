<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DspController extends Controller
{

    #TODO: Eliminar esta vista
   public function index(){
        return view('dsp.index');
    }
}
