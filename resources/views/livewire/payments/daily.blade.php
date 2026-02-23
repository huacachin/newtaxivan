@push('styles')
    <style>
        .sunday { background: #ffcccc !important; }
    </style>
@endpush

<div class="container-fluid">

    {{-- Encabezado --}}
    <div class="row align-items-center mb-3">
        <div class="col-sm-6">
            <h4 class="main-title mb-0 title-modules">
                REPORTE DIARIO DE PAGO {{ mb_strtoupper(\Carbon\Carbon::create($year,$month,1)->translatedFormat('F Y'), 'UTF-8') }} DEL {{$year}}
            </h4>
        </div>
        <div class="col-sm-6 mt-2 mt-sm-0">
            <ul class="breadcrumb breadcrumb-start float-sm-end mb-0">
                <li class="d-flex">
                    <i class="ti ti-cash f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Pagos</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Reporte diario</a>
                </li>
            </ul>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="row table-section">
        {{-- Resumen rápido --}}
        <div class="col-12">
            <div class="row g-3">
                <div class="col-md-3 d-none d-lg-block">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted small">Total mes (S/)</div>
                            <div class="display-6 fw-semibold">{{ number_format($grandTotal, 2) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 d-none d-lg-block">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted small">Pagos (días)</div>
                            <div class="display-6 fw-semibold">{{ number_format($sumDaysPaid) }}</div>
                        </div>
                    </div>
                </div>

                @if($mode === 'Pago')
                    <div class="col-md-3 d-none d-lg-block">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="text-muted small">Deuda (S/)</div>
                                <div class="display-6 fw-semibold">{{ number_format($sumDebtAmount, 2) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 d-none d-lg-block">
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
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-12">
                            <div class="d-flex flex-nowrap align-items-end gap-2 overflow-auto py-1">

                                <!-- Año -->
                                <div class="flex-shrink-0" style="min-width: 120px;">
                                    <label class="form-label mb-1">Año</label>
                                    <select class="form-select form-select-sm" wire:model.live="year">
                                        @foreach($years as $y)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Mes -->
                                <div class="flex-shrink-0" style="min-width: 160px;">
                                    <label class="form-label mb-1">Mes</label>
                                    <select class="form-select form-select-sm" wire:model.live="month">
                                        @foreach($months as $mVal => $mName)
                                            <option value="{{ $mVal }}">{{ $mName }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Modo -->
                                <div class="flex-shrink-0" style="min-width: 140px;">
                                    <label class="form-label mb-1">Modo</label>
                                    <select class="form-select form-select-sm" wire:model.live="mode">
                                        <option value="Pago">Pago</option>
                                        <option value="Caja">Caja</option>
                                    </select>
                                </div>

                                <!-- Exportar -->
                                <a href="#"
                                   wire:click.prevent="export"
                                   class="btn btn-sm btn-primary flex-shrink-0 align-self-end">
                                    <i class="ti ti-file-analytics"></i> Exportar
                                </a>

                                <!-- Regresar -->
                                <a href="{{ route('payments.index') }}"
                                   class="btn btn-sm btn-primary flex-shrink-0 align-self-end">
                                    <i class="ti ti-arrow-back-up"></i> Regresar
                                </a>

                            </div>
                        </div>
                    </div>

                    @php
                        $days = range(1, $daysInMonth);
                        // Columnas:
                        // 3 (item, placa, cond) + días + 2 (Total Pagos: días + S/)
                        $baseCols  = 3 + $daysInMonth + 2;
                        // En modo Pago: +2 (Deuda) +2 (Deuda Real) => 4
                        $extraCols = ($mode === 'Pago') ? 4 : 0;
                        $totalCols = $baseCols + $extraCols;
                    @endphp

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="bg-primary text-white">
                            {{-- Fila 1 de encabezados (agrupadores) --}}
                            <tr>
                                <th rowspan="2">Item</th>
                                <th rowspan="2">Placa</th>
                                <th rowspan="2">Cond.</th>

                                @foreach($days as $d)
                                    @php
                                        $date = \Carbon\Carbon::create($year, $month, $d);
                                        $isSun = $date->isSunday();
                                    @endphp
                                    <th rowspan="2" class="{{ $isSun ? 'sunday' : '' }}">
                                        {{ $d }}
                                    </th>
                                @endforeach

                                <th colspan="2">TOTAL PAGOS</th>

                                @if($mode === 'Pago')
                                    <th colspan="2">TOTAL DEUDA</th>
                                    <th colspan="2">TOTAL D. REAL</th>
                                @endif
                            </tr>

                            {{-- Fila 2 de encabezados (subcolumnas) --}}
                            <tr>
                                <th>Días</th>
                                <th>S/</th>

                                @if($mode === 'Pago')
                                    <th>Días</th>
                                    <th>S/</th>
                                    <th>Días</th>
                                    <th>S/</th>
                                @endif
                            </tr>
                            </thead>

                            <tbody>
                            @php $i=0; @endphp
                            @forelse($rows as $r)
                                @php
                                    $i++;
                                    $cond = strtoupper($r['cond'] ?? '');
                                    $condClass = 'cond-badge ';
                                    if (str_starts_with($cond, 'EX')) {
                                        $condClass .= 'cond-EX';
                                    } elseif ($cond === 'GN') {
                                        $condClass .= 'cond-GN';
                                    } elseif ($cond === 'DT') {
                                        $condClass .= 'cond-DT';
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $i }}</td>
                                    <td>{{ $r['plate'] }}</td>
                                    <td>
                                        <span class="{{ $condClass }}">{{ $cond ?: '-' }}</span>
                                    </td>

                                    @foreach($days as $d)
                                        <td class="{{ (number_format($r['days'][$d] ?? 0, 2) == 0.00) ? 'bg-danger' : 'bg-success' }} text-end">
                                            {{ number_format($r['days'][$d] ?? 0, 2) }}
                                        </td>
                                    @endforeach

                                    {{-- TOTAL PAGOS --}}
                                    <td>{{ number_format($r['days_paid']) }}</td>
                                    <td>{{ number_format($r['total'], 2) }}</td>

                                    @if($mode === 'Pago')
                                        {{-- TOTAL DEUDA --}}
                                        <td>{{ number_format($r['debt_days']) }}</td>
                                        <td>{{ number_format($r['debt_amount'], 2) }}</td>

                                        {{-- TOTAL D. REAL --}}
                                        <td>{{ number_format($r['real_debt_days']) }}</td>
                                        <td>{{ number_format($r['real_debt_amount'], 2) }}</td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $totalCols }}">
                                        Sin datos para el mes seleccionado.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="bg-primary fw-semibold text-white">
                            <tr>
                                <td colspan="3">Totales por día (S/)</td>

                                @foreach($days as $d)
                                    <td>{{ number_format($totalsPerDay[$d] ?? 0, 2) }}</td>
                                @endforeach

                                {{-- TOTAL PAGOS --}}
                                <td>{{ number_format($sumDaysPaid) }}</td>
                                <td>{{ number_format($grandTotal, 2) }}</td>

                                @if($mode === 'Pago')
                                    {{-- TOTAL DEUDA --}}
                                    <td>{{ number_format($sumDebtDays) }}</td>
                                    <td>{{ number_format($sumDebtAmount, 2) }}</td>

                                    {{-- TOTAL D. REAL --}}
                                    <td>{{ number_format($sumRealDebtDays) }}</td>
                                    <td>{{ number_format($sumRealDebtAmount, 2) }}</td>
                                @endif
                            </tr>
                            </tfoot>
                        </table>
                    </div>

                    <small class="text-muted">
                        * Días Pag. cuenta PAGO/RETRASO ({{ $mode === 'Pago' ? 'date_payment' : 'date_register' }}) excluyendo domingos.<br>
                        * TOTAL PAGOS muestra días trabajados y monto del mes.<br>
                        * TOTAL DEUDA y TOTAL D. REAL muestran días y montos según las reglas de condición (EX, GN, DT, etc.).<br>
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
