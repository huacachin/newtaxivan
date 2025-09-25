@push('styles')
    <style>
        /* ===== Compacto base ===== */
        .compact-table-xxs{
            font-size:11px; line-height:1.05; table-layout:fixed;
        }
        .compact-table-xxs th, .compact-table-xxs td{
            padding:.18rem .25rem; white-space:nowrap; vertical-align:middle; text-align:center;
        }
        .compact-table-xxs thead th.sticky{ position:sticky; top:0; z-index:2; background:#e9f4ff; }

        /* ===== Scroll horizontal siempre disponible ===== */
        .x-scroll{ overflow-x:auto; overflow-y:visible; }

        /* ===== Anchos compactados para fijas ===== */
        .col-item{ width:40px; }
        .col-cod{  width:50px; }
        .col-plate{width:82px;}      /* ↓ antes 90 */
        .col-cond{ width:66px; }
        .col-tot{  width:72px;}      /* ↓ antes 100 */

        /* ===== Días: dales más espacio (variable por viewport) ===== */
        :root{ --day-w: 48px; }              /* default: cómodo en 14" */
        @media (max-width: 1366px){ :root{ --day-w: 44px; } }  /* pantallas más chicas */
        @media (min-width: 1600px){ :root{ --day-w: 52px; } }  /* monitores grandes */
        .day-col{ width:var(--day-w); min-width:var(--day-w); }

        /* ===== Estados de celda ===== */
        .cell-paid{  background:#ffe4e6; color:#065f46; font-weight:700; } /* P */
        .cell-freq{  background:#ffe4e6; color:#991b1b; font-weight:700; } /* #salidas */
        .cell-nopay{ background:#ffe4e6; color:#374151; }                  /* NT / exento */
        .sun{ background:#ffe4e6; }                                        /* domingo */
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
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Caja</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Deuda por días</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">

        {{-- Filtros / Controles --}}
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row g-2 align-items-end">
                        <div class="col-xl-4 col-md-4">
                            <label class="form-label">Mes</label>
                            <select class="form-select" wire:model.live="month">
                                @foreach($months as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-xl-4 col-md-4">
                            <label class="form-label">Año</label>
                            <select class="form-select" wire:model.live="year">
                                @foreach($years as $y)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-xl-4 col-md-4">
                            <label class="form-label">Condición</label>
                            <select class="form-select" wire:model.live="condition">
                                <option value="">Todas</option>
                                <option value="DT">DT</option>
                                <option value="GN">GN</option>
                                <option value="EX">EX</option>
                                <option value="EX5">EX5</option>
                            </select>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 mt-2">
            <div class="card">
                <div class="card-body">
                    <div class="row justify-content-end g-2">
                        <div class="col-sm-3 col-md-2">
                            <button class="btn btn-primary w-100" wire:click="exportSummary">
                                <i class="ti ti-file-analytics f-s-16"></i> Exportar
                            </button>
                        </div>
                        <div class="col-sm-3 col-md-2">
                            <button class="btn btn-primary w-100" wire:click="exportDetail">
                                <i class="ti ti-file-description f-s-16"></i> Exportar detalle
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabla --}}
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body table-responsive">

                    {{-- estilos puntuales de la grilla --}}
                    <style>
                        th, td { white-space: nowrap; text-align: center; vertical-align: middle; }
                        thead th.sticky { position: sticky; top: 0; background: var(--bs-primary-bg-subtle); z-index: 1; }
                        /* Celdas estado */
                        .cell-paid  { background: #ecfdf5; color:#065f46; font-weight:700; } /* Pagado (P) */
                        .cell-freq  { background: #fff7ed; color:#9a3412; font-weight:700; } /* #salidas */
                        .cell-nopay { background: #f3f4f6; color:#374151; }                  /* sin pago / exento */
                        .cell-sun   { background: #f1f5f9 !important; }                      /* cabecera domingo */
                    </style>

                    <div class="table-responsive x-scroll">
                        <table class="table table-sm table-bordered table-hover compact-table-xxs">
                            <thead>
                            <tr class="table-primary">
                                <th class="sticky col-item p-0">ITEM</th>
                                <th class="sticky col-cod  p-0">COD</th>
                                <th class="sticky col-plate p-0">PLACA</th>
                                <th class="sticky col-cond  p-0">CONDICIÓN</th>

                                @foreach($days as $d)
                                    <th class="sticky p-0 day-col {{ $d['isSunday'] ? 'sun' : '' }}">{{ $d['n'] }}</th>
                                @endforeach

                                <th class="sticky col-tot">PAGOS (D)</th>
                                <th class="sticky col-tot">PAGOS (S/)</th>
                                <th class="sticky col-tot">DEUDA (D)</th>
                                <th class="sticky col-tot">DEUDA (S/)</th>
                            </tr>
                            </thead>

                            <tbody class="text-center">
                            @foreach($rows as $r)
                                <tr>
                                    <td>{{ $r['item'] }}</td>
                                    <td>{{ $r['cod'] ?? '' }}</td>
                                    <td><strong>{{ $r['plate'] }}</strong></td>
                                    <td>{{ $r['condition'] }}</td>

                                    @foreach($r['cells'] as $c)
                                        <td class="day-col {{ 'cell-' . ($c['class'] ?? '') }}">{{ $c['txt'] }}</td>
                                    @endforeach

                                    <td><strong>{{ $r['paid_days'] }}</strong></td>
                                    <td class="text-end"><strong>{{ number_format($r['paid_amount'], 2) }}</strong></td>
                                    <td><strong>{{ $r['debt_days'] }}</strong></td>
                                    <td class="text-end"><strong>{{ number_format($r['debt_amount'], 2) }}</strong></td>
                                </tr>
                            @endforeach
                            </tbody>

                            <tfoot class="table-primary">
                            <tr>
                                <td colspan="4" class="text-end"><strong>TOTAL</strong></td>
                                @foreach($days as $d)
                                    <td class="day-col"><strong>{{ number_format($dayTotals[$d['d']]['paid_amount'] ?? 0, 2) }}</strong></td>
                                @endforeach
                                <td><strong>{{ $summary['paid_days'] ?? 0 }}</strong></td>
                                <td><strong>{{ number_format($summary['paid_amount'] ?? 0, 2) }}</strong></td>
                                <td><strong>{{ $summary['debt_days'] ?? 0 }}</strong></td>
                                <td><strong>{{ number_format($summary['debt_amount'] ?? 0, 2) }}</strong></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>


                    <div class="mt-2 small text-muted">
                        <div>En el pie por día se muestra la <b>suma de costos del día (S/)</b> de todos los vehículos que pagaron (P) ese día.</div>
                        <div>Domingos no suman; “DÍAS DEUDA” cuenta celdas con número (salidas) y sin pago.</div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
