<?php

namespace App\Http\Controllers;

use App\Exports\OwnersReportExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class OwnerController extends Controller
{
    public function __construct(){
        $this->middleware(['auth','permission:owners.view'])->only(['index']);
    }
    public function index()
    {
        return view('owners.index');
    }

    public function export(Request $request){
        $search = $request->query('search');
        $filter = $request->query('filter', 'plate');

        $filename = 'owners_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new OwnersReportExport($search, $filter),
            $filename
        );
    }

}
