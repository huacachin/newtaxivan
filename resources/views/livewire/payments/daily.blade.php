<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6"><h4 class="main-title">Pagos diarios</h4></div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-cash f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Pagos</span></a>
                </li>
                <li class="d-flex active"><a href="#" class="f-s-14">Reporte diario</a></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row g-2 align-items-end">
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
                        <div class="col-md-3">
                            <label class="form-label">Modo</label>
                            <select class="form-select" wire:model.live="mode">
                                <option value="Pago">Pago (PAGO + RETRASO)</option>
                                <option value="Caja">Caja (PAGO + RETRASO + DEUDA)</option>
                            </select>
                        </div>
                        <div class="col-md-3 text-end">
                            <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-primary">
                                <i class="ti ti-file-analytics"></i> Exportar
                            </a>
                            <a href="{{ route('payments.index') }}" class="btn btn-secondary">
                                <i class="ti ti-arrow-back-up"></i> Regresar
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabla --}}
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        @php
                            $days = range(1, $daysInMonth);
                        @endphp
                        <table class="table table-sm table-bordered table-striped table-hover">
                            <thead class="table-primary text-center">
                            <tr>
                                <th>Item</th>
                                <th>Placa</th>
                                <th>Cond.</th>
                                @foreach($days as $d)
                                    @php
                                        $date = \Carbon\Carbon::create($year, $month, $d);
                                        $isSun = $date->isSunday();
                                    @endphp
                                    <th @class(['bg-danger text-white'=>$isSun])>{{ $d }}</th>
                                @endforeach
                                <th>Total (S/)</th>
                                {{-- NUEVAS 4 COLUMNAS --}}
                                <th>Días Pag.</th>
                                <th>Total Deuda (Días)</th>
                                <th>Total Deuda (S/)</th>
                                <th>Deuda Real (S/)</th>
                            </tr>
                            </thead>

                            <tbody class="text-center">
                            @php $i=0; @endphp
                            @forelse($rows as $r)
                                @php $i++; @endphp
                                <tr>
                                    <td>{{ $i }}</td>
                                    <td class="text-start">{{ $r['plate'] }}</td>
                                    <td>{{ $r['cond'] ?: '-' }}</td>
                                    @foreach($days as $d)
                                        <td class="text-end">{{ number_format($r['days'][$d] ?? 0, 2) }}</td>
                                    @endforeach
                                    <td class="text-end fw-semibold">{{ number_format($r['total'], 2) }}</td>

                                    {{-- Nuevas columnas --}}
                                    <td class="text-end">{{ number_format($r['days_paid']) }}</td>
                                    <td class="text-end">{{ number_format($r['debt_days']) }}</td>
                                    <td class="text-end">{{ number_format($r['debt_amount'], 2) }}</td>
                                    <td class="text-end">{{ number_format($r['real_debt_amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 3 + $daysInMonth + 5 }}" class="text-center text-muted">
                                        Sin datos para el mes seleccionado.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="table-primary text-center fw-semibold">
                            <tr>
                                <td colspan="3" class="text-end">Totales por día (S/)</td>
                                @foreach($days as $d)
                                    <td class="text-end">{{ number_format($totalsPerDay[$d] ?? 0, 2) }}</td>
                                @endforeach>
                                <td class="text-end">{{ number_format($grandTotal, 2) }}</td>

                                {{-- Footer de las 4 columnas --}}
                                <td class="text-end">
                                    {{-- Días Pag. -> Total Pagos (conteo) --}}
                                    {{ number_format($sumDaysPaid) }}
                                </td>
                                <td class="text-end">{{ number_format($sumDebtDays) }}</td>
                                <td class="text-end">{{ number_format($sumDebtAmount, 2) }}</td>
                                <td class="text-end">{{ number_format($sumRealDebtAmount, 2) }}</td>
                            </tr>
                            <tr>
                                <td colspan="{{ 3 + $daysInMonth + 1 }}" class="text-end pe-3"></td>
                                <td class="text-end" colspan="4">
                                    {{-- Etiqueta para Días Pag. --}}
                                    <span class="me-2">Total Pagos (días):</span> <strong>{{ number_format($sumDaysPaid) }}</strong>
                                </td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                    <small class="text-muted">
                        * Días Pag. cuenta PAGO/RETRASO ({{ $mode === 'Pago' ? 'date_payment' : 'date_register' }}).<br>
                        * Deuda toma días del plan (cost_per_plate_days) que no tuvieron pago ese día.<br>
                        * Deuda Real: si condición inicia con “EX”, se considera 0; caso contrario, igual a la deuda.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>
