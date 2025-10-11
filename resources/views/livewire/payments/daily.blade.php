@push('styles')
    <style>
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

    {{-- Encabezado --}}
    <div class="row align-items-center mb-3">
        <div class="col-sm-6">
            <h4 class="main-title mb-0">Pagos diarios</h4>
            <small class="text-muted">Mes de {{ \Carbon\Carbon::create($year,$month,1)->translatedFormat('F Y') }}</small>
        </div>
        <div class="col-sm-6 mt-2 mt-sm-0">
            <ul class="breadcrumb breadcrumb-start float-sm-end mb-0">
                <li class="d-flex">
                    <i class="ti ti-cash f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Pagos</span></a>
                </li>
                <li class="d-flex active"><a href="#" class="f-s-14">Reporte diario</a></li>
            </ul>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="row table-section">
        {{-- Resumen rápido --}}
        <div class="col-12">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted small">Total mes (S/)</div>
                            <div class="display-6 fw-semibold">{{ number_format($grandTotal, 2) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted small">Pagos (días)</div>
                            <div class="display-6 fw-semibold">{{ number_format($sumDaysPaid) }}</div>
                        </div>
                    </div>
                </div>

                @if($mode === 'Pago')
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="text-muted small">Deuda (S/)</div>
                                <div class="display-6 fw-semibold">{{ number_format($sumDebtAmount, 2) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="text-muted small">Deuda Real (S/)</div>
                                <div class="display-6 fw-semibold">{{ number_format($sumRealDebtAmount, 2) }}</div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Tabla --}}
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Año</label>
                            <select class="form-select" wire:model.live="year">
                                @foreach($years as $y) <option value="{{ $y }}">{{ $y }}</option> @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Mes</label>
                            <select class="form-select" wire:model.live="month">
                                @foreach($months as $mVal => $mName)
                                    <option value="{{ $mVal }}">{{ $mName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Modo</label>
                            <select class="form-select" wire:model.live="mode">
                                <option value="Pago">Pago</option>
                                <option value="Caja">Caja</option>
                            </select>
                        </div>
                        <div class="col-md-2 text-end">
                            <a href="#" wire:click="export" class="btn btn-primary w-100">
                                <i class="ti ti-file-analytics"></i> Exportar
                            </a>

                        </div>

                        <div class="col-md-2">
                            <a href="{{ route('payments.index') }}" class="btn btn-primary w-100">
                                <i class="ti ti-arrow-back-up"></i> Regresar
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">

                    {{-- Estilos específicos de la tabla --}}
                    <style>
                        /* Encabezado pegajoso + colores */
                        .tableFixHead thead th {
                            position: sticky; top: 0; z-index: 2;
                            background-color: #009BDC !important;
                            color: #fff !important;
                        }

                        /* Pie de tabla con color oscuro */
                        .tableFixHead tfoot th,
                        .tableFixHead tfoot td {
                            background-color: #009BDC !important;
                            color: #fff !important;
                        }

                        /* Mantén blancos los sticky del cuerpo si los usas */
                        .tableFixHead tbody td.sticky-col,
                        .tableFixHead tbody td.sticky-col-2,
                        .tableFixHead tbody td.sticky-col-3 {
                            background-color: #fff !important;
                            background-clip: padding-box;
                            box-shadow: 1px 0 0 rgba(0,0,0,.06) inset;
                        }

                        /* Que la tabla calcule el ancho según el contenido */
                        .tableFixHead table {
                            table-layout: auto !important;
                            width: auto;              /* el ancho crecerá según el contenido */
                        }

                        /* Evita quiebres de línea para que el ancho sea el del texto */
                        .tableFixHead th,
                        .tableFixHead td {
                            white-space: nowrap;
                        }

                        /* Domingos en rojo */
                        .sunday { background-color: #dc3545 !important; color: #fff !important; }
                    </style>

                    @php
                        $days = range(1, $daysInMonth);
                        $baseCols  = 3 /* item+placa+cond */ + $daysInMonth + 1 /* Total (S/) */;
                        $extraCols = ($mode === 'Pago') ? 4 : 1; // Pago: 4 extra; Caja: 1 (Días Pag.)
                        $totalCols = $baseCols + $extraCols;
                    @endphp

                    <div class="table-responsive tableFixHead">
                        <table class="table table-sm table-bordered table-hover align-middle text-nowrap">
                            <thead class="table-primary text-center">
                            <tr>
                                <th class="sticky-col">Item</th>
                                <th class="sticky-col-2">Placa</th>
                                <th class="sticky-col-3">Cond.</th>

                                @foreach($days as $d)
                                    @php
                                        $date = \Carbon\Carbon::create($year, $month, $d);
                                        $isSun = $date->isSunday();
                                    @endphp
                                    <th class="{{ $isSun ? 'sunday' : '' }}">{{ $d }}</th>
                                @endforeach

                                <th>Total (S/)</th>

                                {{-- Columnas extra según modo --}}
                                <th>Días Pag.</th>
                                @if($mode === 'Pago')
                                    <th>Deuda (días)</th>
                                    <th>Deuda (S/)</th>
                                    <th>Deuda Real (S/)</th>
                                @endif
                            </tr>
                            </thead>

                            <tbody class="text-center">
                            @php $i=0; @endphp
                            @forelse($rows as $r)
                                @php
                                    $i++;
                                    $cond = strtoupper($r['cond'] ?? '');
                                    $condClass = 'cond-badge ';
                                    if (str_starts_with($cond, 'EX')) { $condClass .= 'cond-EX'; }
                                    elseif ($cond === 'GN') { $condClass .= 'cond-GN'; }
                                    elseif ($cond === 'DT') { $condClass .= 'cond-DT'; }
                                @endphp
                                <tr>
                                    <td class="sticky-col text-center">{{ $i }}</td>
                                    <td class="sticky-col-2 text-start fw-semibold">{{ $r['plate'] }}</td>
                                    <td class="sticky-col-3">
                                        <span class="{{ $condClass }}">{{ $cond ?: '-' }}</span>
                                    </td>

                                    @foreach($days as $d)
                                        <td class="text-end">{{ number_format($r['days'][$d] ?? 0, 2) }}</td>
                                    @endforeach

                                    <td class="text-end fw-semibold">{{ number_format($r['total'], 2) }}</td>

                                    {{-- Extra según modo --}}
                                    <td class="text-end">{{ number_format($r['days_paid']) }}</td>

                                    @if($mode === 'Pago')
                                        <td class="text-end">{{ number_format($r['debt_days']) }}</td>
                                        <td class="text-end">{{ number_format($r['debt_amount'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['real_debt_amount'], 2) }}</td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $totalCols }}" class="text-center text-muted py-4">
                                        Sin datos para el mes seleccionado.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="table-primary text-center fw-semibold">
                            <tr>
                                <td class="sticky-col" colspan="3">Totales por día (S/)</td>

                                @foreach($days as $d)
                                    <td class="text-end">{{ number_format($totalsPerDay[$d] ?? 0, 2) }}</td>
                                @endforeach

                                <td class="text-end">{{ number_format($grandTotal, 2) }}</td>

                                {{-- Footer de columnas extra --}}
                                <td class="text-end">{{ number_format($sumDaysPaid) }}</td>

                                @if($mode === 'Pago')
                                    <td class="text-end">{{ number_format($sumDebtDays) }}</td>
                                    <td class="text-end">{{ number_format($sumDebtAmount, 2) }}</td>
                                    <td class="text-end">{{ number_format($sumRealDebtAmount, 2) }}</td>
                                @endif
                            </tr>

                            <tr>
                                <td colspan="{{ 3 + $daysInMonth + 1 }}" class="text-end pe-3"></td>
                                @if($mode === 'Pago')
                                    <td class="text-end" colspan="4">
                                        <span class="me-2">Total Pagos (días):</span>
                                        <strong>{{ number_format($sumDaysPaid) }}</strong>
                                    </td>
                                @else
                                    <td class="text-end">
                                        <span class="me-2">Total Pagos (días):</span>
                                        <strong>{{ number_format($sumDaysPaid) }}</strong>
                                    </td>
                                @endif
                            </tr>
                            </tfoot>
                        </table>
                    </div>

                    <small class="text-muted">
                        * Días Pag. cuenta PAGO/RETRASO ({{ $mode === 'Pago' ? 'date_payment' : 'date_register' }}) excluyendo domingos.<br>
                        * En modo <strong>Pago</strong> se muestran columnas de deuda; en <strong>Caja</strong> sólo se muestra “Días Pag.”.<br>
                        * Domingos resaltados en rojo no computan para deuda ni días pagados.
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="export,month,year,mode">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>
