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
<div class="container-fluid">

    {{-- Encabezado --}}
    <div class="row align-items-center mb-3">
        <div class="col-sm-6">
            <h4 class="main-title mb-0 text-danger">REPORTE DIARIO DE PAGO {{ mb_strtoupper(\Carbon\Carbon::create($year,$month,1)->translatedFormat('F Y'), 'UTF-8') }} DEL {{$year}}</h4>
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
                        $baseCols  = 3 /* item+placa+cond */ + $daysInMonth + 1 /* Total (S/) */;
                        $extraCols = ($mode === 'Pago') ? 4 : 1; // Pago: 4 extra; Caja: 1 (Días Pag.)
                        $totalCols = $baseCols + $extraCols;
                    @endphp

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>Item</th>
                                <th>Placa</th>
                                <th>Cond.</th>

                                @foreach($days as $d)
                                    @php
                                        $date = \Carbon\Carbon::create($year, $month, $d);
                                        $isSun = $date->isSunday();
                                    @endphp
                                    <th class="{{ $isSun ? 'sunday' : '' }}">{{ $d }}</th>
                                @endforeach

                                <th>Total (S/)</th>

                                {{-- Columnas extra según modo --}}
                                <!--th>Días Pag.</th-->
                                @if($mode === 'Pago')
                                    <th>Deuda (días)</th>
                                    <th>Deuda (S/)</th>
                                    <th>Deuda Real (S/)</th>
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
                                    if (str_starts_with($cond, 'EX')) { $condClass .= 'cond-EX'; }
                                    elseif ($cond === 'GN') { $condClass .= 'cond-GN'; }
                                    elseif ($cond === 'DT') { $condClass .= 'cond-DT'; }
                                @endphp
                                <tr>
                                    <td>{{ $i }}</td>
                                    <td>{{ $r['plate'] }}</td>
                                    <td>
                                        <span class="{{ $condClass }}">{{ $cond ?: '-' }}</span>
                                    </td>

                                    @foreach($days as $d)
                                        <td class="{{(number_format($r['days'][$d] ?? 0, 2) == 0.00) ? 'bg-danger':'bg-success'}} text-end">{{ number_format($r['days'][$d] ?? 0, 2) }}</td>
                                    @endforeach

                                    <td>{{ number_format($r['total'], 2) }}</td>

                                    {{-- Extra según modo --}}
                                    <!--td>{{ number_format($r['days_paid']) }} </td-->

                                    @if($mode === 'Pago')
                                        <td>{{ number_format($r['debt_days']) }}</td>
                                        <td>{{ number_format($r['debt_amount'], 2) }}</td>
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

                            <tfoot class="bg-primary fw-semibold">
                            <tr>
                                <td colspan="3">Totales por día (S/)</td>

                                @foreach($days as $d)
                                    <td>{{ number_format($totalsPerDay[$d] ?? 0, 2) }}</td>
                                @endforeach

                                <td>{{ number_format($grandTotal, 2) }}</td>

                                {{-- Footer de columnas extra --}}
                                {{--<td>{{ number_format($sumDaysPaid) }}</td>--}}

                                @if($mode === 'Pago')
                                    <td>{{ number_format($sumDebtDays) }}</td>
                                    <td>{{ number_format($sumDebtAmount, 2) }}</td>
                                    <td>{{ number_format($sumRealDebtAmount, 2) }}</td>
                                @endif
                            </tr>

                            {{--<tr>
                                <td colspan="{{ 3 + $daysInMonth + 1 }}" class="text-end pe-3"></td>
                                @if($mode === 'Pago')
                                    <td colspan="4">
                                        <span class="me-2">Total Pagos (días):</span>
                                        <strong>{{ number_format($sumDaysPaid) }}</strong>
                                    </td>
                                @else
                                    <td>
                                        <span class="me-2">Total Pagos (días):</span>
                                        <strong>{{ number_format($sumDaysPaid) }}</strong>
                                    </td>
                                @endif
                            </tr>--}}
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
