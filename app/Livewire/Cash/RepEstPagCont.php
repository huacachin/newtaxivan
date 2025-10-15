<?php

namespace App\Livewire\Cash;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class RepEstPagCont extends Component
{
    /** =======================
     *  CONFIG – Ajusta si difiere tu esquema
     *  ======================= */
    private const USERS_TABLE        = 'users';         // tabla de usuarios
    private const USERS_ID           = 'id';
    private const USERS_NAME         = 'name';
    private const USERS_USERNAME     = 'username';      // <-- agregado

    private const HQ_TABLE           = 'headquarters';  // tabla de sedes/paraderos
    private const HQ_ID              = 'id';
    private const HQ_NAME            = 'name';

    private const DEPARTURES_TABLE   = 'departures';
    private const DEP_DATE           = 'date';
    private const DEP_USER_ID        = 'user_id';
    private const DEP_HQ_ID          = 'headquarter_id';
    private const DEP_PRICE          = 'price';

    private const EXPENSES_TABLE     = 'expenses';
    private const EXP_DATE           = 'date';
    private const EXP_USER_ID        = 'user_id';
    private const EXP_TOTAL          = 'total';
    private const EXP_REASON         = 'reason';        // <-- usamos reason (no in_charge)

    // Filtro principal
    public ?int $year = null;

    // Datos para la tabla por controlador
    public array $rows = [];
    public array $totalesSaldoMes = [];
    public float $totalSaldoFavor = 0.0;

    // Comparativo
    public array $comparativo = [];
    public array $comparativoTotales = [];

    public function mount(?int $year = null): void
    {
        $this->year = $year ?: (int) date('Y');
        $this->consultar();
    }

    public function updatedYear(): void
    {
        $this->consultar();
    }

    public function consultar(): void
    {
        $year = $this->year ?: (int) date('Y');

        /**
         * 1) Controladores: tomamos los que TENGAN departures en el año
         * (así evitamos depender de roles/flags legacy)
         * Además traemos username para cruzarlo con expenses.reason
         */
        $controladores = DB::table(self::DEPARTURES_TABLE.' as d')
            ->join(self::USERS_TABLE.' as u', 'u.'.self::USERS_ID, '=', 'd.'.self::DEP_USER_ID)
            ->whereYear('d.'.self::DEP_DATE, $year)
            ->select(
                'u.'.self::USERS_ID.' as id',
                'u.'.self::USERS_NAME.' as name',
                'u.'.self::USERS_USERNAME.' as username'
            )
            ->distinct()
            ->orderBy('name')
            ->get();

        $this->rows = [];
        $this->totalesSaldoMes = array_fill(1, 12, 0.0);
        $this->totalSaldoFavor = 0.0;

        foreach ($controladores as $ctrl) {
            // normalizamos el username para comparación case-insensitive y sin espacios
            $usernameLc = mb_strtolower(trim((string) $ctrl->username));

            /** Paraderos/sedes donde ese usuario tuvo departures en el año **/
            $paraderos = DB::table(self::DEPARTURES_TABLE.' as d')
                ->join(self::HQ_TABLE.' as h', 'h.'.self::HQ_ID, '=', 'd.'.self::DEP_HQ_ID)
                ->where('d.'.self::DEP_USER_ID, $ctrl->id)
                ->whereYear('d.'.self::DEP_DATE, $year)
                ->select('h.'.self::HQ_ID.' as id', 'h.'.self::HQ_NAME.' as name')
                ->distinct()
                ->orderBy('name')
                ->get();

            if ($paraderos->isEmpty()) {
                continue;
            }

            $ctrlBlock = [
                'controlador' => $ctrl->name,
                'paraderos'   => [],
                'egreso_pago' => array_fill(1, 12, 0.0),
                'egreso_draco'=> array_fill(1, 12, 0.0),
                'saldos'      => array_fill(1, 12, 0.0),
            ];

            /** Egresos por mes **/
            for ($m=1; $m<=12; $m++) {
                [$start, $end] = $this->monthRange($year, $m);

                // === Egreso Pago ===
                // Regla: expenses.reason == users.username
                $egresoPago = (float) DB::table(self::EXPENSES_TABLE)
                    ->whereBetween(self::EXP_DATE, [$start, $end])
                    ->whereRaw('LOWER(TRIM('.self::EXP_REASON.')) = ?', [$usernameLc])
                    ->sum(self::EXP_TOTAL);

                // === Egreso DRACO ===
                // Regla: expenses.reason = 'DRACO' y expenses.user_id = controller.id
                $egresoDraco = (float) DB::table(self::EXPENSES_TABLE)
                    ->where(self::EXP_USER_ID, $ctrl->id)
                    ->whereBetween(self::EXP_DATE, [$start, $end])
                    ->whereRaw('LOWER(TRIM('.self::EXP_REASON.')) = "draco"')
                    ->sum(self::EXP_TOTAL);

                $ctrlBlock['egreso_pago'][$m]  = $egresoPago;
                $ctrlBlock['egreso_draco'][$m] = $egresoDraco;
            }

            /** Ingresos por paradero y mes **/
            foreach ($paraderos as $par) {
                $ingMes = array_fill(1, 12, 0.0);

                for ($m=1; $m<=12; $m++) {
                    [$start, $end] = $this->monthRange($year, $m);

                    $ingMes[$m] = (float) DB::table(self::DEPARTURES_TABLE)
                        ->where(self::DEP_USER_ID, $ctrl->id)
                        ->where(self::DEP_HQ_ID, $par->id)
                        ->whereBetween(self::DEP_DATE, [$start, $end])
                        ->sum(self::DEP_PRICE);
                }

                $ctrlBlock['paraderos'][] = [
                    'sucursal'     => $par->name,
                    'ingresos_mes' => $ingMes,
                    'total'        => array_sum($ingMes),
                ];
            }

            /** Totales de ingresos por mes (sumando paraderos) **/
            $totIngMes = array_fill(1, 12, 0.0);
            foreach ($ctrlBlock['paraderos'] as $p) {
                for ($m=1; $m<=12; $m++) {
                    $totIngMes[$m] += $p['ingresos_mes'][$m];
                }
            }

            /** Saldos por mes **/
            for ($m=1; $m<=12; $m++) {
                $saldoMes = $totIngMes[$m]
                    - $ctrlBlock['egreso_pago'][$m]
                    - $ctrlBlock['egreso_draco'][$m];

                $ctrlBlock['saldos'][$m] = $saldoMes;
                $this->totalesSaldoMes[$m] += $saldoMes;
            }

            $this->rows[] = [
                'controlador'   => $ctrl->name,
                'paraderos'     => $ctrlBlock['paraderos'],
                'tot_ing_mes'   => $totIngMes,
                'egreso_pago'   => $ctrlBlock['egreso_pago'],
                'egreso_draco'  => $ctrlBlock['egreso_draco'],
                'saldos'        => $ctrlBlock['saldos'],
                'tot_ing_total' => array_sum($totIngMes),
                'tot_egr_pago'  => array_sum($ctrlBlock['egreso_pago']),
                'tot_egr_draco' => array_sum($ctrlBlock['egreso_draco']),
                'tot_saldo'     => array_sum($ctrlBlock['saldos']),
            ];
        }

        $this->totalSaldoFavor = array_sum($this->totalesSaldoMes);

        /** Comparativo (lo dejé tal cual tu versión) */
        $this->comparativo = $this->buildComparativo(
            $year,
            userA: 'Luis',
            userB: 'Elmer',
            hq1: 'Huaycan',
            hq2: 'La victoria'
        );
        $this->comparativoTotales = $this->buildComparativoTotales($this->comparativo);
    }

    private function monthRange(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1, 0, 0, 0)->toDateString();
        $end   = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
        return [$start, $end];
    }

    /** Suma price en departures por usuario (por nombre) y sede (por nombre) */
    private function sumDeparturesByUserAndHq(int $year, int $month, string $userName, string $hqName): float
    {
        [$start, $end] = $this->monthRange($year, $month);

        return (float) DB::table(self::DEPARTURES_TABLE.' as d')
            ->join(self::USERS_TABLE.' as u', 'u.'.self::USERS_ID, '=', 'd.'.self::DEP_USER_ID)
            ->join(self::HQ_TABLE.' as h', 'h.'.self::HQ_ID, '=', 'd.'.self::DEP_HQ_ID)
            ->where('u.'.self::USERS_NAME, $userName)
            ->where('h.'.self::HQ_NAME, $hqName)
            ->whereBetween('d.'.self::DEP_DATE, [$start, $end])
            ->sum('d.'.self::DEP_PRICE);
    }

    private function buildComparativo(int $year, string $userA, string $userB, string $hq1, string $hq2): array
    {
        $meses = [
            1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
            7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
        ];

        $rows = [];
        for ($m=1; $m<=12; $m++) {
            $a_h = $this->sumDeparturesByUserAndHq($year, $m, $userA, $hq1);
            $b_h = $this->sumDeparturesByUserAndHq($year, $m, $userB, $hq1);

            $a_v = $this->sumDeparturesByUserAndHq($year, $m, $userA, $hq2);
            $b_v = $this->sumDeparturesByUserAndHq($year, $m, $userB, $hq2);

            $rows[] = [
                'item'       => $m,
                'mes'        => $meses[$m],
                'a_h'        => $a_h,
                'b_h'        => $b_h,
                'dif_h'      => $a_h - $b_h,
                'a_v'        => $a_v,
                'b_v'        => $b_v,
                'dif_v'      => $a_v - $b_v,
                'dif_h_vs_v' => ($a_h + $a_v) - ($b_h + $b_v),
            ];
        }
        return $rows;
    }

    private function buildComparativoTotales(array $rows): array
    {
        $sum = [
            'a_h'=>0,'b_h'=>0,'dif_h'=>0,
            'a_v'=>0,'b_v'=>0,'dif_v'=>0,
            'dif_h_vs_v'=>0
        ];
        foreach ($rows as $r) {
            $sum['a_h'] += $r['a_h'];
            $sum['b_h'] += $r['b_h'];
            $sum['dif_h'] += $r['dif_h'];
            $sum['a_v'] += $r['a_v'];
            $sum['b_v'] += $r['b_v'];
            $sum['dif_v'] += $r['dif_v'];
            $sum['dif_h_vs_v'] += $r['dif_h_vs_v'];
        }
        return $sum;
    }

    public function render()
    {
        return view('livewire.cash.rep-est-pag-cont', [
            'year' => $this->year,
        ]);
    }
}
