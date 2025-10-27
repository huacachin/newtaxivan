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

        /* Badges de condición (mismo esquema del Daily) */
        .cond-badge {
            display:inline-block; padding:.15rem .4rem; border-radius:.35rem;
            font-size:.75rem; font-weight:600; letter-spacing:.3px;
        }
        .cond-EX  { background:#e2e8f0; color:#334155; } /* EX/EX5 gris */
        .cond-GN  { background:#fef3c7; color:#92400e; } /* GN ámbar */
        .cond-DT  { background:#dcfce7; color:#166534; } /* DT verde */

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

<div class="container-fluid">
    <!-- Header -->
    <div class="row align-items-center mb-3">
        <div class="col-sm-6">
            <h4 class="main-title mb-0">Pagos</h4>
        </div>
        <div class="col-sm-6 mt-sm-0 mt-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end mb-0">
                <li class="d-flex">
                    <i class="ti ti-cash f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Pagos</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Mensual</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">

        <!-- Tabla -->
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header">
                    @php
                        $monthName = \Illuminate\Support\Str::upper(\Carbon\Carbon::create($year, $month, 1)->translatedFormat('F'));
                    @endphp
                    <h5 class="mb-3" style="color:#e11d48;">
                        REPORTE MENSUAL DE PAGO – {{ $monthName }} {{ $year }}
                    </h5>
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

                                <!-- Condición -->
                                <div class="flex-shrink-0" style="min-width: 140px;">
                                    <label class="form-label mb-1">Condición</label>
                                    <select class="form-control form-control-sm" wire:model.live="cond">
                                        <option value="">Todos</option>
                                        <option value="EX">EX</option>
                                        <option value="GN">GN</option>
                                        <option value="DT">DT</option>
                                        <option value="EX5">EX5</option>
                                    </select>
                                </div>

                                <!-- Exportar -->
                                <a href="#"
                                   wire:click.prevent="export"
                                   class="btn btn-sm btn-primary flex-shrink-0 align-self-end">
                                    <i class="ti ti-file-spreadsheet f-s-12"></i> Exportar
                                </a>

                                <!-- Regresar -->
                                <a href="{{ route('payments.index') }}"
                                   class="btn btn-sm btn-primary flex-shrink-0 align-self-end">
                                    <i class="ti ti-arrow-back-up f-s-12"></i> Regresar
                                </a>

                            </div>
                        </div>
                    </div>

                </div>
                <div class="card-body pb-2">

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>Item</th>
                                <th>Cod</th>
                                <th>Placa</th>
                                <th colspan="3">Deuda del mes anterior</th>
                                <th colspan="6">Pagos</th>
                            </tr>
                            <tr>
                                <th></th>
                                <th> </th>
                                <th></th>

                                <th>Deuda</th>
                                <th>Exonerado</th>
                                <th>P.Deuda</th>

                                <th>{{ str_pad($month,2,'0',STR_PAD_LEFT) }}/{{ $year }}</th>
                                <th>Lab.</th>
                                <th>DT</th>
                                <th>DNT</th>
                                <th>Condición</th>
                                <th>T.Deuda</th>
                            </tr>
                            </thead>

                            <tbody>
                            @forelse($rows as $vid => $r)
                                @php
                                    $cond = strtoupper($r['condition'] ?? '');
                                    $condClass = 'cond-badge ';
                                    if (str_starts_with($cond,'EX')) { $condClass .= 'cond-EX'; }
                                    elseif ($cond === 'GN') { $condClass .= 'cond-GN'; }
                                    elseif ($cond === 'DT') { $condClass .= 'cond-DT'; }
                                @endphp
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $r['order'] ?: '-' }}</td>
                                    <td>{{ $r['plate'] }}</td>

                                    {{-- Deuda anterior (debt_days) --}}
                                    <td>{{ number_format($r['prev_debt'], 2) }}</td>
                                    <td>{{ number_format($r['prev_exonerated'], 2) }}</td>
                                    <td>{{ number_format($r['prev_paid_debt'], 2) }}</td>

                                    {{-- Mes actual --}}
                                    <td>{{ number_format($r['month_amount'], 2) }}</td>
                                    <td>{{ $laborableDays }}</td>
                                    <td>{{ $r['dt_days'] }}</td>
                                    <td>{{ $r['dnt_days'] }}</td>
                                    <td><span class="{{ $condClass }}">{{ $cond ?: '-' }}</span></td>

                                    @php $tdebt = (float)$r['tdebt']; @endphp
                                    <td>
                                        <span @class([
                                            'text-danger' => $tdebt > 0 && !str_starts_with($cond,'EX'),
                                            'text-success' => $tdebt == 0
                                        ])>
                                            {{ number_format($tdebt, 2) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center">
                                        No hay datos para el mes seleccionado.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="bg-primary fw-semibold">
                            <tr>
                                <td></td>
                                <td><b>TOTAL</b></td>
                                <td></td>

                                <td>{{ number_format($sumPrevDebt, 2) }}</td>
                                <td>{{ number_format($sumPrevExonerated, 2) }}</td>
                                <td>{{ number_format($sumPrevPaidDebt, 2) }}</td>

                                <td>{{ number_format($sumMonthAmount, 2) }}</td>
                                <td>{{ $laborableDays }}</td>
                                <td>{{ $sumDtDays }}</td>
                                <td>{{ $sumDntDays }}</td>
                                <td></td>
                                <td>{{ number_format($sumTDebt, 2) }}</td>
                            </tr>
                            <tr>
                                <td></td>
                                <td><b>TOTAL</b></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td colspan="2">
                                    {{ number_format($sumMonthAmount + $sumPrevPaidDebt, 2) }}
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
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
         wire:target="export,month,year,cond">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>
