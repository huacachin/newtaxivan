{{-- resources/views/livewire/reports/departures-monthly-by-stop.blade.php --}}
@push('styles')
    <style>
        /* ===== Encabezado/pie fijos (tema oscuro) ===== */
        .tableFixHead thead th{
            position: sticky; top: 0; z-index: 3;
            background-color: #009BDC !important;
            color: #fff !important;
        }
        .tableFixHead tfoot th,
        .tableFixHead tfoot td{
            position: sticky; bottom: 0; z-index: 2;
            background-color: #009BDC !important;
            color: #fff !important;
        }

        /* ===== Anchos mínimos (sin width fijo) ===== */
        :root{
            --min-controller: 7rem;  /* CONTROLADOR */
            --min-stop:       10rem; /* PARADERO */
            --min-day:        3.2rem;/* celdas de días */
            --min-type:       4.5rem;/* TIPO */
            --min-total:      5.5rem;/* TOTAL */
        }

        /* Sticky cols: CONTROLADOR y PARADERO */
        .tableFixHead .sticky-col{
            position: sticky; left: 0; z-index: 4;
            min-width: var(--min-controller);
            white-space: nowrap;
        }
        .tableFixHead .sticky-col-2{
            position: sticky; left: calc(var(--min-controller)); z-index: 4;
            min-width: var(--min-stop);
            white-space: nowrap;
        }

        /* Fondo blanco en sticky del cuerpo para que no “trasluzca” */
        .tableFixHead tbody td.sticky-col,
        .tableFixHead tbody td.sticky-col-2{
            background-color: #fff !important;
            background-clip: padding-box;
            box-shadow: 1px 0 0 rgba(0,0,0,.06) inset;
        }

        /* Celdas por tipo y totales */
        .type-col{ min-width: var(--min-type); }
        .total-col{ min-width: var(--min-total); font-weight: 600; }

        /* Días compactos */
        .day-col{ min-width: var(--min-day); }

        /* Domingos */
        .sunday{ background:#ef4444 !important; color:#fff !important; }

        /* Alineaciones generales */
        th, td{ vertical-align: middle; text-align: center; white-space: nowrap; }
        .text-start{ text-align: left !important; }
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
            <small class="text-muted">{{ $monthName }} {{ $year }}</small>
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

        <!-- Filtros start -->
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body pt-3 pb-3">
                    <div class="row g-3">
                        <div class="col-xl-3 col-md-4">
                            <label class="form-label">Mes</label>
                            <select class="form-select" wire:model.live="month">
                                @foreach($months as $mVal => $mName)
                                    <option value="{{ $mVal }}">{{ $mName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-4">
                            <label class="form-label">Año</label>
                            <select class="form-select" wire:model.live="year">
                                @foreach($years as $y)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-4">
                            <label class="form-label d-block invisible">.</label>
                            <a wire:click="export" class="btn btn-primary w-100">
                                <i class="ti ti-file-analytics f-s-16"></i> Exportar
                            </a>
                        </div>
                        <div class="col-xl-3 col-md-4">
                            <label class="form-label d-block invisible">.</label>
                            <a href="{{ route('departures.index') }}" class="btn btn-primary w-100">
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

        <!-- Tabla RMP V.T start -->
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Reporte mensual por paradero (V.T.)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive tableFixHead">

                        <table class="table table-sm table-bordered table-hover align-middle">
                            <thead class="text-center">
                            <tr>
                                <th class="sticky-col">CONTROLADOR</th>
                                <th class="sticky-col-2">PARADERO</th>
                                <th class="type-col">TIPO</th>
                                @for($d=1; $d<=$daysInMonth; $d++)
                                    @php $isSun = \Illuminate\Support\Carbon::create($year, $month, $d)->isSunday(); @endphp
                                    <th class="day-col {{ $isSun ? 'sunday' : '' }}">{{ $d }}</th>
                                @endfor
                                <th class="total-col">TOTAL</th>
                            </tr>
                            </thead>

                            <tbody>
                            @forelse($rows as $r)
                                <tr>
                                    <td class="sticky-col text-start">{{ $r['controller'] }}</td>
                                    <td class="sticky-col-2 text-start">{{ $r['stop'] }}</td>
                                    <td class="type-col">{{ $r['type'] === 'Emp' ? 'Emp.' : 'Apoyo.' }}</td>

                                    @for($d=1; $d<=$daysInMonth; $d++)
                                        <td class="day-col">{{ $r['days'][$d] ?? 0 }}</td>
                                    @endfor

                                    <td class="total-col">{{ $r['total'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 3 + $daysInMonth + 1 }}" class="text-center text-muted py-4">
                                        No se encontraron resultados
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="text-center fw-semibold">
                            <tr>
                                <td class="sticky-col text-end p-2" colspan="3">T.E</td>
                                @for($d=1; $d<=$daysInMonth; $d++)
                                    <td class="p-2 day-col">{{ $totalsTE[$d] }}</td>
                                @endfor
                                <td class="p-2 total-col">{{ $grandTE }}</td>
                            </tr>
                            <tr>
                                <td class="sticky-col text-end p-2" colspan="3">T.A</td>
                                @for($d=1; $d<=$daysInMonth; $d++)
                                    <td class="p-2 day-col">{{ $totalsTA[$d] }}</td>
                                @endfor
                                <td class="p-2 total-col">{{ $grandTA }}</td>
                            </tr>
                            <tr>
                                <td class="sticky-col text-end p-2" colspan="3">V.T</td>
                                @for($d=1; $d<=$daysInMonth; $d++)
                                    <td class="p-2 day-col">{{ $totalsVT[$d] }}</td>
                                @endfor
                                <td class="p-2 total-col">{{ $grandVT }}</td>
                            </tr>
                            </tfoot>
                        </table>

                    </div>
                </div>
            </div>
        </div>
        <!-- Tabla RMP V.T end -->

    </div>
</div>
