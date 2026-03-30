<?php

namespace App\Livewire\Cash;

use App\Exports\CajaEstadisticaExport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

class RepEstCajaMa extends Component
{
    /* =======================
     *  Tablas / Columnas
     * ======================= */
    private const TBL_PAYMENTS  = 'payments';     // Pagos (cotización/retraso/deuda)
    private const PAY_DATE      = 'date_register';// <-- CAMPO CORRECTO
    private const PAY_HQ_ID     = 'headquarter_id';
    private const PAY_TYPE      = 'type';         // 'PAGO'|'RETRASO'|'DEUDA'
    private const PAY_AMOUNT    = 'amount';

    private const TBL_DEPARTURES = 'departures';  // Salidas
    private const DEP_DATE       = 'date';
    private const DEP_HQ_ID      = 'headquarter_id';
    private const DEP_IS_SUPPORT = 'is_support';  // 0 empresa / 1 apoyo
    private const DEP_PRICE      = 'price';

    private const TBL_INCOMES = 'incomes';        // Otros (ingresos varios)
    private const INC_DATE    = 'date';
    private const INC_HQ_ID   = 'headquarter_id';
    private const INC_TOTAL   = 'total';

    private const TBL_EXPENSES  = 'expenses';     // Egresos
    private const EXP_DATE      = 'date';
    private const EXP_HQ_ID     = 'headquarter_id';
    private const EXP_TOTAL     = 'total';

    private const TBL_HQ        = 'headquarters';
    private const HQ_ID         = 'id';
    private const HQ_NAME       = 'name';

    /* =======================
     *  Filtros (persisten en la URL)
     * ======================= */
    public ?int $year = null;           // YYYY
    public ?int $month = null;          // 1..12
    public ?int $headquarterId = null;  // opcional

    protected $queryString = [
        'year'          => ['except' => null],
        'month'         => ['except' => null],
        'headquarterId' => ['except' => null],
    ];

    /* =======================
     *  Datos cuadro mensual (por día)
     * ======================= */
    public array $rows = [];        // filas dd/mm/YYYY
    public array $totales = [];     // totales del mes
    public array $promedios = [];   // promedios del mes (por día mostrado)

    /* =======================
     *  Datos cuadro anual (por mes)
     * ======================= */
    public array $anual = [];           // Solo meses con movimiento
    public array $anualTotales = [];    // totales año (solo de meses mostrados)
    public array $anualPromedios = [];  // promedios año (divididos entre meses mostrados)
    public int   $anualMesesMostrados = 0;

    /* Para combos */
    public array $headquarters = [];

    /* =======================
     *  Ciclo de vida
     * ======================= */
    public function mount(?int $year = null, ?int $month = null, ?int $headquarterId = null): void
    {
        $today = now();

        // Solo setear defaults si están NULL (evita "volver" al mes actual en remounts)
        if ($this->year === null) {
            $this->year = $year ?? (int) $today->format('Y');
        }
        if ($this->month === null) {
            $this->month = $month ?? (int) $today->format('n');
        }
        if ($this->headquarterId === null) {
            $this->headquarterId = $headquarterId;
        }

        $this->headquarters = DB::table(self::TBL_HQ)
            ->select(self::HQ_ID.' as id', self::HQ_NAME.' as name')
            ->orderBy('name')
            ->get()
            ->map(fn($r) => ['id' => (int)$r->id, 'name' => $r->name])
            ->toArray();

        $this->consultar();
    }

    public function updated($field): void
    {
        if (in_array($field, ['year', 'month', 'headquarterId'], true)) {
            $this->consultar();
        }
    }

    /* =======================
     *  Consultar datos
     * ======================= */
    public function consultar(): void
    {
        $y  = (int) ($this->year ?: date('Y'));
        $m  = (int) ($this->month ?: date('n'));
        [$start, $end] = $this->monthRange($y, $m);
        $days = $this->daysInMonth($y, $m);

        // 1) Pagos por día/tipo  (ONLY_FULL_GROUP_BY safe)
        $pagos = DB::table(self::TBL_PAYMENTS)
            ->selectRaw('DATE('.self::PAY_DATE.') as d, '.self::PAY_TYPE.' as t, SUM('.self::PAY_AMOUNT.') as s')
            ->whereBetween(self::PAY_DATE, [$start, $end])
            ->when($this->headquarterId, fn($q) => $q->where(self::PAY_HQ_ID, $this->headquarterId))
            ->groupBy(DB::raw('DATE('.self::PAY_DATE.')'), self::PAY_TYPE)
            ->get()
            ->mapWithKeys(fn($r) => [ "{$r->d}|{$r->t}" => (float)$r->s ])
            ->all();

        // 2) Salidas por día/flag (0 empresa / 1 apoyo)
        $salidas = DB::table(self::TBL_DEPARTURES)
            ->selectRaw('DATE('.self::DEP_DATE.') as d, '.self::DEP_IS_SUPPORT.' as spt, SUM('.self::DEP_PRICE.') as s')
            ->whereBetween(self::DEP_DATE, [$start, $end])
            ->when($this->headquarterId, fn($q) => $q->where(self::DEP_HQ_ID, $this->headquarterId))
            ->groupBy(DB::raw('DATE('.self::DEP_DATE.')'), self::DEP_IS_SUPPORT)
            ->get()
            ->mapWithKeys(fn($r) => [ "{$r->d}|{$r->spt}" => (float)$r->s ])
            ->all();

        // 3) Otros por día (SIN filtro por mode; SUM(total) agrupado por DATE(date))
        $otros = DB::table(self::TBL_INCOMES)
            ->selectRaw('DATE('.self::INC_DATE.') as d, SUM('.self::INC_TOTAL.') as s')
            ->whereBetween(self::INC_DATE, [$start, $end])
            ->when($this->headquarterId, fn($q) => $q->where(self::INC_HQ_ID, $this->headquarterId))
            ->groupBy(DB::raw('DATE('.self::INC_DATE.')'))
            ->pluck('s','d')
            ->all();

        // 4) Egresos por día
        $egresos = DB::table(self::TBL_EXPENSES)
            ->selectRaw('DATE('.self::EXP_DATE.') as d, SUM('.self::EXP_TOTAL.') as s')
            ->whereBetween(self::EXP_DATE, [$start, $end])
            ->when($this->headquarterId, fn($q) => $q->where(self::EXP_HQ_ID, $this->headquarterId))
            ->groupBy(DB::raw('DATE('.self::EXP_DATE.')'))
            ->pluck('s','d')
            ->all();

        // ---- Armar filas diarias (mensual)
        $this->rows = [];
        $sum = [
            'pago' => 0, 'retraso' => 0, 'deuda' => 0, 'pago_total' => 0,
            'empresa' => 0, 'apoyo' => 0, 'salidas_total' => 0,
            'otros' => 0, 'ingresos_total' => 0,
            'egreso' => 0, 'utilidad' => 0,
        ];

        for ($day = 1; $day <= $days; $day++) {
            $date  = Carbon::create($y, $m, $day)->toDateString(); // YYYY-mm-dd
            $label = Carbon::create($y, $m, $day)->format('d/m/Y');

            $cotizacion = (float) ($pagos["{$date}|PAGO"]    ?? 0);
            $retraso    = (float) ($pagos["{$date}|RETRASO"] ?? 0);
            $deuda      = (float) ($pagos["{$date}|DEUDA"]   ?? 0);
            $pagoTotal  = $cotizacion + $retraso + $deuda;

            $empresa    = (float) ($salidas["{$date}|0"] ?? 0);
            $apoyo      = (float) ($salidas["{$date}|1"] ?? 0);
            $salTotal   = $empresa + $apoyo;

            $otrosDia   = (float) ($otros[$date]   ?? 0);
            $egreso     = (float) ($egresos[$date] ?? 0);

            $ingTotal   = $pagoTotal + $salTotal + $otrosDia;
            $utilidad   = $ingTotal - $egreso;

            $this->rows[] = [
                'fecha'         => $label,
                'cotizacion'    => $cotizacion,
                'retraso'       => $retraso,
                'deuda'         => $deuda,
                'pago_total'    => $pagoTotal,
                'empresa'       => $empresa,
                'apoyo'         => $apoyo,
                'salidas_total' => $salTotal,
                'otros'         => $otrosDia,
                'ingresos_total'=> $ingTotal,
                'egreso'        => $egreso,
                'utilidad'      => $utilidad,
            ];

            // Acumular totales
            $sum['pago']           += $cotizacion;
            $sum['retraso']        += $retraso;
            $sum['deuda']          += $deuda;
            $sum['pago_total']     += $pagoTotal;
            $sum['empresa']        += $empresa;
            $sum['apoyo']          += $apoyo;
            $sum['salidas_total']  += $salTotal;
            $sum['otros']          += $otrosDia;
            $sum['ingresos_total'] += $ingTotal;
            $sum['egreso']         += $egreso;
            $sum['utilidad']       += $utilidad;
        }

        $this->totales = $sum;

        // Promedios del cuadro mensual (por día del mes mostrado)
        $divisorMensual = max(1, count($this->rows));
        $this->promedios = array_map(
            fn($v) => $v / $divisorMensual,
            $this->totales
        );

        // ---- Cuadro anual (solo meses con movimiento)
        $currYear = (int) now()->format('Y');
        $limitMonth = ($y === $currYear)
            ? max(1, min(12, (int) ($this->month ?: 12)))
            : 12;

        $this->buildAnnual($y, $limitMonth);
    }

    /* =======================
     *  Auxiliares
     * ======================= */
    private function monthRange(int $y, int $m): array
    {
        $start = Carbon::create($y, $m, 1)->startOfDay()->toDateTimeString();
        $end   = Carbon::create($y, $m, 1)->endOfMonth()->endOfDay()->toDateTimeString();
        return [$start, $end];
    }

    private function daysInMonth(int $y, int $m): int
    {
        return Carbon::create($y, $m, 1)->endOfMonth()->day;
    }

    public static function monthName(int $m): string
    {
        return [
            1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
            7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
        ][$m] ?? (string)$m;
    }

    private static function rowHasMovement(array $row): bool
    {
        // Consideramos movimiento si hay algún valor > 0 en ingresos o egresos
        return (
            ($row['pago_total'] ?? 0) > 0 ||
            ($row['salidas_total'] ?? 0) > 0 ||
            ($row['otros'] ?? 0) > 0 ||
            ($row['egreso'] ?? 0) > 0
        );
    }

    private function buildAnnual(int $year, int $limitMonth = 12): void
    {
        $this->anual = [];
        $sum = [
            'pago'=>0,'retraso'=>0,'deuda'=>0,'pago_total'=>0,
            'empresa'=>0,'apoyo'=>0,'salidas_total'=>0,
            'otros'=>0,'ingresos_total'=>0,
            'egreso'=>0,'utilidad'=>0,
        ];
        $mesesMostrados = 0;

        $limitMonth = max(1, min(12, $limitMonth));

        for ($m=1; $m<= $limitMonth; $m++) {
            [$start, $end] = $this->monthRange($year, $m);

            // Pagos mes
            $pagos = DB::table(self::TBL_PAYMENTS)
                ->selectRaw(self::PAY_TYPE." as t, SUM(".self::PAY_AMOUNT.") as s")
                ->whereBetween(self::PAY_DATE, [$start, $end])
                ->when($this->headquarterId, fn($q) => $q->where(self::PAY_HQ_ID, $this->headquarterId))
                ->groupBy(self::PAY_TYPE)
                ->pluck('s','t');

            $cotizacion = (float) ($pagos['PAGO']    ?? 0);
            $retraso    = (float) ($pagos['RETRASO'] ?? 0);
            $deuda      = (float) ($pagos['DEUDA']   ?? 0);
            $pagoTotal  = $cotizacion + $retraso + $deuda;

            // Salidas mes
            $salidas = DB::table(self::TBL_DEPARTURES)
                ->selectRaw(self::DEP_IS_SUPPORT." as spt, SUM(".self::DEP_PRICE.") as s")
                ->whereBetween(self::DEP_DATE, [$start, $end])
                ->when($this->headquarterId, fn($q) => $q->where(self::DEP_HQ_ID, $this->headquarterId))
                ->groupBy(self::DEP_IS_SUPPORT)
                ->pluck('s','spt');

            $empresa  = (float) ($salidas[0] ?? 0);
            $apoyo    = (float) ($salidas[1] ?? 0);
            $salTotal = $empresa + $apoyo;

            // Otros y egresos mes (SIN filtro por mode)
            $otros = (float) DB::table(self::TBL_INCOMES)
                ->whereBetween(self::INC_DATE, [$start, $end])
                ->when($this->headquarterId, fn($q) => $q->where(self::INC_HQ_ID, $this->headquarterId))
                ->sum(self::INC_TOTAL);

            $egreso = (float) DB::table(self::TBL_EXPENSES)
                ->whereBetween(self::EXP_DATE, [$start, $end])
                ->when($this->headquarterId, fn($q) => $q->where(self::EXP_HQ_ID, $this->headquarterId))
                ->sum(self::EXP_TOTAL);

            $ingTotal = $pagoTotal + $salTotal + $otros;
            $utilidad = $ingTotal - $egreso;

            $row = [
                'mes'           => self::monthName($m),
                'pago'          => $cotizacion,
                'retraso'       => $retraso,
                'deuda'         => $deuda,
                'pago_total'    => $pagoTotal,
                'empresa'       => $empresa,
                'apoyo'         => $apoyo,
                'salidas_total' => $salTotal,
                'otros'         => $otros,
                'ingresos_total'=> $ingTotal,
                'egreso'        => $egreso,
                'utilidad'      => $utilidad,
            ];

            if (self::rowHasMovement($row)) {
                $this->anual[] = $row;
                $mesesMostrados++;

                foreach ($row as $k => $v) {
                    if (isset($sum[$k])) {
                        $sum[$k] += (float)$v;
                    }
                }
            }
        }

        $this->anualTotales = $sum;
        $this->anualMesesMostrados = $mesesMostrados;

        // Promedios anuales divididos entre el mes seleccionado (como legacy)
        $divisorAnual = max(1, (int) $this->month);
        $this->anualPromedios = array_map(fn($v) => $v / $divisorAnual, $this->anualTotales);
    }

    public function render()
    {
        $monthName = self::monthName((int) $this->month);
        $hqName = null;
        if ($this->headquarterId) {
            $hqName = DB::table(self::TBL_HQ)->where(self::HQ_ID, $this->headquarterId)->value(self::HQ_NAME);
        }

        return view('livewire.cash.rep-est-caja-ma', [
            'monthName' => $monthName,
            'hqName'    => $hqName,
        ]);
    }

    public function export()
    {
        $file = sprintf('Estadistica_Caja_%s_%d.xlsx', str_pad($this->month, 2, '0', STR_PAD_LEFT), $this->year);
        return Excel::download(new CajaEstadisticaExport($this->year, $this->month, $this->headquarterId), $file);
    }
}
