{{-- resources/views/livewire/reports/departures-stats-monthly.blade.php --}}
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
    </style>
@endpush

@php
    $monthName = $months[$month] ?? '';
    $days = range(1, $daysInMonth);
@endphp

<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title text-danger">REPORTE ESTADÍSTICO DE SALIDAS {{mb_strtoupper($monthName, 'UTF-8')}} DEL {{$year}}</h4>
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
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>CONTROLADOR</th>
                                <th>PARADERO</th>
                                <th>TIPO</th>
                                @foreach($days as $d)
                                    @php $isSun = \Carbon\Carbon::create($year,$month,$d)->isSunday(); @endphp
                                    <th class="{{ $isSun ? 'sunday' : '' }}">{{ $d }}</th>
                                @endforeach
                                <th>SALIDAS</th>
                                <th>S/</th>
                            </tr>
                            </thead>

                            <tbody>
                            @forelse($rows as $r)
                                <tr class="text-center">
                                    <td class="bg-primary text-white">{{ $r['controller'] }}</td>
                                    <td class="bg-primary text-white">{{ $r['stop'] }}</td>
                                    <td class="bg-primary text-white">{{ $r['type'] }}</td>

                                    @foreach($days as $d)
                                        @php $val = $r['days'][$d] ?? 0; @endphp
                                        <td>
                                            {{ $r['type']==='S/' ? number_format($val,2) : number_format($val) }}
                                        </td>
                                    @endforeach

                                    <td>
                                        {{ $r['total_sal'] !== null ? number_format($r['total_sal']) : '' }}
                                    </td>
                                    <td>
                                        {{ $r['total_soles'] !== null ? number_format($r['total_soles'],2) : '' }}
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

                            @if($daysInMonth>0)
                                <tfoot class="fw-semibold bg-primary">
                                <tr>
                                    <td></td>
                                    <td>TOTAL GENERAL — Salidas</td>
                                    <td></td>
                                    @foreach($days as $d)
                                        <td>{{ number_format($totalsSalidas[$d] ?? 0) }}</td>
                                    @endforeach
                                    <td>{{ number_format($grandSalidas) }}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td>TOTAL GENERAL — S/</td>
                                    <td></td>
                                    @foreach($days as $d)
                                        <td>{{ number_format($totalsMonto[$d] ?? 0, 2) }}</td>
                                    @endforeach
                                    <td></td>
                                    <td>{{ number_format($grandMonto,2) }}</td>
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

    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="month,year,export">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>
