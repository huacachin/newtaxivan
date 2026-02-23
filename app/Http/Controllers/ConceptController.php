<?php

namespace App\Http\Controllers;

use App\Models\Concept;
use Illuminate\Http\Request;

class ConceptController extends Controller
{
    public function __construct(){
        $this->middleware(['auth','permission:configuracion.concepts'])->only(['index','create','edit']);
        $this->middleware(['auth','role:admin'])->only(['create','edit']);
    }

    public function index()
    {
        return view('concepts.index');
    }

    public function create()
    {
        return view('concepts.create');
    }

    public function edit(Concept $concept)
    {
        return view('concepts.edit', ['conceptId' => $concept->id]);
    }
}
