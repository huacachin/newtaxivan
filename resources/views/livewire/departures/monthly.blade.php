@push('styles')
    <style>
        .bg-modules{ background-color:red !important; }
    </style>
@endpush

@php
    $monthName = \Illuminate\Support\Str::upper(
        \Carbon\Carbon::create($year, $month, 1)->translatedFormat('F')
    );
    $months = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
    $years  = range(now()->year-10, now()->year+1);
@endphp

<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">REPORTE MENSUAL POR PLACA - V.T {{$monthName}} DEL {{$year}}</h4>
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
                    <a href="#" class="f-s-14">Mensual por placa</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">

        <!-- Tabla mensual por placa -->
        <div class="col-xl-12">
            <div class="card shadow-sm">

                <div class="card-body">

                    <div class="row mb-2">
                        <div class="col-12">
                            <div class="d-flex flex-nowrap align-items-end gap-2 overflow-auto py-1">

                                <!-- Mes -->
                                <div class="flex-shrink-0">
                                    <label class="form-label mb-1">Mes</label>
                                    <select class="form-select form-select-sm" wire:model="month">
                                        @foreach($months as $mVal => $mName)
                                            <option value="{{ $mVal }}">{{ $mName }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Año -->
                                <div class="flex-shrink-0">
                                    <label class="form-label mb-1">Año</label>
                                    <select class="form-select form-select-sm" wire:model="year">
                                        @foreach($years as $y)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Regresar -->
                                <button class="btn btn-sm btn-dark flex-shrink-0 align-self-end" wire:click="$refresh">
                                    <i class="ti ti-search f-s-12"></i>
                                </button>
                                <a href="{{ route('departures.index') }}"
                                   class="btn btn-sm btn-primary flex-shrink-0 align-self-end">
                                    <i class="ti ti-rotate-2 f-s-12"></i> Regresar
                                </a>



                                <!-- Exportar -->
                                <a href="#"
                                   wire:click.prevent="export"
                                   class="btn btn-sm btn-export flex-shrink-0 align-self-end">
                                    <i class="ti ti-file-analytics f-s-12"></i> Exportar
                                </a>

                                <button class="btn btn-sm btn-primary" id="down" title="Bajar">
                                    <i class="fa-solid fa-angle-down"></i>
                                </button>



                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>Nº</th>
                                <th>COD</th>
                                <th>Placa</th>
                                @foreach($days as $d)
                                    @php $isSun = \Carbon\Carbon::create($year, $month, $d)->isSunday(); @endphp
                                    <th class="{{ $isSun ? 'bg-danger' : '' }}">{{ $d }}</th>
                                @endforeach
                                <th>T. Salida</th>
                            </tr>
                            </thead>

                            <tbody>
                            @php $i=0; @endphp
                            @forelse($rows as $row)
                                @php $i++; @endphp
                                <tr class="text-center">
                                    <td>{{ $i }}</td>
                                    <td>{{ $row['sort_order'] }}</td>
                                    <td class="text-start">{{ $row['plate'] }}</td>
                                    @foreach($days as $d)
                                        @php
                                            $count = $row['daily'][$d] ?? 0;
                                            $dayDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
                                        @endphp
                                        <td class="green_modules">
                                            @if($count > 0)
                                                <a href="{{ route('departures.index', ['searchType' => 1, 'searchText' => $row['plate'], 'fromDate' => $dayDate, 'toDate' => $dayDate]) }}" target="_blank">{{ $count }}</a>
                                            @else
                                                0
                                            @endif
                                        </td>
                                    @endforeach
                                    @php
                                        $monthStart = sprintf('%04d-%02d-01', $year, $month);
                                        $monthEnd = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
                                    @endphp
                                    <td>
                                        <strong>
                                            @if($row['total'] > 0)
                                                <a href="{{ route('departures.index', ['searchType' => 1, 'searchText' => $row['plate'], 'fromDate' => $monthStart, 'toDate' => $monthEnd]) }}" target="_blank">{{ $row['total'] }}</a>
                                            @else
                                                0
                                            @endif
                                        </strong>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 2 + count($days) + 1 }}" class="text-center text-muted py-4">
                                        No se encontraron resultados
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="text-center f-w-600 bg-primary">
                            <tr>
                                <th colspan="3" class="text-center">Total Vueltas</th>
                                @foreach($days as $d)
                                    @php
                                        $tpd = $totalPerDay[$d] ?? 0;
                                        $dayDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
                                    @endphp
                                    <th class="bg-modules">
                                        @if($tpd > 0)
                                            <a href="{{ route('departures.index', ['fromDate' => $dayDate, 'toDate' => $dayDate]) }}" target="_blank" class="text-white">{{ $tpd }}</a>
                                        @else
                                            0
                                        @endif
                                    </th>
                                @endforeach
                                <th>{{ array_sum($totalPerDay) }}</th>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-center">Total V.T</th>
                                @foreach($days as $d)
                                    <th class="bg-modules">{{ $vehiclesWorkedPerDay[$d] ?? 0 }}</th>
                                @endforeach
                                <th>{{ array_sum($vehiclesWorkedPerDay) }}</th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>

                </div>
            </div>
        </div>

        {{-- ===== Tablas por Sede ===== --}}
        @foreach($hqTables as $hqId => $hq)
        <div class="col-xl-12 mt-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="fw-bold title-modules mb-2">{{ $hq['name'] }} - V.T {{$monthName}} DEL {{$year}}</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>Nº</th>
                                <th>Placa</th>
                                @foreach($days as $d)
                                    @php $isSun = \Carbon\Carbon::create($year, $month, $d)->isSunday(); @endphp
                                    <th class="{{ $isSun ? 'bg-danger' : '' }}">{{ $d }}</th>
                                @endforeach
                                <th>T. Salida</th>
                            </tr>
                            </thead>
                            <tbody>
                            @php $hi=0; @endphp
                            @foreach($hq['rows'] as $row)
                                @php $hi++; @endphp
                                <tr class="text-center">
                                    <td>{{ $hi }}</td>
                                    <td class="text-start">{{ $row['plate'] }}</td>
                                    @foreach($days as $d)
                                        @php
                                            $count = $row['daily'][$d] ?? 0;
                                            $dayDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
                                        @endphp
                                        <td class="green_modules">
                                            @if($count > 0)
                                                <a href="{{ route('departures.index', ['searchType' => 1, 'searchText' => $row['plate'], 'fromDate' => $dayDate, 'toDate' => $dayDate]) }}" target="_blank">{{ $count }}</a>
                                            @else
                                                0
                                            @endif
                                        </td>
                                    @endforeach
                                    @php
                                        $monthStart = sprintf('%04d-%02d-01', $year, $month);
                                        $monthEnd = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
                                    @endphp
                                    <td>
                                        <strong>
                                            @if($row['total'] > 0)
                                                <a href="{{ route('departures.index', ['searchType' => 1, 'searchText' => $row['plate'], 'fromDate' => $monthStart, 'toDate' => $monthEnd]) }}" target="_blank">{{ $row['total'] }}</a>
                                            @else
                                                0
                                            @endif
                                        </strong>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                            <tfoot class="text-center f-w-600 bg-primary">
                            <tr>
                                <th colspan="2" class="text-center">Total Vueltas</th>
                                @foreach($days as $d)
                                    @php
                                        $hqTpd = $hq['totalPerDay'][$d] ?? 0;
                                        $dayDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
                                    @endphp
                                    <th class="bg-modules">
                                        @if($hqTpd > 0)
                                            <a href="{{ route('departures.index', ['fromDate' => $dayDate, 'toDate' => $dayDate]) }}" target="_blank" class="text-white">{{ $hqTpd }}</a>
                                        @else
                                            0
                                        @endif
                                    </th>
                                @endforeach
                                <th>{{ array_sum($hq['totalPerDay']) }}</th>
                            </tr>
                            <tr>
                                <th colspan="2" class="text-center">Total V.T</th>
                                @foreach($days as $d)
                                    <th class="bg-modules">{{ $hq['vtPerDay'][$d] ?? 0 }}</th>
                                @endforeach
                                <th>{{ array_sum($hq['vtPerDay']) }}</th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endforeach

        {{-- ===== Resumen por Sede ===== --}}
        @if(count($hqSummary) > 0)
        <div class="col-xl-12 mt-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="fw-bold title-modules mb-2">RESUMEN POR SEDE - {{$monthName}} DEL {{$year}}</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>Sede</th>
                                <th class="text-center">Total Vueltas</th>
                                <th class="text-center">Total V.T</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($hqSummary as $s)
                                <tr>
                                    <td>{{ $s['name'] }}</td>
                                    <td class="text-center">{{ $s['totalVueltas'] }}</td>
                                    <td class="text-center">{{ $s['totalVT'] }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                            <tfoot class="text-center f-w-600 bg-primary">
                            <tr>
                                <th>TOTAL GENERAL</th>
                                <th>{{ $grandTotalVueltas }}</th>
                                <th>{{ $grandTotalVT }}</th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                    <small class="text-muted mt-2 d-block">
                        * El Total General V.T no es la suma de los V.T por sede. Un vehículo que opera en varias sedes el mismo día se cuenta una sola vez en el total general, pero aparece en cada sede donde trabajó.
                    </small>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>
