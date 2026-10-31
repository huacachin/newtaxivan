<?php

namespace App\Http\Controllers;

use App\Exports\DrawSimulationExport;
use App\Models\Draw;
use App\Services\DrawService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DrawController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:draws'])->only(['index', 'simulation', 'exportSimulation']);
    }

    public function index(): View
    {
        return view('draws.index');
    }

    /**
     * Página pública (sin login) del orden de salida de un sorteo, la que se
     * comparte por WhatsApp. Si se anuló sigue abriendo, marcada como anulada
     * y con el link al sorteo vigente de ese día si lo hay.
     */
    public function show(Request $request, string $code): View
    {
        $draw = Draw::where('code', $code)->firstOrFail();
        $entries = $draw->entries()->whereNotNull('number')->get(['number', 'plate', 'driver_name']);
        $replacement = $draw->isAnnulled()
            ? Draw::active()->whereDate('date', $draw->date)->first(['id', 'code'])
            : null;

        return view('draws.public', [
            'title' => Draw::orderTitleFor($draw->date->toDateString()),
            'dayTitle' => Draw::dayTitleFor($draw->date->toDateString()),
            'code' => $draw->code,
            'drawnAt' => $draw->created_at->format('d/m/Y H:i'),
            'participants' => $draw->participants,
            'items' => DrawService::orderItems($entries),
            'simulated' => false,
            'annulledAt' => $draw->annulled_at?->format('d/m/Y H:i'),
            'replacementUrl' => $replacement?->publicUrl(),
            'shareUrl' => $draw->publicUrl(),
            'whatsappUrl' => $draw->whatsappUrl(),
            'reveal' => $request->boolean('revelar'),
        ]);
    }

    /**
     * Un día de la simulación del mes (no se guarda): misma semilla, mínimo y
     * máximo que la grilla, así muestra exactamente lo mismo. Solo con sesión.
     */
    public function simulation(Request $request, string $date): View
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

        return view('draws.public', [
            'title' => Draw::orderTitleFor($date),
            'dayTitle' => Draw::dayTitleFor($date),
            'code' => 'Simulación',
            'drawnAt' => null,
            'participants' => count($items),
            'items' => $items,
            'simulated' => true,
            'annulledAt' => null,
            'replacementUrl' => null,
            'shareUrl' => null,
            'whatsappUrl' => null,
            'reveal' => $request->boolean('revelar'),
        ]);
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
}
