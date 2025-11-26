{{-- resources/views/livewire/reports/departures-monthly-by-stop.blade.php --}}
@push('styles')
    <style>
        table {
            border-collapse: collapse; /* opcional */
            width: 100%;
        }

        th,td{
            padding: 1px !important;
            font-size: 10px !important;
            text-align: center !important;
            vertical-align: middle;   /* <-- clave */
            overflow-wrap: anywhere;
        }

        .btn, input,select {
            font-size: 10px !important;
        }

        .screen-overlay {
            position: fixed;
            inset: 0;                 /* full viewport */
            display: none;            /* Livewire lo pondrá en flex */
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,.35);
            backdrop-filter: blur(2px);
            z-index: 2000;            /* sobre modals/backdrops de Bootstrap */
            pointer-events: all;      /* bloquea clics */
        }

        .striped-cond {
            background: #d0cdcd !important;
        }

        .green_modules{
            color: #4D8A7C !important;
        }
    </style>
@endpush

@php
    /** @var array $months, $years */
    /** @var int $month, $year, $daysInMonth */
    $monthName = $months[$month] ?? '';

    // Rowspan para CONTROLADOR y PARADERO
    $controllerRowSpan = [];
    $stopRowSpan       = [];

    $rowCount = count($rows);
    $i = 0;

    while ($i < $rowCount) {
        $currentController = $rows[$i]['controller'] ?? null;

        if ($currentController === null) {
            $i++;
            continue;
        }

        // 1) Grupo por CONTROLADOR
        $ctrlStart = $i;
        $j = $i + 1;

        while (
            $j < $rowCount &&
            $rows[$j]['controller'] === $currentController
        ) {
            $j++;
        }

        $ctrlSpan = $j - $ctrlStart;
        $controllerRowSpan[$ctrlStart] = $ctrlSpan;
        for ($k = $ctrlStart + 1; $k < $j; $k++) {
            $controllerRowSpan[$k] = 0;
        }

        // 2) Dentro de ese controlador, agrupar por PARADERO
        $s = $ctrlStart;
        while ($s < $j) {
            $currentStop = $rows[$s]['stop'] ?? null;

            $t = $s + 1;
            while (
                $t < $j &&
                $rows[$t]['controller'] === $currentController &&
                $rows[$t]['stop'] === $currentStop
            ) {
                $t++;
            }

            $stopSpan = $t - $s;
            $stopRowSpan[$s] = $stopSpan;
            for ($k = $s + 1; $k < $t; $k++) {
                $stopRowSpan[$k] = 0;
            }

            $s = $t;
        }

        $i = $j;
    }
@endphp


<div class="container-fluid">
    <!-- Header start -->
    <div class="row align-items-center mb-3">
        <div class="col-sm-6">
            <h4 class="main-title title-modules ">
                REPORTE MENSUAL POR PARADERO V.T {{ mb_strtoupper($monthName, 'UTF-8') }} DEL {{ $year }}
            </h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end mb-0">
                <li class="d-flex">
                    <i class="ti ti-door-exit f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Salidas</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">RMP V.T</a>
                </li>
            </ul>
        </div>
    </div>
    <!-- Header end -->

    <div class="row table-section">

        <!-- Tabla RMP V.T start -->
        <div class="col-xl-12">
            <div class="card shadow-sm">

                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-12">
                            <div class="d-flex flex-nowrap align-items-end gap-2 overflow-auto py-1">

                                <!-- Mes -->
                                <div class="flex-shrink-0" style="min-width: 160px;">
                                    <label class="form-label mb-1">Mes</label>
                                    <select class="form-select form-select-sm" wire:model.live="month">
                                        @foreach($months as $mVal => $mName)
                                            <option value="{{ $mVal }}">{{ $mName }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Año -->
                                <div class="flex-shrink-0" style="min-width: 120px;">
                                    <label class="form-label mb-1">Año</label>
                                    <select class="form-select form-select-sm" wire:model.live="year">
                                        @foreach($years as $y)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <!-- Regresar -->
                                <a href="{{ route('departures.index') }}"
                                   class="btn btn-sm btn-primary flex-shrink-0 align-self-end">
                                    <i class="ti ti-rotate-2 f-s-12"></i> Regresar
                                </a>
                                <!-- Exportar -->
                                <a href="#"
                                   wire:click.prevent="export"
                                   class="btn btn-sm btn-primary flex-shrink-0 align-self-end">
                                    <i class="ti ti-file-analytics f-s-12"></i> Exportar
                                </a>

                                <button class="btn btn-sm btn-primary" id="down" title="Bajar">
                                    <i class="ti ti-square-chevrons-down f-s-12"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">

                        <table class="table table-bordered table-hover table-striped">
                            <thead class="text-center bg-primary">
                            <tr>
                                <th>CONTROLADOR</th>
                                <th>PARADERO</th>
                                <th>TIPO</th>
                                @for($d=1; $d<=$daysInMonth; $d++)
                                    @php $isSun = \Illuminate\Support\Carbon::create($year, $month, $d)->isSunday(); @endphp
                                    <th class="{{ $isSun ? 'bg-danger' : '' }}">{{ $d }}</th>
                                @endfor
                                <th>TOTAL</th>
                            </tr>
                            </thead>

                            <tbody>
                            @forelse($rows as $i => $r)
                                <tr>
                                    {{-- CONTROLADOR: una sola celda por grupo --}}
                                    @if(($controllerRowSpan[$i] ?? 0) > 0)
                                        <td class="bg-primary text-white"
                                            rowspan="{{ $controllerRowSpan[$i] }}">
                                            {{ $r['controller'] }}
                                        </td>
                                    @endif

                                    {{-- PARADERO: una sola celda por paradero dentro del controlador --}}
                                    @if(($stopRowSpan[$i] ?? 0) > 0)
                                        <td class="bg-primary text-white"
                                            rowspan="{{ $stopRowSpan[$i] }}">
                                            {{ $r['stop'] }}
                                        </td>
                                    @endif

                                    <td class="bg-primary text-white">
                                        {{ $r['type'] === 'Emp' ? 'Emp.' : 'Apoyo.' }}
                                    </td>

                                    @for($d = 1; $d <= $daysInMonth; $d++)
                                        <td class="green_modules">{{ $r['days'][$d] ?? 0 }}</td>
                                    @endfor

                                    <td>{{ $r['total'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 3 + $daysInMonth + 1 }}">
                                        No se encontraron resultados
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>


                            <tfoot class="text-center fw-semibold">
                            <tr>
                                <td rowspan="3" colspan="2">TOTAL GENERAL</td>
                                <td>T.E</td>
                                @for($d=1; $d<=$daysInMonth; $d++)
                                    <td>{{ $totalsTE[$d] }}</td>
                                @endfor
                                <td>{{ $grandTE }}</td>
                            </tr>
                            <tr class="striped-cond">
                                <td>T.A</td>
                                @for($d=1; $d<=$daysInMonth; $d++)
                                    <td class="day-col">{{ $totalsTA[$d] }}</td>
                                @endfor
                                <td>{{ $grandTA }}</td>
                            </tr>
                            <tr>
                                <td>V.T</td>
                                @for($d=1; $d<=$daysInMonth; $d++)
                                    <td class="day-col">{{ $totalsVT[$d] }}</td>
                                @endfor
                                <td>{{ $grandVT }}</td>
                            </tr>
                            </tfoot>
                        </table>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="month,year,export">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>
