<?php

namespace App\Http\Controllers;

use App\Exports\VehiclesReportExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class VehicleController extends Controller
{
    public function __construct(){
        $this->middleware(['auth','permission:vehicles.view'])->only(['index']);
    }
    public function index()
    {
        return view('vehicles.index');
    }

    public function export(Request $request){
        $status = $request->query('status', 'active');
        $search = $request->query('search');
        $filter = $request->query('filter', 'plate');

        $filename = 'vehiculos_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new VehiclesReportExport($status, $search, $filter),
            $filename
        );
    }


}
