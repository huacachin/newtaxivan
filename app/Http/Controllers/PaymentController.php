<?php

namespace App\Http\Controllers;

use App\Exports\PaymentsExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PaymentController extends Controller
{
    public function __construct(){
        $this->middleware(['auth','permission:payments.view'])->only(['index']);
    }
    public function index()
    {
        return view('payments.index');
    }

    public function daily(){
        return view('payments.daily');
    }

    public function export(Request $request){

        $search        = (string) $request->query('search', '');
        $filter        = (string) $request->query('filter', '');
        $date_start    = $request->query('date_start');
        $date_end      = $request->query('date_end');
        $headquarterId = $request->query('headquarter_id', '');
        $type          = $request->query('type', '');

        $filename = 'payments_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new PaymentsExport($search, $filter, $date_start, $date_end, $headquarterId, $type),
            $filename
        );
    }

}
