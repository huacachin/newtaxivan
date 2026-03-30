<?php

namespace App\Http\Controllers;

use App\Exports\VehiclesReportExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class VehicleController extends Controller
{
    public function __construct(){
        $this->middleware(['auth','permission:configuracion.vehicles'])->only(['index','export']);
    }
    public function index()
    {
        return view('vehicles.index');
    }

    public function create()
    {
        return view('vehicles.create');
    }

    public function edit(int $id)
    {
        return view('vehicles.edit',compact('id'));
    }

    public function export(Request $request){
        $status = $request->query('status', 'active');
        $search = $request->query('search');
        $filter = $request->query('filter', 'plate');

        $filename = 'vehiculos_' . now()->format('Ymd_His') . '.xls';

        $export = new VehiclesReportExport($status, $search, $filter);
        $data = $export->htmlData();
        $html = view('exports.vehicles', $data)->render();

        return response($html)
            ->header('Content-Type', 'application/vnd.ms-excel')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }


}
