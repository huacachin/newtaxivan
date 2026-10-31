<?php

namespace App\Http\Controllers;

use App\Exports\DrawSimulationExport;
use App\Models\Draw;
use App\Services\DrawService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DrawController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:draws'])->only(['index', 'pdf', 'simulation', 'exportSimulation']);
    }

    public function index(): View
    {
        return view('draws.index');
    }

    /**
     * PDF del orden de salida de un sorteo, para mandarlo por WhatsApp como
     * archivo. Un sorteo anulado sigue saliendo, marcado como anulado.
     */
    public function pdf(Request $request, Draw $draw): Response
    {
        $date = $draw->date->toDateString();
        $entries = $draw->entries()->whereNotNull('number')->get(['number', 'plate', 'driver_name']);

        return $this->orderPdf($request, [
            'title' => Draw::orderTitleFor($date),
            'dayTitle' => Draw::dayTitleFor($date),
            'code' => $draw->code,
            'drawnAt' => $draw->created_at->format('d/m/Y H:i'),
            'participants' => $draw->participants,
            'items' => DrawService::orderItems($entries),
            'simulated' => false,
            'annulledAt' => $draw->annulled_at?->format('d/m/Y H:i'),
        ], 'orden_salida_'.$this->fileDay($date).'_'.$draw->code.'.pdf');
    }

    /**
     * PDF de un día de la simulación del mes (no se guarda): misma semilla,
     * mínimo y máximo que la grilla, así muestra exactamente lo mismo.
     */
    public function simulation(Request $request, string $date): Response
    {
        $data = $request->validate([
            'seed' => 'required|integer|min:1|max:2147483647',
            'min' => 'required|integer|min:1|max:999',
            'max' => 'required|integer|gte:min|max:999',
        ]);
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date), 404);

        $day = Carbon::parse($date);
        $dates = array_column(DrawService::workingDaysOfMonth($day->year, $day->month), 'date');
        abort_unless(in_array($date, $dates, true), 404);

        $service = new DrawService(new Randomizer(new Mt19937((int) $data['seed'])));
        $items = DrawService::orderItems($service->simulateMonth($dates, (int) $data['min'], (int) $data['max'])[$date] ?? []);

        return $this->orderPdf($request, [
            'title' => Draw::orderTitleFor($date),
            'dayTitle' => Draw::dayTitleFor($date),
            'code' => null,
            'drawnAt' => null,
            'participants' => count($items),
            'items' => $items,
            'simulated' => true,
            'annulledAt' => null,
        ], 'simulacion_orden_salida_'.$this->fileDay($date).'.pdf');
    }

    /**
     * Excel de la simulación que se está viendo en la grilla (misma semilla,
     * mínimo y máximo, así sale idéntica). No guarda nada.
     */
    public function exportSimulation(Request $request): BinaryFileResponse
    {
        $data = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'seed' => 'required|integer|min:1|max:2147483647',
            'min' => 'required|integer|min:1|max:999',
            'max' => 'required|integer|gte:min|max:999',
        ]);

        $file = sprintf('simulacion_sorteos_%04d_%02d_%s.xlsx', $data['year'], $data['month'], now()->format('Ymd_His'));

        return Excel::download(
            new DrawSimulationExport((int) $data['year'], (int) $data['month'], (int) $data['seed'], (int) $data['min'], (int) $data['max']),
            $file,
        );
    }

    /** "lunes_2026-11-02" para el nombre del archivo (sin tildes: miercoles, sabado). */
    private function fileDay(string $date): string
    {
        return Str::slug(Carbon::parse($date)->translatedFormat('l')).'_'.$date;
    }

    /**
     * Arma el PDF A4 del orden de salida. Con ?descargar=1 baja el archivo;
     * si no, lo abre en el navegador (desde ahí también se comparte).
     *
     * @param  array<string, mixed>  $data
     */
    private function orderPdf(Request $request, array $data, string $filename): Response
    {
        $pdf = Pdf::loadView('draws.pdf', $data + ['generatedAt' => now()->format('d/m/Y H:i')])->setPaper('a4');

        return $request->boolean('descargar') ? $pdf->download($filename) : $pdf->stream($filename);
    }
}
