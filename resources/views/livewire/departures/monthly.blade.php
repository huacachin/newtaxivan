@push('styles')
    <style>
        th, td { white-space: nowrap; vertical-align: middle; text-align: center; }
        thead th.sticky { position: sticky; top: 0; z-index: 2; }
        .table thead th { background:#0ea5e9; color:#fff; }
        td.text-left, .text-start { text-align: left; }
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
    <!-- Header start -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Reporte mensual por placa – V.T</h4>
            <div class="text-muted f-s-12">{{ $monthName }} {{ $year }}</div>
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
    <!-- Header end -->

    <div class="row table-section">

        <!-- Filtros start -->
        <div class="col-12">
            <div class="card">
                <div class="card-body pt-3 pb-3">
                    <div class="row g-3 align-items-end">
                        <div class="col-xl-3 col-md-6">
                            <label class="form-label">Mes</label>
                            <select class="form-select form-select-sm" wire:model.live="month">
                                @foreach($months as $mVal => $mName)
                                    <option value="{{ $mVal }}">{{ $mName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <label class="form-label">Año</label>
                            <select class="form-select form-select-sm" wire:model.live="year">
                                @foreach($years as $y)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <label class="form-label d-block invisible">.</label>
                            <a href="#" wire:click="export"
                               class="btn btn-primary w-100">
                                <i class="ti ti-file-analytics f-s-16"></i> Exportar
                            </a>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <label class="form-label d-block invisible">.</label>
                            <a href="{{ route('departures.index') }}" class="btn btn-secondary w-100">
                                <i class="ti ti-rotate-2 f-s-16"></i> Regresar
                            </a>
                        </div>
                    </div>

                    <div class="mt-2" wire:loading.delay>
                        <span class="text-muted">
                            <span class="spinner-border spinner-border-sm"></span> Cargando…
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <!-- Filtros end -->

        <!-- Tabla mensual por placa -->
        <div class="col-xl-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0" style="color:#e11d48;">
                        REPORTE MENSUAL POR PLACA – V.T {{ $monthName }} {{ $year }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">

                        <table class="table table-sm table-bordered table-striped table-hover">
                            <thead class="text-center">
                            <tr>
                                <th class="sticky">Item</th>
                                <th class="sticky">Placa</th>
                                @foreach($days as $d)
                                    @php $isSun = \Carbon\Carbon::create($year, $month, $d)->isSunday(); @endphp
                                    <th class="sticky {{ $isSun ? 'bg-danger text-white' : '' }}">{{ $d }}</th>
                                @endforeach
                                <th class="sticky">T. Salida</th>
                            </tr>
                            </thead>

                            <tbody>
                            @php $i=0; @endphp
                            @forelse($rows as $row)
                                @php $i++; @endphp
                                <tr class="text-center">
                                    <td>{{ $i }}</td>
                                    <td class="text-start">{{ $row['plate'] }}</td>
                                    @foreach($days as $d)
                                        <td>{{ $row['daily'][$d] ?? 0 }}</td>
                                    @endforeach
                                    <td><strong>{{ $row['total'] }}</strong></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 2 + count($days) + 1 }}" class="text-center">
                                        No se encontraron resultados
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="table-primary text-center f-w-600">
                            <tr>
                                <th colspan="2" class="text-start">Total Salidas</th>
                                @foreach($days as $d)
                                    <th>{{ $totalPerDay[$d] ?? 0 }}</th>
                                @endforeach
                                <th>{{ array_sum($totalPerDay) }}</th>
                            </tr>
                            <tr>
                                <th colspan="2" class="text-start">Total V.T. (vehículos con salida)</th>
                                @foreach($days as $d)
                                    <th>{{ $vehiclesWorkedPerDay[$d] ?? 0 }}</th>
                                @endforeach
                                <th>{{ array_sum($vehiclesWorkedPerDay) }}</th>
                            </tr>
                            </tfoot>
                        </table>

                    </div>
                </div>
            </div>
        </div>
        <!-- /Tabla -->
    </div>
</div>
