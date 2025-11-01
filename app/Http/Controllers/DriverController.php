<?php

namespace App\Http\Controllers;

use App\Exports\DriversReportExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class DriverController extends Controller
{
    public function __construct(){
        $this->middleware(['auth','permission:configuracion.drivers'])->only(['index','export']);
    }
    public function index()
    {
        return view('drivers.index');
    }

    public function create()
    {
        return view('drivers.create');
    }

    public function edit(int $id)
    {
        return view('drivers.edit',compact('id'));
    }

    public function export(Request $request){
        $search = $request->query('search');
        $filter = $request->query('filter', 'plate');

        $filename = 'drivers_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new DriversReportExport($search, $filter),
            $filename
        );
    }
}
