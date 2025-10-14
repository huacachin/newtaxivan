<div class="container-fluid">

    {{-- Filtro mes/año (opcional) --}}
    <div class="row mb-3">
        <div class="col-md-6">
            <label class="form-label">Mes</label>
            <select wire:model.live="month" class="form-select">
                @for($m=1;$m<=12;$m++)
                    <option value="{{ $m }}">{{ \Carbon\Carbon::create(null, $m, 1)->locale('es')->translatedFormat('F') }}</option>
                @endfor
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Año</label>
            <select wire:model.live="year" class="form-select">
                @for($y=now()->year-5;$y<=now()->year+1;$y++)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>
        </div>
    </div>

    {{-- KPIs fila 1 --}}
    <div class="row g-3">
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted">Ingresos del mes</div>
                    <div class="fs-4 fw-bold">{{ number_format($ingMes, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted">Egresos del mes</div>
                    <div class="fs-4 fw-bold">{{ number_format($egrMes, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted">Utilidad del mes</div>
                    <div class="fs-4 fw-bold">{{ number_format($utilMes, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted">Prom. saldo por día (c/ movimiento)</div>
                    <div class="fs-4 fw-bold">{{ number_format($promSaldoDia, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- KPIs fila 2 (hoy) --}}
    <div class="row g-3 mt-1">
        <div class="col-sm-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted">Ingresos hoy</div>
                    <div class="fs-4 fw-bold">{{ number_format($ingHoy, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted">Egresos hoy</div>
                    <div class="fs-4 fw-bold">{{ number_format($egrHoy, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted">Saldo hoy</div>
                    <div class="fs-4 fw-bold">{{ number_format($saldoHoy, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Mini tabla: Top 5 sedes por ingreso del mes --}}
    <div class="row g-3 mt-1">
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">Top 5 Sedes por ingreso (mes)</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>Sede</th>
                            <th class="text-end">Ingreso</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($topHQ as $row)
                            <tr>
                                <td>{{ $row['hq'] }}</td>
                                <td class="text-end">{{ number_format($row['sum'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-muted">Sin datos</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Mini tabla: Top tipos de pago (payments.type) --}}
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">Top tipos de pago (mes)</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>Tipo</th>
                            <th class="text-end">Monto</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($topTypes as $row)
                            <tr>
                                <td>{{ strtoupper($row['type']) }}</td>
                                <td class="text-end">{{ number_format($row['sum'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-muted">Sin datos</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Mini tabla: Saldos diarios (últimos 10 días del mes con movimiento) --}}
    <div class="row g-3 mt-1">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header">Saldos por día (mes)</div>
                <div class="card-body p-0">
                    @php
                        $daysWithMove = array_values(array_filter($days, fn($r) => ($r['income'] != 0 || $r['expense'] != 0)));
                        $last10 = array_slice($daysWithMove, -10);
                    @endphp
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>Fecha</th>
                            <th class="text-end">Ingresos</th>
                            <th class="text-end">Egresos</th>
                            <th class="text-end">Saldo del día</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($last10 as $r)
                            <tr>
                                <td>{{ $r['date'] }}</td>
                                <td class="text-end">{{ number_format($r['income'], 2) }}</td>
                                <td class="text-end">{{ number_format($r['expense'], 2) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($r['balance'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">Sin datos</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
