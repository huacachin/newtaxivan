<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OwnerController extends Controller
{
    public function __construct(){
        $this->middleware(['auth','permission:owners.view'])->only(['index']);
    }
    public function index()
    {
        return view('owners.index');
    }

}
