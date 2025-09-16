<div class="container-fluid">

    <div class="row mb-3">
        <div class="col-12 d-flex align-items-center gap-2">
            <h4 class="mb-0">Reporte Diario de Caja / Deuda</h4>
            <button class="btn btn-sm btn-outline-primary" wire:click="prevMonth">« Mes anterior</button>
            <input type="date" class="form-control form-control-sm" style="max-width:160px"
                   wire:model.live="monthDate">
            <button class="btn btn-sm btn-outline-primary" wire:click="nextMonth">Mes siguiente »</button>

            <div class="ms-auto d-flex align-items-center gap-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="onlyActive" wire:model.live="onlyActive">
                    <label class="form-check-label" for="onlyActive">Sólo activos</label>
                </div>
                <select class="form-select form-select-sm" style="max-width:160px" wire:model.live="condition">
                    <option value="">Todas condiciones</option>
                    <option value="DT">DT</option>
                    <option value="GN">GN</option>
                    <option value="EX">EX</option>
                    <option value="EX5">EX5</option>
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <div wire:loading>
                <p>Cargando deudas...</p>
            </div>
            <style>
                .paid   { background: #ffe4e6; color:#065f46; font-weight:700; } /* P */
                .freq   { background: #ffe4e6; color:#991b1b; font-weight:700; } /* #salidas */
                .nopay  { background: #ffe4e6; color:#374151; }                  /* NT / exento */
                .sun    { background: #ffe4e6; }                                 /* cabecera domingo */
                th, td { white-space: nowrap; text-align: center; vertical-align: middle; }
                thead th.sticky { position: sticky; top: 0; background: #e0f2fe; z-index: 1; }
            </style>

            <table class="table table-sm table-bordered table-hover w-full">
                <thead>
                <tr class="table-primary">
                    <th class="sticky p-0">ITEM</th>
                    <th class="sticky p-0">COD</th>
                    <th class="sticky p-0">PLACA</th>
                    <th class="sticky p-0">CONDICIÓN</th>
                    @foreach($days as $d)
                        <th class="p-0 sticky {{ $d['isSunday'] ? 'sun' : '' }}">{{ $d['n'] }}</th>
                    @endforeach
                    <th class="sticky" colspan="2">TOTAL PAGOS</th>
                    <th class="sticky" colspan="2">TOTAL DEUDA</th>
                </tr>
                </thead>

                <tbody>

                @forelse($rows as $r)
                    <tr>
                        <td>{{ $r['item'] }}</td>
                        <td>{{ $r['cod'] ?? '' }}</td>
                        <td><strong>{{ $r['plate'] }}</strong></td>
                        <td>{{ $r['condition'] }}</td>

                        @foreach($r['cells'] as $c)
                            <td class="{{ $c['class'] }}">{{ $c['txt'] }}</td>
                        @endforeach

                        <td><strong>{{ $r['paid_days'] }}</strong></td>
                        <td><strong>{{ number_format($r['paid_amount'], 2) }}</strong></td>
                        <td><strong>{{ $r['debt_days'] }}</strong></td>
                        <td><strong>{{ number_format($r['debt_amount'], 2) }}</strong></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 4 + count($days) + 4 }}">Sin resultados para los filtros seleccionados.</td>
                    </tr>
                @endforelse
                </tbody>

                <tfoot class="table-primary">
                <tr>
                    <td colspan="4" class="text-end"><strong>TOTAL</strong></td>
                    @foreach($days as $d)
                        <td><strong>{{ number_format($dayTotals[$d['d']]['paid_amount'] ?? 0, 2) }}</strong></td>
                    @endforeach
                    <td><strong>{{ $summary['paid_days'] ?? 0 }}</strong></td>
                    <td><strong>{{ number_format($summary['paid_amount'] ?? 0, 2) }}</strong></td>
                    <td><strong>{{ $summary['debt_days'] ?? 0 }}</strong></td>
                    <td><strong>{{ number_format($summary['debt_amount'] ?? 0, 2) }}</strong></td>
                </tr>
                </tfoot>
            </table>

            <div class="mt-2 small text-muted">
                <div>En el pie por día se muestra la <b>suma de costos del día (S/)</b> de todos los vehículos que pagaron (P) ese día.</div>
                <div>Domingos no suman; “DÍAS DEUDA” cuenta celdas con número (salidas) y sin pago.</div>
            </div>
        </div>
    </div>
</div>
