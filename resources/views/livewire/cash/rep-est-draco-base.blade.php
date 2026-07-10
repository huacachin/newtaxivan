
<div class="container-fluid">

    {{-- Header / migas --}}
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">REPORTE ESTADÍSTICO DRACO {{$year}}</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Caja</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Estadístico DRACO</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">

        {{-- Tabla principal --}}
        <div class="col-12">
            <div class="card">

                <div class="card-body lw-holder">
                    <div class="row my-2">

                        <div class="col-12 d-flex align-items-end justify-content-end">
                            <select class="form-select form-select-sm w-80 mg-e-10" wire:model="year">
                                @for($y = now()->year + 1; $y >= 2015; $y--)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endfor
                            </select>
                                                            <button class="btn btn-sm btn-dark flex-shrink-0 align-self-end mg-e-10" wire:click="$refresh">
                                    <i class="ti ti-search f-s-12"></i>
                                </button>

                                <button class="btn btn-sm btn-export mg-e-2" wire:click="export">
                                <i class="ti ti-file-analytics f-s-12"></i>
                            </button>
                            <a href="{{ route('departures.index') }}" class="btn btn-sm btn-primary ms-2">
                                <i class="ti ti-arrow-back-up f-s-12"></i>
                            </a>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="bg-primary">
                            <tr>
                                <th>CONTROLADOR</th>
                                <th>PARADERO</th>
                                @foreach($months as $mn)
                                    <th>{{ $mn }}</th>
                                @endforeach
                                <th>TOTAL</th>
                            </tr>
                            </thead>

                            @php
                                // Links de drill-down: cada monto abre /cash/expenses con los
                                // filtros que reproducen exactamente esa celda.
                                $monthStart = fn ($m) => sprintf('%04d-%02d-01', $year, $m);
                                $monthEnd   = fn ($m) => \Carbon\Carbon::create($year, $m, 1)->endOfMonth()->toDateString();
                                $expensesLink = function (string $reason, string $from, string $to, ?int $uid = null, ?int $hid = null) {
                                    return route('cash.expenses', array_filter([
                                        'expense_search' => $reason,
                                        'filterType'     => 1,
                                        'date_start'     => $from,
                                        'date_end'       => $to,
                                        'user_id'        => $uid,
                                        'headquarter_id' => $hid,
                                    ], fn ($v) => $v !== null));
                                };
                            @endphp

                            <tbody>
                            {{-- Oficina / Base (queda como una fila normal) --}}
                            <tr>
                                <td class="bg-primary text-white align-middle"><strong>OFICINA</strong></td>
                                <td class="bg-primary text-white align-middle"><strong>BASE</strong></td>
                                @php $tBase = 0; @endphp
                                @foreach($baseMonthly as $m => $val)
                                    @php $tBase += $val; @endphp
                                    <td>
                                        @if($val != 0)
                                            <a href="{{ $expensesLink('BASE', $monthStart($m), $monthEnd($m)) }}" target="_blank">{{ number_format($val, 2) }}</a>
                                        @else
                                            {{ number_format($val, 2) }}
                                        @endif
                                    </td>
                                @endforeach
                                <td>
                                    <strong>
                                        @if($tBase != 0)
                                            <a href="{{ $expensesLink('BASE', $year.'-01-01', $year.'-12-31') }}" target="_blank">{{ number_format($tBase, 2) }}</a>
                                        @else
                                            {{ number_format($tBase, 2) }}
                                        @endif
                                    </strong>
                                </td>
                            </tr>

                            @php
                                use Illuminate\Support\Collection;

                                // Aplanamos $groups en una sola colección de filas:
                                // cada fila tiene: user (controller), hq (sucursal/paradero), m (meses), total
                                $allRows = collect();

                                foreach ($groups as $uid => $g) {
                                    foreach ($g['hq_rows'] as $hid => $row) {
                                        $allRows->push([
                                            'uid'   => (int) $uid,
                                            'hid'   => (int) $hid,
                                            'user'  => $g['user'],
                                            'hq'    => $row['hq'],
                                            'm'     => $row['m'],      // array de montos por mes
                                            'total' => $row['total'],  // total anual de esa hq
                                        ]);
                                    }
                                }

                                // Rowspan por controller
                                $controllerRowspans = $allRows
                                    ->groupBy('user')
                                    ->map
                                    ->count()
                                    ->toArray();

                                // Rowspan por controller + hq (por si en el futuro hay más de una fila por hq)
                                $hqRowspans = $allRows
                                    ->groupBy(function ($r) {
                                        return $r['user'].'|'.$r['hq'];
                                    })
                                    ->map
                                    ->count()
                                    ->toArray();

                                // Flags para saber cuándo ya se pintó la celda combinada
                                $printedControllers = [];
                                $printedHqs = [];
                            @endphp

                            @if($allRows->isEmpty())
                                <tr>
                                    <td colspan="{{ 2 + count($months) + 1 }}">
                                        No hay registros DRACO para {{ $year }}.
                                    </td>
                                </tr>
                            @else
                                @foreach($allRows as $r)
                                    @php
                                        $controllerKey = $r['user'];
                                        $hqKey = $r['user'].'|'.$r['hq'];
                                    @endphp
                                    <tr>
                                        {{-- CONTROLLER (agrupado, mismo fondo que thead) --}}
                                        @if (!isset($printedControllers[$controllerKey]))
                                            @php $printedControllers[$controllerKey] = true; @endphp
                                            <td rowspan="{{ $controllerRowspans[$controllerKey] }}"
                                                class="bg-primary text-white align-middle">
                                                <strong>{{ strtoupper($r['user']) }}</strong>
                                            </td>
                                        @endif

                                        {{-- HQ / PARADERO (agrupado por controller+hq, mismo fondo que thead) --}}
                                        @if (!isset($printedHqs[$hqKey]))
                                            @php $printedHqs[$hqKey] = true; @endphp
                                            <td rowspan="{{ $hqRowspans[$hqKey] }}"
                                                class="bg-primary text-white align-middle">
                                                <strong>{{ $r['hq'] }}</strong>
                                            </td>
                                        @endif

                                        {{-- Meses --}}
                                        @foreach($r['m'] as $m => $val)
                                            <td>
                                                @if($val != 0)
                                                    <a href="{{ $expensesLink('DRACO', $monthStart($m), $monthEnd($m), $r['uid'], $r['hid'] ?: null) }}" target="_blank">{{ number_format($val, 2) }}</a>
                                                @else
                                                    {{ number_format($val, 2) }}
                                                @endif
                                            </td>
                                        @endforeach

                                        {{-- Total por HQ --}}
                                        <td>
                                            <strong>
                                                @if($r['total'] != 0)
                                                    <a href="{{ $expensesLink('DRACO', $year.'-01-01', $year.'-12-31', $r['uid'], $r['hid'] ?: null) }}" target="_blank">{{ number_format($r['total'], 2) }}</a>
                                                @else
                                                    {{ number_format($r['total'], 2) }}
                                                @endif
                                            </strong>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                            </tbody>


                            <tfoot class="bg-primary">
                            {{-- Si quieres fila de SOLO DRACO, descomenta:
                            <tr>
                              <th colspan="2">TOTAL DRACO</th>
                              @for($i=1;$i<=12;$i++)
                                <th>{{ number_format($totalsByMonth[$i] ?? 0, 2) }}</th>
                              @endfor
                              <th>{{ number_format($grandTotalDraco, 2) }}</th>
                            </tr>
                            --}}
                            <tr>
                                <th colspan="2">TOTAL GENERAL</th>
                                @for($i=1;$i<=12;$i++)
                                    <th>{{ number_format($totalsCombinedByMonth[$i] ?? 0, 2) }}</th>
                                @endfor
                                <th>{{ number_format($grandTotalCombined, 2) }}</th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="row table-responsive">

                        <div class="col-md-4 col-12">
                            <table class="table table-bordered" >
                                <thead class="bg-primary">
                                <tr><th>SUCURSAL</th><th class="text-end">TOTAL</th></tr>
                                </thead>
                                <tbody>
                                @php $sumHQ = 0; @endphp
                                @foreach($byHeadquarter as $h)
                                    @php $sumHQ += $h['total']; @endphp
                                    <tr>
                                        <td>{{ $h['hq'] }}</td>
                                        <td class="text-end">
                                            @if($h['total'] != 0)
                                                <a href="{{ $expensesLink('DRACO', $year.'-01-01', $year.'-12-31', null, ($h['hid'] ?? 0) ?: null) }}" target="_blank">{{ number_format($h['total'], 2) }}</a>
                                            @else
                                                {{ number_format($h['total'], 2) }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td>BASE</td>
                                    <td class="text-end">
                                        @if($grandTotalBase != 0)
                                            <a href="{{ $expensesLink('BASE', $year.'-01-01', $year.'-12-31') }}" target="_blank">{{ number_format($grandTotalBase, 2) }}</a>
                                        @else
                                            {{ number_format($grandTotalBase, 2) }}
                                        @endif
                                    </td>
                                </tr>
                                </tbody>
                                <tfoot class="table-primary">
                                <tr>
                                    <th>TOTAL</th>
                                    <th class="text-end">{{ number_format($sumHQ + $grandTotalBase, 2) }}</th>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>


    </div>
</div>
