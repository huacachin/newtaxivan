<div class="container-fluid">

    @push('styles')
    <style>
        :root {
            --dash-green: #0d9f6e;
            --dash-green-light: rgba(13, 159, 110, .08);
            --dash-red: #e02d3c;
            --dash-red-light: rgba(224, 45, 60, .08);
            --dash-amber: #d97706;
            --dash-amber-light: rgba(217, 119, 6, .08);
            --dash-blue: #2563eb;
            --dash-blue-light: rgba(37, 99, 235, .08);
            --dash-slate: #475569;
        }

        /* ── Metric cards ── */
        .metric-card {
            border: none;
            border-radius: 14px;
            transition: transform .15s ease, box-shadow .15s ease;
            overflow: hidden;
        }
        .metric-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.08); }

        .metric-card .metric-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .metric-card .metric-label { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; color: var(--dash-slate); margin-bottom: 4px; }
        .metric-card .metric-value { font-family: 'Poppins', sans-serif; font-size: 22px; font-weight: 700; line-height: 1.1; margin: 0; }

        .metric-green  .metric-icon { background: var(--dash-green-light); color: var(--dash-green); }
        .metric-green  .metric-value { color: var(--dash-green); }
        .metric-red    .metric-icon { background: var(--dash-red-light); color: var(--dash-red); }
        .metric-red    .metric-value { color: var(--dash-red); }
        .metric-amber  .metric-icon { background: var(--dash-amber-light); color: var(--dash-amber); }
        .metric-amber  .metric-value { color: var(--dash-amber); }
        .metric-blue   .metric-icon { background: var(--dash-blue-light); color: var(--dash-blue); }
        .metric-blue   .metric-value { color: var(--dash-blue); }
        .metric-neutral .metric-icon { background: #f1f5f9; color: var(--dash-slate); }
        .metric-neutral .metric-value { color: #1e293b; }

        /* ── Section titles ── */
        .dash-section-title {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .8px; color: #94a3b8; margin-bottom: 12px; padding-left: 2px;
        }

        /* ── Tables inside cards ── */
        .dash-table th {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .4px; color: #94a3b8; border-bottom: 2px solid #e2e8f0;
            padding: 10px 14px;
        }
        .dash-table td {
            font-size: 13px; padding: 10px 14px; border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .dash-table tbody tr:last-child td { border-bottom: none; }
        .dash-table tbody tr:hover { background: #f8fafc; }

        .dash-table .amount { font-weight: 600; font-variant-numeric: tabular-nums; }
        .dash-table .positive { color: var(--dash-green); }
        .dash-table .negative { color: var(--dash-red); }

        /* ── Rank badge ── */
        .rank-badge {
            width: 26px; height: 26px; border-radius: 8px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 700; color: #fff;
        }
        .rank-1 { background: #f59e0b; }
        .rank-2 { background: #94a3b8; }
        .rank-3 { background: #b45309; }
        .rank-default { background: #cbd5e1; color: #475569; }

        /* ── Today strip ── */
        .today-strip {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: 14px; color: #fff; padding: 20px 24px;
        }
        .today-strip .today-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .6px; opacity: .6; }
        .today-strip .today-value { font-family: 'Poppins', sans-serif; font-size: 24px; font-weight: 700; }
        .today-strip .today-sub { font-size: 13px; opacity: .7; }

        /* ── Chart card ── */
        .chart-card { border: none; border-radius: 14px; }

        /* ── Filter bar ── */
        .filter-bar {
            background: #fff; border-radius: 14px; border: 1px solid #e2e8f0;
            padding: 14px 20px;
        }
        .filter-bar select {
            border: 1px solid #e2e8f0; border-radius: 10px;
            font-size: 13px; font-weight: 600; padding: 6px 32px 6px 12px;
            background-color: #f8fafc; transition: border-color .15s;
        }
        .filter-bar select:focus { border-color: var(--dash-blue); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
    </style>
    @endpush

    {{-- ════════════════════════ FILTER BAR ════════════════════════ --}}
    <div class="filter-bar d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="d-flex align-items-center gap-2">
            <i class="ti ti-calendar f-s-18" style="color: var(--dash-blue);"></i>
            <select wire:model.live="month" class="form-select form-select-sm" style="width:160px;">
                @for($m=1;$m<=12;$m++)
                    <option value="{{ $m }}">{{ \Carbon\Carbon::create(null, $m, 1)->locale('es')->translatedFormat('F') }}</option>
                @endfor
            </select>
        </div>
        <div>
            <select wire:model.live="year" class="form-select form-select-sm" style="width:110px;">
                @for($y=now()->year-5;$y<=now()->year+1;$y++)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>
        </div>
        <div class="ms-auto text-muted" style="font-size:12px; font-weight:600;">
            <i class="ti ti-clock f-s-14"></i>
            {{ now()->locale('es')->translatedFormat('l, d \d\e F Y') }}
        </div>
    </div>

    {{-- ════════════════════════ TODAY STRIP ════════════════════════ --}}
    <div class="today-strip mb-4">
        <div class="row align-items-center">
            <div class="col-md-4 text-center mb-3 mb-md-0">
                <div class="today-label">Ingresos hoy</div>
                <div class="today-value" style="color: #34d399;">S/ {{ number_format($ingHoy, 2) }}</div>
            </div>
            <div class="col-md-4 text-center mb-3 mb-md-0">
                <div class="today-label">Egresos hoy</div>
                <div class="today-value" style="color: #fb923c;">S/ {{ number_format($egrHoy, 2) }}</div>
            </div>
            <div class="col-md-4 text-center">
                <div class="today-label">Saldo hoy</div>
                <div class="today-value">S/ {{ number_format($saldoHoy, 2) }}</div>
                <div class="today-sub">
                    @if($saldoHoy >= 0)
                        <i class="ti ti-trending-up"></i> Positivo
                    @else
                        <i class="ti ti-trending-down"></i> Negativo
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ════════════════════════ MONTHLY METRICS ════════════════════════ --}}
    <div class="dash-section-title">Resumen mensual</div>
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card metric-card metric-green">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="metric-icon"><i class="ti ti-arrow-down-left f-s-22"></i></div>
                    <div>
                        <div class="metric-label">Ingresos</div>
                        <div class="metric-value">{{ number_format($ingMes, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card metric-card metric-amber">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="metric-icon"><i class="ti ti-arrow-up-right f-s-22"></i></div>
                    <div>
                        <div class="metric-label">Egresos</div>
                        <div class="metric-value">{{ number_format($egrMes, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card metric-card {{ $utilMes >= 0 ? 'metric-blue' : 'metric-red' }}">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="metric-icon"><i class="ti ti-report-money f-s-22"></i></div>
                    <div>
                        <div class="metric-label">Utilidad</div>
                        <div class="metric-value">{{ number_format($utilMes, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card metric-card metric-neutral">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="metric-icon"><i class="ti ti-chart-bar f-s-22"></i></div>
                    <div>
                        <div class="metric-label">Prom. diario</div>
                        <div class="metric-value">{{ number_format($promSaldoDia, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ════════════════════════ CHART ════════════════════════ --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card chart-card">
                <div class="card-body">
                    <div class="dash-section-title mb-0">Ingresos vs Egresos - Diario</div>
                    <div id="dailyChart" style="min-height: 320px;" wire:ignore></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ════════════════════════ TABLES ROW ════════════════════════ --}}
    <div class="row g-3 mb-4">

        {{-- Top Sedes --}}
        <div class="col-md-4">
            <div class="card" style="border:none; border-radius:14px;">
                <div class="card-body">
                    <div class="dash-section-title">Top Sedes por ingreso</div>
                    <table class="table dash-table mb-0">
                        <thead>
                        <tr><th>#</th><th>Sede</th><th class="text-end">Ingreso</th></tr>
                        </thead>
                        <tbody>
                        @forelse($topHQ as $i => $row)
                            <tr>
                                <td>
                                    <span class="rank-badge {{ $i < 3 ? 'rank-'.($i+1) : 'rank-default' }}">{{ $i+1 }}</span>
                                </td>
                                <td class="fw-semibold">{{ $row['hq'] }}</td>
                                <td class="text-end amount positive">S/ {{ number_format($row['sum'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">Sin datos</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Top Tipos de Pago --}}
        <div class="col-md-4">
            <div class="card" style="border:none; border-radius:14px;">
                <div class="card-body">
                    <div class="dash-section-title">Tipos de pago</div>
                    <table class="table dash-table mb-0">
                        <thead>
                        <tr><th>#</th><th>Tipo</th><th class="text-end">Monto</th></tr>
                        </thead>
                        <tbody>
                        @forelse($topTypes as $i => $row)
                            <tr>
                                <td>
                                    <span class="rank-badge {{ $i < 3 ? 'rank-'.($i+1) : 'rank-default' }}">{{ $i+1 }}</span>
                                </td>
                                <td class="fw-semibold">{{ strtoupper($row['type']) }}</td>
                                <td class="text-end amount positive">S/ {{ number_format($row['sum'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">Sin datos</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Saldos por día --}}
        <div class="col-md-4">
            <div class="card" style="border:none; border-radius:14px;">
                <div class="card-body">
                    <div class="dash-section-title">Saldos recientes (c/ movimiento)</div>
                    @php
                        $daysWithMove = array_values(array_filter($days, fn($r) => ($r['income'] != 0 || $r['expense'] != 0)));
                        $last10 = array_slice($daysWithMove, -10);
                    @endphp
                    <table class="table dash-table mb-0">
                        <thead>
                        <tr><th>Fecha</th><th class="text-end">Ingreso</th><th class="text-end">Egreso</th><th class="text-end">Saldo</th></tr>
                        </thead>
                        <tbody>
                        @forelse($last10 as $r)
                            <tr>
                                <td class="fw-semibold">{{ \Carbon\Carbon::parse($r['date'])->format('d/m') }}</td>
                                <td class="text-end amount positive">{{ number_format($r['income'], 2) }}</td>
                                <td class="text-end amount negative">{{ number_format($r['expense'], 2) }}</td>
                                <td class="text-end amount fw-bold {{ $r['balance'] >= 0 ? 'positive' : 'negative' }}">
                                    {{ number_format($r['balance'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">Sin datos</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ════════════════════════ LOADING OVERLAY ════════════════════════ --}}

</div>

@script
<script>
    let chart = null;

    function renderChart(filtered) {
        const el = document.querySelector('#dailyChart');
        if (!el) return;
        if (!filtered || !filtered.length) {
            if (chart) { chart.destroy(); chart = null; }
            return;
        }

        const categories = filtered.map(d => {
            const parts = d.date.split('-');
            return parts[2] + '/' + parts[1];
        });

        const options = {
            chart: {
                type: 'bar',
                height: 320,
                fontFamily: 'Poppins, sans-serif',
                toolbar: { show: false },
                animations: { enabled: true, easing: 'easeinout', speed: 600 },
            },
            series: [
                { name: 'Ingresos', data: filtered.map(d => parseFloat(Number(d.income).toFixed(2))) },
                { name: 'Egresos', data: filtered.map(d => parseFloat(Number(d.expense).toFixed(2))) },
            ],
            colors: ['#0d9f6e', '#e02d3c'],
            plotOptions: {
                bar: { borderRadius: 4, columnWidth: '60%' }
            },
            dataLabels: { enabled: false },
            xaxis: {
                categories: categories,
                labels: { style: { fontSize: '11px', colors: '#94a3b8', fontWeight: 600 } },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: {
                labels: {
                    style: { fontSize: '11px', colors: '#94a3b8' },
                    formatter: v => v >= 1000 ? (v/1000).toFixed(1) + 'k' : v.toFixed(0)
                }
            },
            grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
            tooltip: {
                y: { formatter: v => 'S/ ' + v.toLocaleString('es-PE', {minimumFractionDigits:2}) }
            },
            legend: {
                position: 'top', horizontalAlign: 'right',
                fontSize: '12px', fontWeight: 600,
                markers: { radius: 4 }
            },
        };

        if (chart) chart.destroy();
        chart = new ApexCharts(el, options);
        chart.render();
    }

    // Render inicial
    renderChart($wire.$el.querySelector('#dailyChart') ? @json(array_values(array_filter($days, fn($r) => ($r['income'] != 0 || $r['expense'] != 0)))) : []);

    // Actualizaciones reactivas al cambiar mes/año
    $wire.on('chart-data', (event) => {
        renderChart(event.data);
    });
</script>
@endscript
