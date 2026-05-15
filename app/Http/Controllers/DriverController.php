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

    public function expiration(int $id, string $field)
    {
        abort_unless(in_array($field, ['documento','licencia','cartilla-informativa','credencial'], true), 404);
        return view('drivers.expiration', compact('id','field'));
    }

    public function export(Request $request){
        $search = $request->query('search');
        $filter = $request->query('filter', 'plate');

        $filename = 'conductores_' . now()->format('Ymd_His') . '.xls';

        $export = new DriversReportExport($search, $filter);
        $data = $export->htmlData();
        $html = view('exports.drivers', $data)->render();

        return response($html)
            ->header('Content-Type', 'application/vnd.ms-excel')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
}
