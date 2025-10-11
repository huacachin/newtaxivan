

@push('styles')
    <style>

        /* ===== Estados de celda ===== */
        .cell-paid{  background:#dcfce7; color:#166534; font-weight:700; } /* Pagado (P) */
        .cell-freq{  background:#fef3c7; color:#92400e; font-weight:700; } /* #salidas */
        .cell-nopay{ background:#e5e7eb; color:#374151; }                  /* Sin pago/Exento */

        /* ===== Domingos en rojo (columna completa) ===== */
        .sun-head{ background:#ef4444 !important; color:#fff !important; }
        .sun-col{  background:#fee2e2 !important; } /* cuerpo de la columna domingo */

        /* Encabezado y pie oscuros */
        .tableFixHead thead th{
            position: sticky; top: 0; z-index: 3;
            background-color:#009BDC !important; color:#fff !important;
            vertical-align: middle; text-align:center;
        }
        .tableFixHead tfoot th,
        .tableFixHead tfoot td{
            position: sticky; bottom: 0; z-index: 2;
            background-color:#009BDC !important; color:#fff !important;
        }

        /* Columnas sticky (Item y Placa) — SIEMPRE BLANCAS en el cuerpo */
        .sticky-col{  position:sticky; left:0;    z-index:4; }
        .sticky-col-2{position:sticky; left:40px; z-index:4; }

        /* Fondo BLANCO en tbody para que no transparente la “zebra” */
        .tableFixHead tbody td.sticky-col,
        .tableFixHead tbody td.sticky-col-2{
            background-color:#fff !important;
            background-clip: padding-box;
            box-shadow: 1px 0 0 rgba(0,0,0,.06) inset;
        }

        /* Mantener fondo oscuro en thead para celdas sticky */
        .tableFixHead thead th.sticky-col,
        .tableFixHead thead th.sticky-col-2{
            background-color:#009BDC !important; color:#fff !important;
        }

        /* (Opcional) si usas sticky en el footer, que también permanezca oscuro */
        .tableFixHead tfoot td.sticky-col,
        .tableFixHead tfoot td.sticky-col-2{
            background-color:#009BDC !important; color:#fff !important;
            box-shadow: none;
        }
    </style>
@endpush


<div class="container-fluid">

    {{-- Header --}}
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Deuda por días</h4>
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
            <div class="card shadow-sm">
                <div class="card-header">
                    <div class="row g-2 align-items-end">
                        <div class=" col-md-4">
                            <label class="form-label">Mes</label>
                            <select class="form-select" wire:model.live="month">
                                @foreach($months as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class=" col-md-2">
                            <label class="form-label">Año</label>
                            <select class="form-select" wire:model.live="year">
                                @foreach($years as $y)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class=" col-md-2">
                            <label class="form-label">Condición</label>
                            <select class="form-select" wire:model.live="condition">
                                <option value="">Todas</option>
                                <option value="DT">DT</option>
                                <option value="GN">GN</option>
                                <option value="EX">EX</option>
                                <option value="EX5">EX5</option>
                            </select>
                        </div>

                        <div class=" col-md-2">
                            <button class="btn btn-primary w-100" wire:click="exportSummary">
                                <i class="ti ti-file-analytics f-s-16"></i> Exportar
                            </button>
                        </div>
                        <div class=" col-md-2">
                            <button class="btn btn-primary w-100" wire:click="exportDetail">
                                <i class="ti ti-file-description f-s-16"></i> E. detalle
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body table-responsive">
                    <div class="table-responsive x-scroll tableFixHead">
                        <table class="table table-sm table-bordered table-hover compact-table-xxs align-middle">
                            <thead class="text-center">
                            <tr>
                                <th class="sticky-col col-item">ITEM</th>
                                <th class="col-cod">COD</th>
                                <th class="sticky-col-2 col-plate">PLACA</th>
                                <th class="col-cond">CONDICIÓN</th>

                                @foreach($days as $d)
                                    <th class="day-col {{ $d['isSunday'] ? 'sun-head' : '' }}">{{ $d['n'] }}</th>
                                @endforeach

                                <th class="col-tot">PAGOS (D)</th>
                                <th class="col-tot">PAGOS (S/)</th>
                                <th class="col-tot">DEUDA (D)</th>
                                <th class="col-tot">DEUDA (S/)</th>
                            </tr>
                            </thead>

                            <tbody class="text-center">
                            @foreach($rows as $r)
                                <tr>
                                    <td class="sticky-col">{{ $r['item'] }}</td>
                                    <td>{{ $r['cod'] ?? '' }}</td>
                                    <td class="sticky-col-2"><strong>{{ $r['plate'] }}</strong></td>
                                    <td>{{ $r['condition'] }}</td>

                                    @foreach($r['cells'] as $i => $c)
                                        <td class="day-col {{ 'cell-' . ($c['class'] ?? '') }} {{ $days[$i]['isSunday'] ? 'sun-col' : '' }}">
                                            {{ $c['txt'] }}
                                        </td>
                                    @endforeach

                                    <td><strong>{{ $r['paid_days'] }}</strong></td>
                                    <td class="text-end"><strong>{{ number_format($r['paid_amount'], 2) }}</strong></td>
                                    <td><strong>{{ $r['debt_days'] }}</strong></td>
                                    <td class="text-end"><strong>{{ number_format($r['debt_amount'], 2) }}</strong></td>
                                </tr>
                            @endforeach
                            </tbody>

                            <tfoot class="text-center fw-semibold">
                            <tr>
                                <td class="sticky-col"></td>
                                <td></td>
                                <td class="sticky-col-2 text-end" colspan="2">TOTAL</td>

                                @foreach($days as $d)
                                    <td class="day-col">{{ number_format($dayTotals[$d['d']]['paid_amount'] ?? 0, 2) }}</td>
                                @endforeach

                                <td>{{ $summary['paid_days'] ?? 0 }}</td>
                                <td class="text-end">{{ number_format($summary['paid_amount'] ?? 0, 2) }}</td>
                                <td>{{ $summary['debt_days'] ?? 0 }}</td>
                                <td class="text-end">{{ number_format($summary['debt_amount'] ?? 0, 2) }}</td>
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
