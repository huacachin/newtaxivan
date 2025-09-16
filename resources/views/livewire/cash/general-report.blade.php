<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-12 d-flex align-items-center gap-2">
            <h4 class="mb-0">Reporte General (semanal)</h4>

            <button class="btn btn-sm btn-outline-primary" wire:click="prevMonth">« Mes anterior</button>
            <input type="month" class="form-control form-control-sm" style="max-width: 160px" wire:model.live="month">
            <button class="btn btn-sm btn-outline-primary" wire:click="nextMonth">Mes siguiente »</button>

            <div class="ms-auto form-check">
                <input class="form-check-input" type="checkbox" id="onlyActive" wire:model.live="onlyActive">
                <label class="form-check-label" for="onlyActive">Sólo activos</label>
            </div>
        </div>
    </div>

    <style>
        .sticky-top-0 { position: sticky; top: 0; z-index: 1; }
        th, td { vertical-align: middle; }
        .table thead th { background: #e0f2fe; }
        .wk-header { background: #f1f5f9; }
    </style>

    <div class="card">
        <div class="card-body">

            <div wire:loading.delay>
                <div class="d-flex align-items-center gap-2 text-muted mb-3">
                    <div class="spinner-border spinner-border-sm" role="status"></div>
                    <span>Cargando…</span>
                </div>
            </div>

            @foreach($sections as $sec)
                <div class="wk-header p-2 rounded mb-2">
                    <strong>{{ $sec['label'] }}</strong>
                    <span class="text-muted ms-2">{{ $sec['start'] }} — {{ $sec['end'] }}</span>
                </div>

                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered table-striped">
                        <thead>
                        <tr>
                            <th class="sticky-top-0">#</th>
                            <th class="sticky-top-0">Fecha</th>
                            <th class="sticky-top-0">Usuario</th>
                            <th class="sticky-top-0">Origen</th>
                            <th class="sticky-top-0">Detalle</th>
                            <th class="sticky-top-0 text-end">Ingreso (S/)</th>
                            <th class="sticky-top-0 text-end">Egreso (S/)</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($sec['rows'] as $i => $r)
                            <tr>
                                <td>{{ $i+1 }}</td>
                                <td>{{ $r['date'] }}</td>
                                <td>{{ $r['user'] }}</td>
                                <td>{{ $r['source'] }}</td>
                                <td>{{ $r['detail'] }}</td>
                                <td class="text-end">{{ number_format($r['income'], 2) }}</td>
                                <td class="text-end">{{ number_format($r['expense'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">Sin movimientos en esta semana.</td>
                            </tr>
                        @endforelse
                        </tbody>
                        <tfoot class="table-primary">
                        <tr>
                            <td colspan="5" class="text-end"><strong>Resumen semana</strong></td>
                            <td class="text-end"><strong>{{ number_format($sec['summary']['income'], 2) }}</strong></td>
                            <td class="text-end"><strong>{{ number_format($sec['summary']['expense'], 2) }}</strong></td>
                        </tr>
                        <tr>
                            <td colspan="5" class="text-end"><strong>Utilidad semana</strong></td>
                            <td colspan="2" class="text-end">
                                <strong>{{ number_format($sec['summary']['profit'], 2) }}</strong>
                            </td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            @endforeach

            <div class="alert alert-info d-flex justify-content-between align-items-center">
                <div><strong>Total del mes</strong></div>
                <div class="ms-auto">
                    <span class="me-3">Ingresos: <strong>{{ number_format($grandIncome, 2) }}</strong></span>
                    <span class="me-3">Egresos: <strong>{{ number_format($grandExpense, 2) }}</strong></span>
                    <span>Utilidad: <strong>{{ number_format($grandProfit, 2) }}</strong></span>
                </div>
            </div>

        </div>
    </div>
</div>
