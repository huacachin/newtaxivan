<?php

namespace App\Http\Controllers;

use App\Exports\ConceptsReportExport;
use App\Models\Concept;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ConceptController extends Controller
{
    public function __construct(){
        $this->middleware(['auth','permission:configuracion.concepts'])->only(['index','create','edit']);
        $this->middleware(['auth','role:director'])->only(['create','edit']);
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

    public function export(Request $request)
    {
        $export = new ConceptsReportExport($request->get('search'));
        $html = view('exports.concepts', ['rows' => $export->htmlData()])->render();

        return response($html)
            ->header('Content-Type', 'application/vnd.ms-excel')
            ->header('Content-Disposition', 'attachment; filename="conceptos.xls"');
    }
}
