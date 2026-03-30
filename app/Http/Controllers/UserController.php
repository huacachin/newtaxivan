<?php

namespace App\Http\Controllers;

use App\Exports\UsersReportExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{

    public function __construct(){
        $this->middleware(['auth','permission:configuracion.users'])->only(['index','edit','export']);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('users.index');
    }

    public function create(){
        return view('users.create');
    }

    public function edit($id){
        return view('users.edit',compact('id'));
    }

    public function perms($id){
        return view('users.perms',compact('id'));
    }

    public function export(Request $request)
    {
        $search   = $request->query('search');
        $filename = 'usuarios_' . now()->format('Ymd_His') . '.xls';

        $export = new UsersReportExport($search);
        $html = view('exports.users', ['rows' => $export->htmlData()])->render();

        return response($html)
            ->header('Content-Type', 'application/vnd.ms-excel')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
}
