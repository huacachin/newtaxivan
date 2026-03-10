
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
                            <select class="form-select form-select-sm w-80 mg-e-10" wire:model.live="year">
                                @for($y = now()->year + 1; $y >= 2015; $y--)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endfor
                            </select>
                            <button class="btn btn-sm btn-primary" wire:click="export">
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

                            <tbody>
                            {{-- Oficina / Base (queda como una fila normal) --}}
                            <tr>
                                <td class="bg-primary text-white align-middle"<strong>OFICINA</strong></td>
                                <td class="bg-primary text-white align-middle"><strong>BASE</strong></td>
                                @php $tBase = 0; @endphp
                                @foreach($baseMonthly as $val)
                                    @php $tBase += $val; @endphp
                                    <td>{{ number_format($val, 2) }}</td>
                                @endforeach
                                <td><strong>{{ number_format($tBase, 2) }}</strong></td>
                            </tr>

                            @php
                                use Illuminate\Support\Collection;

                                // Aplanamos $groups en una sola colección de filas:
                                // cada fila tiene: user (controller), hq (sucursal/paradero), m (meses), total
                                $allRows = collect();

                                foreach ($groups as $g) {
                                    foreach ($g['hq_rows'] as $row) {
                                        $allRows->push([
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
                                        @foreach($r['m'] as $val)
                                            <td>{{ number_format($val, 2) }}</td>
                                        @endforeach

                                        {{-- Total por HQ --}}
                                        <td><strong>{{ number_format($r['total'], 2) }}</strong></td>
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
                                        <td class="text-end">{{ number_format($h['total'], 2) }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td>BASE</td>
                                    <td class="text-end">{{ number_format($grandTotalBase, 2) }}</td>
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
    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="export,year">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>
