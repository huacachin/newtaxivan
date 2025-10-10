{{-- resources/views/livewire/reports/departures-stats-monthly.blade.php --}}
@push('styles')
    <style>
        /* ===== Estilo matriz común ===== */
        .tableFixHead thead th{
            position: sticky; top: 0; z-index: 3;
            background:#009BDC !important; color:#fff !important;
            vertical-align: middle; text-align:center;
        }
        .tableFixHead tfoot th,
        .tableFixHead tfoot td{
            position: sticky; bottom: 0; z-index: 2;
            background:#009BDC !important; color:#fff !important;
            text-align:center;
        }
        .tableFixHead table.table th,
        .tableFixHead table.table td{ white-space: nowrap; }

        .num{ text-align:right; }
        .text-start{ text-align:left !important; }

        /* Domingos */
        .sunday{ background:#ef4444 !important; color:#fff !important; }

        /* Sticky cols para CONTROLADOR y PARADERO */
        :root{
            --w-col1: 180px; /* CONTROLADOR */
            --w-col2: 220px; /* PARADERO */
        }
        .sticky-col{ position:sticky; left:0; z-index:4; min-width:var(--w-col1); }
        .sticky-col-2{ position:sticky; left:var(--w-col1); z-index:4; min-width:var(--w-col2); }

        /* Fondo blanco en sticky del body para que no “traspase” rayado */
        tbody td.sticky-col,
        tbody td.sticky-col-2{
            background:#fff !important; background-clip:padding-box;
            box-shadow: 1px 0 0 rgba(0,0,0,.06) inset;
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
            <h4 class="main-title">Reporte estadístico de salidas</h4>
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
                <div class="card-header">
                    <h5 class="mb-0" style="color:#e11d48;">
                        REPORTE ESTADÍSTICO DE SALIDAS – {{ $monthName }} {{ $year }}
                    </h5>

                    <div class="row g-3 align-items-end mt-2">
                        <div class="col-xl-3 col-md-3">
                            <label class="form-label">Mes</label>
                            <select class="form-select form-select-sm" wire:model.live="month">
                                @foreach($months as $mVal => $mName)
                                    <option value="{{ $mVal }}">{{ $mName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-3">
                            <label class="form-label">Año</label>
                            <select class="form-select form-select-sm" wire:model.live="year">
                                @foreach($years as $y)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-3">
                            <label class="form-label d-block invisible">.</label>
                            <a href="#" wire:click="export" class="btn btn-primary w-100">
                                <i class="ti ti-file-analytics f-s-16"></i> Exportar
                            </a>
                        </div>
                        <div class="col-xl-3 col-md-3">
                            <label class="form-label d-block invisible">.</label>
                            <a href="{{ route('departures.index') }}" class="btn btn-primary w-100">
                                <i class="ti ti-rotate-2 f-s-16"></i> Regresar
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive tableFixHead">
                        <table class="table table-sm table-bordered table-striped table-hover align-middle">
                            <thead>
                            <tr>
                                <th class="sticky-col">CONTROLADOR</th>
                                <th class="sticky-col-2">PARADERO</th>
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
                                    <td class="sticky-col text-start">{{ $r['controller'] }}</td>
                                    <td class="sticky-col-2 text-start">{{ $r['stop'] }}</td>
                                    <td>{{ $r['type'] }}</td>

                                    @foreach($days as $d)
                                        @php $val = $r['days'][$d] ?? 0; @endphp
                                        <td class="num">
                                            {{ $r['type']==='S/' ? number_format($val,2) : number_format($val) }}
                                        </td>
                                    @endforeach

                                    <td class="num f-w-600">
                                        {{ $r['total_sal'] !== null ? number_format($r['total_sal']) : '' }}
                                    </td>
                                    <td class="num f-w-600">
                                        {{ $r['total_soles'] !== null ? number_format($r['total_soles'],2) : '' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 3 + $daysInMonth + 2 }}" class="py-4 text-muted text-center">
                                        No se encontraron resultados
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>

                            @if($daysInMonth>0)
                                <tfoot class="fw-semibold">
                                <tr>
                                    <td class="sticky-col"></td>
                                    <td class="sticky-col-2 text-start">TOTAL GENERAL — Salidas</td>
                                    <td></td>
                                    @foreach($days as $d)
                                        <td class="num">{{ number_format($totalsSalidas[$d] ?? 0) }}</td>
                                    @endforeach
                                    <td class="num">{{ number_format($grandSalidas) }}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td class="sticky-col"></td>
                                    <td class="sticky-col-2 text-start">TOTAL GENERAL — S/</td>
                                    <td></td>
                                    @foreach($days as $d)
                                        <td class="num">{{ number_format($totalsMonto[$d] ?? 0, 2) }}</td>
                                    @endforeach
                                    <td></td>
                                    <td class="num">{{ number_format($grandMonto,2) }}</td>
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
