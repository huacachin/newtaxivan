{{-- resources/views/livewire/reports/departures-stats-monthly.blade.php --}}
@push('styles')
    <style>
        th, td { white-space: nowrap; vertical-align: middle; text-align: center; }
        thead th.sticky { position: sticky; top: 0; z-index: 2; }
        .table thead th { background:#0ea5e9; color:#fff; }
        .text-start { text-align: left; }
    </style>
@endpush

@php
    $monthName = $months[$month] ?? '';
@endphp

<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Reporte estadístico de salidas</h4>
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
                    <a href="#" class="f-s-14">Estadístico</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <!-- Filtros -->
        <div class="col-12">
            <div class="card">
                <div class="card-body pt-3 pb-3">
                    <div class="row g-3 align-items-end">
                        <div class="col-xl-3 col-md-4">
                            <label class="form-label">Mes</label>
                            <select class="form-select form-select-sm" wire:model.live="month">
                                @foreach($months as $mVal => $mName)
                                    <option value="{{ $mVal }}">{{ $mName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-4">
                            <label class="form-label">Año</label>
                            <select class="form-select form-select-sm" wire:model.live="year">
                                @foreach($years as $y)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-4">
                            <label class="form-label d-block invisible">.</label>
                            <a href="#" wire:click="export"
                               class="btn btn-primary w-100">
                                <i class="ti ti-file-analytics f-s-16"></i> Exportar
                            </a>
                        </div>
                        <div class="col-xl-3 col-md-4">
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

        <!-- Tabla -->
        <div class="col-xl-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0" style="color:#e11d48;">
                        REPORTE ESTADÍSTICO DE SALIDAS – {{ $monthName }} {{ $year }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped table-hover">
                            <thead class="text-center">
                            <tr>
                                <th class="sticky">CONTROLADOR</th>
                                <th class="sticky">PARADERO</th>
                                <th class="sticky">TIPO</th>
                                @for($d=1;$d<=$daysInMonth;$d++)
                                    @php $isSun = \Carbon\Carbon::create($year,$month,$d)->isSunday(); @endphp
                                    <th class="sticky {{ $isSun ? 'bg-danger text-white' : '' }}">{{ $d }}</th>
                                @endfor
                                <th class="sticky">SALIDAS</th>
                                <th class="sticky">S/</th>
                            </tr>
                            </thead>

                            <tbody>
                            @forelse($rows as $r)
                                <tr class="text-center">
                                    <td class="text-start">{{ $r['controller'] }}</td>
                                    <td class="text-start">{{ $r['stop'] }}</td>
                                    <td>{{ $r['type'] }}</td>

                                    @for($d=1;$d<=$daysInMonth;$d++)
                                        <td>
                                            @php $val = $r['days'][$d] ?? 0; @endphp
                                            {{ $r['type']==='S/' ? number_format($val,2) : number_format($val) }}
                                        </td>
                                    @endfor

                                    <td class="f-w-600">
                                        {{ $r['total_sal'] !== null ? number_format($r['total_sal']) : '' }}
                                    </td>
                                    <td class="f-w-600 text-danger">
                                        {{ $r['total_soles'] !== null ? number_format($r['total_soles'],2) : '' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 3 + $daysInMonth + 2 }}" class="text-center">
                                        No se encontraron resultados
                                    </td>
                                </tr>
                            @endforelse

                            {{-- Totales inferiores --}}
                            @if($daysInMonth>0)
                                <tr class="table-primary text-center f-w-600">
                                    <td colspan="3" class="text-start">TOTAL GENERAL — Salidas</td>
                                    @for($d=1;$d<=$daysInMonth;$d++)
                                        <td>{{ number_format($totalsSalidas[$d] ?? 0) }}</td>
                                    @endfor
                                    <td>{{ number_format($grandSalidas) }}</td>
                                    <td></td>
                                </tr>
                                <tr class="table-primary text-center f-w-600">
                                    <td colspan="3" class="text-start">TOTAL GENERAL — S/</td>
                                    @for($d=1;$d<=$daysInMonth;$d++)
                                        <td>{{ number_format($totalsMonto[$d] ?? 0, 2) }}</td>
                                    @endfor
                                    <td></td>
                                    <td class="text-danger">{{ number_format($grandMonto,2) }}</td>
                                </tr>
                            @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
