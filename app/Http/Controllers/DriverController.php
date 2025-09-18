<?php

namespace App\Http\Controllers;

use App\Exports\DriversReportExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class DriverController extends Controller
{
    public function __construct(){
        $this->middleware(['auth','permission:drivers.view'])->only(['index']);
    }
    public function index()
    {
        return view('drivers.index');
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
