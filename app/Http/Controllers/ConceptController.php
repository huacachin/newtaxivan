<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ConceptController extends Controller
{
    public function __construct(){
        $this->middleware(['auth','permission:concepts.view'])->only(['index']);
    }
    public function index()
    {
        return view ('concepts.index');
    }


}
