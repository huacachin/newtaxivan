<?php

namespace App\Http\Controllers;

use App\Exports\HeadquartersReportExport;
use App\Models\Headquarter;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class HeadquarterController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:configuracion.headquarters']);
        $this->middleware(['auth', 'role:director'])->only(['create', 'edit']);
    }

    public function index()
    {
        return view('headquarters.index');
    }

    public function create()
    {
        return view('headquarters.create');
    }

    public function edit(int $id)
    {
        return view('headquarters.edit', ['headquarterId' => $id]);
    }

    public function export(Request $request)
    {
        return Excel::download(
            new HeadquartersReportExport($request->get('search')),
            'sucursales.xlsx'
        );
    }
}
