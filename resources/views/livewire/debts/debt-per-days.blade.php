

@push('styles')
    <style>
        .sun-head{ background:#ef4444 !important; color:#fff !important; }
        .sun-col{  background:#fee2e2 !important; }
        .day-val{ color:#ef4444 !important; }
        .total-val{ color:#000 !important; }
    </style>
@endpush

@php
    $monthName = \Illuminate\Support\Str::upper(
        \Carbon\Carbon::create($year, $month, 1)->translatedFormat('F')
    );

    $years  = range(now()->year-10, now()->year+1);
@endphp


<div class="container-fluid">

    {{-- Header --}}
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">REPORTE DE RETRASOS DEL MES DE {{$monthName}} DEL {{$year}}</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Caja</span></a>
                </li>
                <li class="d-flex active"><a href="#" class="f-s-14">Deuda por días</a></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">

        {{-- Tabla --}}
        <div class="col-xl-12">
            <div class="card">

                <div class="card-body">

                    <div class="row my-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-2 overflow-auto py-1">

                                <!-- Mes -->
                                <div class="flex-shrink-0" >
                                    <label class="form-label mb-1">Mes</label>
                                    <select class="form-select form-select-sm" wire:model="month">
                                        @foreach($months as $val => $label)
                                            <option value="{{ $val }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Año -->
                                <div class="flex-shrink-0" style="min-width: 120px;">
                                    <label class="form-label mb-1">Año</label>
                                    <select class="form-select form-select-sm" wire:model="year">
                                        @foreach($years as $y)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Condición -->
                                <div class="flex-shrink-0" style="min-width: 140px;">
                                    <label class="form-label mb-1">Condición</label>
                                    <select class="form-select form-select-sm" wire:model="condition">
                                        <option value="">Todas</option>
                                        <option value="DT">DT</option>
                                        <option value="GN">GN</option>
                                        <option value="EX">EX</option>
                                        <option value="EX5">EX5</option>
                                    </select>
                                </div>

                                                                <button class="btn btn-sm btn-dark flex-shrink-0 align-self-end" wire:click="$refresh">
                                    <i class="ti ti-search f-s-12"></i>
                                </button>

                                <!-- Exportar resumen -->
                                <button class="btn btn-sm btn-primary flex-shrink-0 align-self-end"
                                        wire:click="exportSummary">
                                    <i class="ti ti-file-analytics f-s-12"></i> Exportar
                                </button>

                                <!-- Exportar detalle -->
                                <button class="btn btn-sm btn-primary flex-shrink-0 align-self-end"
                                        wire:click="exportDetail">
                                    <i class="ti ti-file-description f-s-12"></i> E. detalle
                                </button>

                                <button class="btn btn-sm btn-primary" id="down" title="Bajar">
                                    <i class="fa-solid fa-angle-down"></i>
                                </button>

                            </div>
                        </div>
                    </div>
                    <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;max-width:100%">
                        <table class="table table-bordered table-hover" style="min-width:1800px;white-space:nowrap">
                            <thead class="bg-primary">
                            <tr>
                                <th>ITEM</th>
                                <th>PLACA</th>
                                <th>CONDICIÓN</th>

                                @foreach($days as $d)
                                    <th class="{{ $d['isSunday'] ? 'sun-head' : '' }}">{{ $d['n'] }}</th>
                                @endforeach

                                <th>PAGOS (D)</th>
                                <th>PAGOS (S/)</th>
                                <th>DEUDA (D)</th>
                                <th>DEUDA (S/)</th>
                            </tr>
                            </thead>

                            <tbody>
                            @foreach($rows as $r)
                                <tr>
                                    <td>{{ $r['item'] }}</td>
                                    <td class="sticky-col-2"><strong>{{ $r['plate'] }}</strong></td>
                                    <td>{{ $r['condition'] }}</td>

                                    @foreach($r['cells'] as $i => $c)
                                        <td class="{{ 'cell-' . ($c['class'] ?? '') }} {{ $days[$i]['isSunday'] ? 'sun-col' : '' }} {{ is_numeric(str_replace(',', '', $c['txt'] ?? '')) ? 'day-val' : '' }}">
                                            <a href="{{ route('departures.by-debt', ['plate' => $r['plate'], 'date' => $days[$i]['d']]) }}"
                                               target="_blank"
                                               style="text-decoration:none; color:inherit;">
                                                {{ $c['txt'] }}
                                            </a>
                                        </td>
                                    @endforeach

                                    <td class="total-val"><strong>{{ $r['paid_days'] }}</strong></td>
                                    <td class="total-val"><strong>{{ number_format($r['paid_amount'], 2) }}</strong></td>
                                    <td class="total-val"><strong>{{ $r['debt_days'] }}</strong></td>
                                    <td class="total-val"><strong>{{ number_format($r['debt_amount'], 2) }}</strong></td>
                                </tr>
                            @endforeach
                            </tbody>

                            <tfoot class="bg-primary" >
                            <tr>
                                <td></td>
                                <td></td>
                                <td colspan="1">TOTAL</td>

                                @foreach($days as $d)
                                    <td>{{ number_format($dayTotals[$d['d']]['paid_amount'] ?? 0, 2) }}</td>
                                @endforeach

                                <td>{{ $summary['paid_days'] ?? 0 }}</td>
                                <td>{{ number_format($summary['paid_amount'] ?? 0, 2) }}</td>
                                <td>{{ $summary['debt_days'] ?? 0 }}</td>
                                <td>{{ number_format($summary['debt_amount'] ?? 0, 2) }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="mt-2 small text-muted">
                        <div>En el pie por día se muestra la <b>suma de costos del día (S/)</b> de todos los vehículos que pagaron (P) ese día.</div>
                        <div>Domingos resaltados en rojo; “DEUDA (D)” cuenta celdas con #salidas y sin pago.</div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
