<?php

namespace App\Http\Controllers;

use App\Exports\OwnersReportExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class OwnerController extends Controller
{
    public function __construct(){
        $this->middleware(['auth','permission:configuracion.owners'])->only(['index','export']);
    }
    public function index()
    {
        return view('owners.index');
    }

    public function create()
    {
        return view('owners.create');
    }

    public function edit(int $id)
    {
        return view('owners.edit',compact('id'));
    }

    public function export(Request $request){
        $search = $request->query('search');
        $filter = $request->query('filter', 'plate');

        $filename = 'propietarios_' . now()->format('Ymd_His') . '.xls';

        $export = new OwnersReportExport($search, $filter);
        $data = $export->htmlData();
        $html = view('exports.owners', $data)->render();

        return response($html)
            ->header('Content-Type', 'application/vnd.ms-excel')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

}
