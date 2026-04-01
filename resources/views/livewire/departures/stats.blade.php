{{-- resources/views/livewire/reports/departures-stats-monthly.blade.php --}}

@php
    $monthName = $months[$month] ?? '';
    $days = range(1, $daysInMonth);
@endphp

<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">REPORTE ESTADÍSTICO DE SALIDAS {{mb_strtoupper($monthName, 'UTF-8')}} DEL {{$year}}</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-door-exit f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Salidas</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Estadístico</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">

        <!-- Tabla -->
        <div class="col-xl-12">
            <div class="card shadow-sm">

                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-12">
                            <div class="d-flex flex-nowrap align-items-end gap-2 overflow-auto py-1">

                                <!-- Mes -->
                                <div class="flex-shrink-0" >
                                    <label class="form-label mb-1">Mes</label>
                                    <select class="form-select form-select-sm" wire:model="month">
                                        @foreach($months as $mVal => $mName)
                                            <option value="{{ $mVal }}">{{ $mName }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Año -->
                                <div class="flex-shrink-0" >
                                    <label class="form-label mb-1">Año</label>
                                    <select class="form-select form-select-sm" wire:model="year">
                                        @foreach($years as $y)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button class="btn btn-sm btn-dark flex-shrink-0 align-self-end" wire:click="$refresh">
                                    <i class="ti ti-search f-s-12"></i>
                                </button>
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
                                    <i class="fa-solid fa-angle-down"></i>
                                </button>



                            </div>
                        </div>
                    </div>
                    <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;max-width:100%">
                        <table class="table table-bordered table-striped table-hover" style="min-width:1800px;white-space:nowrap">
                            <thead class="bg-primary">
                            <tr>
                                <th>CONTROLADOR</th>
                                <th colspan="2">PARADERO</th>
                                @foreach($days as $d)
                                    @php $isSun = \Carbon\Carbon::create($year,$month,$d)->isSunday(); @endphp
                                    <th class="{{ $isSun ? 'bg-danger' : '' }}">{{ $d }}</th>
                                @endforeach
                                <th>SALIDAS</th>
                                <th>S/</th>
                            </tr>
                            </thead>

                            <tbody>
                            @php
                                $rowsCollection = collect($rows);

                                // 1) Rowspan por CONTROLLER (abarca todos sus paraderos y tipos)
                                $controllerRowspans = $rowsCollection
                                    ->groupBy(fn ($r) => $r['controller'])
                                    ->map->count()
                                    ->toArray();

                                // 2) Rowspan por CONTROLLER|STOP (como lo tenías antes)
                                $stopRowspans = $rowsCollection
                                    ->groupBy(fn ($r) => $r['controller'].'|'.$r['stop'])
                                    ->map->count()
                                    ->toArray();

                                // Para saber si ya pintamos las celdas combinadas de cada nivel
                                $printedControllers = [];
                                $printedStops       = [];
                            @endphp

                            @forelse($rows as $r)
                                @php
                                    $controllerKey = $r['controller'];
                                    $stopKey       = $r['controller'].'|'.$r['stop'];
                                @endphp
                                <tr class="text-center">
                                    {{-- CONTROLLER: solo una vez por controller --}}
                                    @if (!isset($printedControllers[$controllerKey]))
                                        @php $printedControllers[$controllerKey] = true; @endphp
                                        <td rowspan="{{ $controllerRowspans[$controllerKey] }}" class="bg-primary text-white align-middle">
                                            {{ $r['controller'] }}
                                        </td>
                                    @endif

                                    {{-- PARADERO: una vez por controller|stop --}}
                                    @if (!isset($printedStops[$stopKey]))
                                        @php $printedStops[$stopKey] = true; @endphp
                                        <td rowspan="{{ $stopRowspans[$stopKey] }}" class="bg-primary text-white align-middle">
                                            {{ $r['stop'] }}
                                        </td>
                                    @endif

                                    {{-- TIPO (se muestra en cada fila) --}}
                                    <td class="bg-primary text-white">
                                        {{ $r['type'] }}
                                    </td>

                                    {{-- Días --}}
                                    @foreach($days as $d)
                                        @php
                                            $val = $r['days'][$d] ?? 0;
                                            $dayDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
                                            $linkDay = route('departures.index', ['searchType' => 3, 'searchText' => $r['stop'], 'searchUser' => $r['controller'], 'fromDate' => $dayDate, 'toDate' => $dayDate]);
                                        @endphp
                                        @if($r['type'] === 'S/')
                                            <td class="title-modules f-w-600">
                                                @if($val != 0)
                                                    <a href="{{ $linkDay }}" target="_blank">{{ number_format($val, 2) }}</a>
                                                @endif
                                            </td>
                                        @else
                                            <td class="green_modules">
                                                @if($val != 0)
                                                    <a href="{{ $linkDay }}" target="_blank">{{ number_format($val) }}</a>
                                                @endif
                                            </td>
                                        @endif
                                    @endforeach

                                    {{-- Totales por fila --}}
                                    @php
                                        $mStart = sprintf('%04d-%02d-01', $year, $month);
                                        $mEnd = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
                                        $linkMonth = route('departures.index', ['searchType' => 3, 'searchText' => $r['stop'], 'searchUser' => $r['controller'], 'fromDate' => $mStart, 'toDate' => $mEnd]);
                                    @endphp
                                    <td>
                                        @if($r['total_sal'] !== null && $r['total_sal'] > 0)
                                            <a href="{{ $linkMonth }}" target="_blank">{{ number_format($r['total_sal']) }}</a>
                                        @endif
                                    </td>
                                    <td class="title-modules">
                                        @if($r['total_soles'] !== null && $r['total_soles'] > 0)
                                            <a href="{{ $linkMonth }}" target="_blank">{{ number_format($r['total_soles'], 2) }}</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 3 + $daysInMonth + 2 }}">
                                        No se encontraron resultados
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>

                            @if($daysInMonth > 0)
                                <tfoot class="fw-semibold table-striped">
                                <tr>
                                    {{-- Columna CONTROLADOR/PARADERO agrupada en 2 filas --}}
                                    <td colspan="2" rowspan="2">TOTAL GENERAL</td>

                                    {{-- Fila 1: Salidas --}}
                                    <td>Salidas</td>

                                    @foreach($days as $d)
                                        @php
                                            $vs = $totalsSalidas[$d] ?? 0;
                                            $dayDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
                                            $linkDayAll = route('departures.index', ['fromDate' => $dayDate, 'toDate' => $dayDate]);
                                        @endphp
                                        <td class="striped-cond">
                                            @if($vs != 0)
                                                <a href="{{ $linkDayAll }}" target="_blank">{{ number_format($vs) }}</a>
                                            @endif
                                        </td>
                                    @endforeach

                                    {{-- Total Salidas (rowspan 2) --}}
                                    @php
                                        $mStart = sprintf('%04d-%02d-01', $year, $month);
                                        $mEnd = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
                                        $linkMonthAll = route('departures.index', ['fromDate' => $mStart, 'toDate' => $mEnd]);
                                    @endphp
                                    <td rowspan="2">
                                        @if($grandSalidas > 0)
                                            <a href="{{ $linkMonthAll }}" target="_blank">{{ number_format($grandSalidas) }}</a>
                                        @else
                                            0
                                        @endif
                                    </td>

                                    {{-- Total S/ (rowspan 2) --}}
                                    <td rowspan="2">
                                        @if($grandMonto > 0)
                                            <a href="{{ $linkMonthAll }}" target="_blank">{{ number_format($grandMonto, 2) }}</a>
                                        @else
                                            0
                                        @endif
                                    </td>
                                </tr>

                                <tr>
                                    {{-- Fila 2: S/ (monto) --}}
                                    <td>S/</td>

                                    @foreach($days as $d)
                                        @php
                                            $vm = $totalsMonto[$d] ?? 0;
                                            $dayDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
                                            $linkDayAll = route('departures.index', ['fromDate' => $dayDate, 'toDate' => $dayDate]);
                                        @endphp
                                        <td>
                                            @if($vm != 0)
                                                <a href="{{ $linkDayAll }}" target="_blank">{{ number_format($vm, 2) }}</a>
                                            @endif
                                        </td>
                                    @endforeach
                                    {{-- OJO: aquí ya NO agregamos celdas al final porque las de arriba están con rowspan --}}
                                </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>

                    <small class="text-muted">
                        * Domingos se resaltan en rojo para referencia visual.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>
