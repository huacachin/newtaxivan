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
    /** @var array $months, $years */
    /** @var int $month, $year, $daysInMonth */
    $monthName = $months[$month] ?? '';
@endphp

<div class="container-fluid">
    <!-- Header start -->
    <div class="row align-items-center mb-3">
        <div class="col-sm-6">
            <h4 class="main-title mb-0">RMP V.T</h4>
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
                <div class="card-header">
                    <h5 class="mb-0">Reporte mensual por paradero (V.T.)</h5>
                    <div class="row mt-2">
                        <div class="col-12">
                            <div class="d-flex flex-nowrap align-items-end gap-2 overflow-auto py-1">

                                <!-- Mes -->
                                <div class="flex-shrink-0" style="min-width: 160px;">
                                    <label class="form-label mb-1">Mes</label>
                                    <select class="form-control form-control-sm" wire:model.live="month">
                                        @foreach($months as $mVal => $mName)
                                            <option value="{{ $mVal }}">{{ $mName }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Año -->
                                <div class="flex-shrink-0" style="min-width: 120px;">
                                    <label class="form-label mb-1">Año</label>
                                    <select class="form-control form-control-sm" wire:model.live="year">
                                        @foreach($years as $y)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Exportar -->
                                <a href="#"
                                   wire:click.prevent="export"
                                   class="btn btn-sm btn-primary flex-shrink-0 align-self-end">
                                    <i class="ti ti-file-analytics f-s-12"></i> Exportar
                                </a>

                                <!-- Regresar -->
                                <a href="{{ route('departures.index') }}"
                                   class="btn btn-sm btn-primary flex-shrink-0 align-self-end">
                                    <i class="ti ti-rotate-2 f-s-12"></i> Regresar
                                </a>

                            </div>
                        </div>
                    </div>

                </div>
                <div class="card-body">
                    <div class="table-responsive">

                        <table class="table table-bordered table-hover">
                            <thead class="text-center bg-primary">
                            <tr>
                                <th>CONTROLADOR</th>
                                <th>PARADERO</th>
                                <th>TIPO</th>
                                @for($d=1; $d<=$daysInMonth; $d++)
                                    @php $isSun = \Illuminate\Support\Carbon::create($year, $month, $d)->isSunday(); @endphp
                                    <th class="{{ $isSun ? 'sunday' : '' }}">{{ $d }}</th>
                                @endfor
                                <th>TOTAL</th>
                            </tr>
                            </thead>

                            <tbody>
                            @forelse($rows as $r)
                                <tr>
                                    <td>{{ $r['controller'] }}</td>
                                    <td>{{ $r['stop'] }}</td>
                                    <td>{{ $r['type'] === 'Emp' ? 'Emp.' : 'Apoyo.' }}</td>

                                    @for($d=1; $d<=$daysInMonth; $d++)
                                        <td>{{ $r['days'][$d] ?? 0 }}</td>
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

                            <tfoot class="text-center fw-semibold bg-primary">
                            <tr>
                                <td colspan="3">T.E</td>
                                @for($d=1; $d<=$daysInMonth; $d++)
                                    <td>{{ $totalsTE[$d] }}</td>
                                @endfor
                                <td>{{ $grandTE }}</td>
                            </tr>
                            <tr>
                                <td  colspan="3">T.A</td>
                                @for($d=1; $d<=$daysInMonth; $d++)
                                    <td class="p-2 day-col">{{ $totalsTA[$d] }}</td>
                                @endfor
                                <td>{{ $grandTA }}</td>
                            </tr>
                            <tr>
                                <td  colspan="3">V.T</td>
                                @for($d=1; $d<=$daysInMonth; $d++)
                                    <td class="p-2 day-col">{{ $totalsVT[$d] }}</td>
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
