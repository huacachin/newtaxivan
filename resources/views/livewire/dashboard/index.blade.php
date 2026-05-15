{{-- =========================================================
     PANEL DE CONTROL · Sala de mando TaxiVan
     ========================================================= --}}
<div class="tx-dashboard">

    @php
        $hour = (int) now()->format('H');
        $greeting = $hour < 12 ? 'Buenos días' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches');
        $userName = trim(explode(' ', auth()->user()->name ?? 'Operador')[0]);

        $fmt = fn($n, $d=2) => number_format((float)$n, $d, '.', ',');

        // Iniciales y color HSL determinístico por placa o nombre
        $iniciales = function (?string $s) {
            $s = trim((string) $s);
            if ($s === '' || $s === '—') return '··';
            $clean = preg_replace('/[^A-Z0-9]/', '', strtoupper($s));
            if (strlen($clean) >= 2) return substr($clean, 0, 3);
            return collect(explode(' ', $s))->filter()->take(2)
                ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
        };
        $avatarStyle = function (?string $s) {
            $h = abs(crc32(mb_strtolower(trim((string) $s)))) % 360;
            return "background: hsl({$h}, 55%, 92%); color: hsl({$h}, 60%, 28%); border-color: hsl({$h}, 50%, 80%);";
        };
    @endphp

    {{-- ════════ TIRA SUPERIOR ════════ --}}
    <div class="tx-topstrip">
        <div class="tx-topstrip__left">
            <span class="tx-eyebrow">{{ now()->translatedFormat('l, d \\d\\e F · Y') }}</span>
            <h1 class="tx-greeting">
                {{ $greeting }}, <em>{{ $userName }}</em>.
                <span class="tx-greeting__sub">Esto es lo que pasa hoy en Huacachin · TaxiVan.</span>
            </h1>
        </div>
        <div class="tx-topstrip__right">
            <span class="tx-pulse"></span>
            <span class="tx-eyebrow tx-eyebrow--mono">act. {{ now()->format('H:i') }}</span>
        </div>
    </div>

    {{-- ════════ ROW 1 · HERO + OPS DEL DÍA ════════ --}}
    <div class="row g-3 tx-stagger">

        {{-- HERO · Ingresos del mes --}}
        <div class="col-xl-8 col-lg-12 tx-anim" style="--i:1">
            <article class="tx-hero">
                <div class="tx-hero__grain"></div>
                <header class="tx-hero__head">
                    <span class="tx-eyebrow tx-eyebrow--light">Ingresos · {{ now()->translatedFormat('F · Y') }}</span>
                    <span class="tx-chip">
                        <span class="tx-chip__dot"></span>
                        {{ $vehiclesActive }} vehículos activos
                    </span>
                </header>

                <div class="tx-hero__num">
                    <span class="tx-currency">S/</span>
                    <span class="tx-bigint">{{ $fmt(floor($ingMes), 0) }}</span>
                    <span class="tx-decimal">.{{ str_pad((int)round(($ingMes - floor($ingMes))*100), 2, '0', STR_PAD_LEFT) }}</span>
                </div>

                <div class="tx-hero__foot">
                    <div class="tx-hero__sparkwrap" wire:ignore>
                        <div id="txSpark"></div>
                        <span class="tx-hero__sparklbl">Ingresos · últimos 7 días</span>
                    </div>
                    <div class="tx-hero__split">
                        <div>
                            <span class="tx-eyebrow tx-eyebrow--light">utilidad mes</span>
                            <span class="tx-hero__val {{ $utilMes < 0 ? 'tx-hero__val--warn' : '' }}">S/ {{ $fmt($utilMes) }}</span>
                        </div>
                        <div>
                            <span class="tx-eyebrow tx-eyebrow--light">deuda pendiente</span>
                            <span class="tx-hero__val tx-hero__val--warn">S/ {{ $fmt($debtsPending) }}</span>
                        </div>
                    </div>
                </div>
            </article>
        </div>

        {{-- OPS DEL DÍA --}}
        <div class="col-xl-4 col-lg-12 tx-anim" style="--i:2">
            <article class="tx-ops">
                <header class="tx-ops__head">
                    <span class="tx-eyebrow">Operación del día</span>
                    <span class="tx-eyebrow tx-eyebrow--mono">{{ now()->format('d.m.Y') }}</span>
                </header>

                <ul class="tx-ops__list">
                    <li class="tx-ops__row">
                        <span class="tx-ops__dot tx-ops__dot--cyan"></span>
                        <div class="tx-ops__txt">
                            <span class="tx-ops__lbl">Ingresos hoy</span>
                            <span class="tx-ops__sub">
                                @if($deltaIngresos >= 0)
                                    <i class="ti ti-arrow-up-right"></i> {{ $fmt(abs($deltaIngresos), 1) }}% vs ayer
                                @else
                                    <i class="ti ti-arrow-down-right"></i> {{ $fmt(abs($deltaIngresos), 1) }}% vs ayer
                                @endif
                            </span>
                        </div>
                        <span class="tx-ops__num">S/ {{ $fmt($ingHoy) }}</span>
                    </li>
                    <li class="tx-ops__row">
                        <span class="tx-ops__dot tx-ops__dot--green"></span>
                        <div class="tx-ops__txt">
                            <span class="tx-ops__lbl">Egresos hoy</span>
                            <span class="tx-ops__sub">– salida del día</span>
                        </div>
                        <span class="tx-ops__num">S/ {{ $fmt($egrHoy) }}</span>
                    </li>
                    <li class="tx-ops__row tx-ops__row--total">
                        <span class="tx-ops__dot tx-ops__dot--ink"></span>
                        <div class="tx-ops__txt">
                            <span class="tx-ops__lbl">Saldo neto</span>
                            <span class="tx-ops__sub">ingresos − egresos</span>
                        </div>
                        <span class="tx-ops__num tx-ops__num--big {{ $saldoHoy >= 0 ? '' : 'is-neg' }}">
                            S/ {{ $fmt($saldoHoy) }}
                        </span>
                    </li>
                </ul>
            </article>
        </div>
    </div>

    {{-- ════════ DIVISOR 01 ════════ --}}
    <div class="tx-section">
        <span class="tx-section__num">01</span>
        <span class="tx-section__lbl">Indicadores · resumen del mes</span>
        <span class="tx-section__rule"></span>
    </div>

    {{-- ════════ KPI GRID ════════ --}}
    <div class="row g-3 tx-stagger">
        <div class="col-xl-3 col-md-6 tx-anim" style="--i:3">
            <article class="tx-kpi">
                <div class="tx-kpi__top">
                    <span class="tx-eyebrow">Ingresos mes</span>
                    <i class="ti ti-arrow-down-left tx-kpi__icon"></i>
                </div>
                <div class="tx-kpi__num"><small>S/</small>{{ $fmt($ingMes, 0) }}</div>
                <div class="tx-kpi__foot"><span class="tx-mono">{{ now()->translatedFormat('F') }}</span></div>
            </article>
        </div>

        <div class="col-xl-3 col-md-6 tx-anim" style="--i:4">
            <article class="tx-kpi">
                <div class="tx-kpi__top">
                    <span class="tx-eyebrow">Egresos mes</span>
                    <i class="ti ti-arrow-up-right tx-kpi__icon"></i>
                </div>
                <div class="tx-kpi__num"><small>S/</small>{{ $fmt($egrMes, 0) }}</div>
                <div class="tx-kpi__foot"><span class="tx-mono">caja de operaciones</span></div>
            </article>
        </div>

        <div class="col-xl-3 col-md-6 tx-anim" style="--i:5">
            <article class="tx-kpi {{ $utilMes < 0 ? 'tx-kpi--warn' : '' }}">
                <div class="tx-kpi__top">
                    <span class="tx-eyebrow">Utilidad mes</span>
                    <i class="ti ti-report-money tx-kpi__icon"></i>
                </div>
                <div class="tx-kpi__num"><small>S/</small>{{ $fmt($utilMes, 0) }}</div>
                <div class="tx-kpi__foot"><span class="tx-mono">prom. diario S/ {{ $fmt($promSaldoDia, 0) }}</span></div>
            </article>
        </div>

        <div class="col-xl-3 col-md-6 tx-anim" style="--i:6">
            <article class="tx-kpi {{ $vehiclesWithDebtCount > 0 ? 'tx-kpi--warn' : '' }}">
                <div class="tx-kpi__top">
                    <span class="tx-eyebrow">Vehículos con deuda</span>
                    <i class="ti ti-alert-triangle tx-kpi__icon"></i>
                </div>
                <div class="tx-kpi__num">{{ $vehiclesWithDebtCount }}</div>
                <div class="tx-kpi__bar">
                    <span style="width: {{ $vehiclesActive > 0 ? min(100, ($vehiclesWithDebtCount / $vehiclesActive) * 100) : 0 }}%"></span>
                </div>
            </article>
        </div>
    </div>

    {{-- ════════ DIVISOR 02 ════════ --}}
    <div class="tx-section">
        <span class="tx-section__num">02</span>
        <span class="tx-section__lbl">Tendencia · ingresos vs egresos</span>
        <span class="tx-section__rule"></span>
    </div>

    {{-- ════════ ROW 3 · CHART + DONUT SEDES ════════ --}}
    <div class="row g-3 tx-stagger">
        <div class="col-xl-8 tx-anim" style="--i:7">
            <article class="tx-panel">
                <header class="tx-panel__head">
                    <div>
                        <span class="tx-eyebrow">Movimiento diario · {{ now()->translatedFormat('F') }}</span>
                        <h3 class="tx-panel__title">
                            S/ <em>{{ $fmt($ingMes) }}</em><small> ingresos · S/ {{ $fmt($egrMes) }} egresos</small>
                        </h3>
                    </div>
                    <div class="tx-legend">
                        <span><i class="tx-legend__sw" style="background:#15803D"></i> Ingresos</span>
                        <span><i class="tx-legend__sw" style="background:#B91C1C"></i> Egresos</span>
                    </div>
                </header>
                <div class="tx-panel__body" wire:ignore>
                    <div id="txChart30" style="height:300px;"></div>
                </div>
            </article>
        </div>

        <div class="col-xl-4 tx-anim" style="--i:8">
            <article class="tx-panel tx-panel--ink">
                <header class="tx-panel__head">
                    <div>
                        <span class="tx-eyebrow tx-eyebrow--light">Top sedes · ingreso del mes</span>
                        <h3 class="tx-panel__title tx-panel__title--light">
                            <em>{{ count($topHQ) }}</em><small> sedes con movimiento</small>
                        </h3>
                    </div>
                </header>
                <div class="tx-panel__body" wire:ignore>
                    <div id="txDonut" style="height:200px;"></div>
                </div>
                <ul class="tx-donut__legend">
                    @foreach($topHQ as $i => $row)
                        @php
                            $palette = ['#00B4D8','#F59E0B','#22C55E','#A855F7','#EC4899'];
                            $color = $palette[$i % count($palette)];
                        @endphp
                        <li>
                            <span class="tx-donut__sw" style="background: {{ $color }}"></span>
                            <span class="tx-donut__lbl">{{ $row['hq'] }}</span>
                            <span class="tx-donut__val">S/ {{ $fmt($row['sum'], 0) }}</span>
                        </li>
                    @endforeach
                    @if(empty($topHQ))
                        <li class="tx-donut__empty">Sin movimiento este mes</li>
                    @endif
                </ul>
            </article>
        </div>
    </div>

    {{-- ════════ DIVISOR 03 ════════ --}}
    <div class="tx-section">
        <span class="tx-section__num">03</span>
        <span class="tx-section__lbl">Atención · alertas y movimiento</span>
        <span class="tx-section__rule"></span>
    </div>

    {{-- ════════ ROW 4 · ALERTAS + ÚLTIMOS PAGOS ════════ --}}
    <div class="row g-3 tx-stagger">
        <div class="col-xl-5 tx-anim" style="--i:9">
            <article class="tx-panel tx-panel--warn">
                <header class="tx-panel__head">
                    <div>
                        <span class="tx-eyebrow tx-eyebrow--warn">Atención inmediata</span>
                        <h3 class="tx-panel__title">Vencimientos & deuda</h3>
                    </div>
                    <a href="{{ route('settings.vehicles.index') }}" class="tx-link">Vehículos
                        <i class="ti ti-arrow-narrow-right"></i></a>
                </header>
                <div class="tx-panel__body p-0">
                    @if(empty($expiringAlerts) && empty($debtsTop))
                        <div class="tx-empty">
                            <i class="ti ti-circle-check"></i>
                            <p>Sin vencimientos ni deuda activa. Flota al día.</p>
                        </div>
                    @else
                        <ul class="tx-alerts">
                            @foreach($expiringAlerts as $a)
                                <li class="tx-alerts__row">
                                    <span class="tx-alerts__pill tx-alerts__pill--{{ $a['color'] }}">{{ $a['abbr'] }}</span>
                                    <div class="tx-alerts__main">
                                        <a href="{{ route('settings.vehicles.expiration', ['id' => $a['id'], 'field' => $a['field']]) }}"
                                           class="tx-alerts__name">
                                            {{ $a['plate'] }}
                                        </a>
                                        <span class="tx-alerts__meta">{{ $a['label'] }}</span>
                                    </div>
                                    <div class="tx-alerts__num">
                                        @if($a['status'] === 'today')
                                            <span class="tx-alerts__val">HOY</span>
                                        @else
                                            <span class="tx-alerts__val">{{ $a['days'] }}</span>
                                            <span class="tx-alerts__days {{ $a['color'] === 'warning' ? 'is-warn' : '' }}">
                                                {{ $a['status'] === 'expired' ? 'días atrás' : 'días' }}
                                            </span>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                            @foreach($debtsTop as $d)
                                <li class="tx-alerts__row tx-alerts__row--debt">
                                    <span class="tx-alerts__pill tx-alerts__pill--debt">$</span>
                                    <div class="tx-alerts__main">
                                        <span class="tx-alerts__name">{{ $d['plate'] }}</span>
                                        <span class="tx-alerts__meta">Deuda pendiente</span>
                                    </div>
                                    <div class="tx-alerts__num">
                                        <span class="tx-alerts__val">S/ {{ $fmt($d['pending']) }}</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </article>
        </div>

        <div class="col-xl-7 tx-anim" style="--i:10">
            <article class="tx-panel">
                <header class="tx-panel__head">
                    <div>
                        <span class="tx-eyebrow">Movimiento reciente</span>
                        <h3 class="tx-panel__title">Últimos pagos registrados</h3>
                    </div>
                    <a href="{{ route('payments.index') }}" class="tx-link">Ver todos
                        <i class="ti ti-arrow-narrow-right"></i></a>
                </header>
                <div class="tx-panel__body p-0">
                    @if($recentPayments->isEmpty())
                        <div class="tx-empty">
                            <i class="ti ti-cash-off"></i>
                            <p>Sin pagos registrados.</p>
                        </div>
                    @else
                        <ul class="tx-feed">
                            @foreach($recentPayments as $pago)
                                @php
                                    $tipoUp = strtoupper($pago->type ?? '');
                                    $iconClass = match($tipoUp) {
                                        'PAGO'    => 'ti-cash',
                                        'DEUDA'   => 'ti-alert-triangle',
                                        'RETRASO' => 'ti-clock-hour-4',
                                        default   => 'ti-receipt-2',
                                    };
                                    $tipoMod = match($tipoUp) {
                                        'PAGO'    => 'pago',
                                        'DEUDA'   => 'deuda',
                                        'RETRASO' => 'retraso',
                                        default   => 'def',
                                    };
                                    $plate = $pago->vehicle?->plate ?? $pago->legacy_plate ?? '—';
                                    $hace = $pago->date_register
                                        ? \Carbon\Carbon::parse($pago->date_register)->locale('es')->diffForHumans(['parts' => 1, 'short' => true])
                                        : '';
                                @endphp
                                <li class="tx-feed__row">
                                    <span class="tx-feed__avatar" style="{{ $avatarStyle($plate) }}" title="{{ $plate }}">
                                        {{ $iniciales($plate) }}
                                        <span class="tx-feed__dot tx-feed__dot--{{ $tipoMod }}" title="{{ $pago->type }}">
                                            <i class="ti {{ $iconClass }}"></i>
                                        </span>
                                    </span>
                                    <div class="tx-feed__main">
                                        <div class="tx-feed__top">
                                            <span class="tx-feed__name">{{ $plate }}</span>
                                            <span class="tx-feed__amount tx-feed__amount--{{ $tipoMod }}">
                                                S/ {{ $fmt($pago->amount) }}
                                            </span>
                                        </div>
                                        <div class="tx-feed__bot">
                                            <span class="tx-pill is-{{ $tipoMod }}">{{ $pago->type ?: '—' }}</span>
                                            <span class="tx-feed__meta">
                                                <i class="ti ti-map-pin"></i> {{ $pago->headquarter?->name ?? '—' }}
                                                <span class="tx-feed__sep">·</span>
                                                <i class="ti ti-clock"></i> {{ $hace }}
                                            </span>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </article>
        </div>
    </div>

    {{-- ════════ ROW 5 · ÚLTIMAS SALIDAS ════════ --}}
    <div class="row g-3 mt-1 tx-stagger">
        <div class="col-12 tx-anim" style="--i:11">
            <article class="tx-panel">
                <header class="tx-panel__head">
                    <div>
                        <span class="tx-eyebrow">Operación</span>
                        <h3 class="tx-panel__title">Últimas salidas</h3>
                    </div>
                    <a href="{{ route('departures.index') }}" class="tx-link">Ir a salidas
                        <i class="ti ti-arrow-narrow-right"></i></a>
                </header>
                <div class="tx-panel__body p-0">
                    @if($recentDepartures->isEmpty())
                        <div class="tx-empty">
                            <i class="ti ti-car-off"></i>
                            <p>Sin salidas registradas.</p>
                        </div>
                    @else
                        <ul class="tx-feed tx-feed--grid">
                            @foreach($recentDepartures as $d)
                                @php
                                    $plate = $d->vehicle?->plate ?? $d->legacy_plate ?? '—';
                                    $hace = $d->date
                                        ? \Carbon\Carbon::parse($d->date)->locale('es')->diffForHumans(['parts' => 1, 'short' => true])
                                        : '';
                                @endphp
                                <li class="tx-feed__row">
                                    <span class="tx-feed__avatar" style="{{ $avatarStyle($plate) }}" title="{{ $plate }}">
                                        {{ $iniciales($plate) }}
                                        <span class="tx-feed__dot tx-feed__dot--dep">
                                            <i class="ti ti-route"></i>
                                        </span>
                                    </span>
                                    <div class="tx-feed__main">
                                        <div class="tx-feed__top">
                                            <span class="tx-feed__name">{{ $plate }}</span>
                                            <span class="tx-feed__amount">S/ {{ $fmt($d->price) }}</span>
                                        </div>
                                        <div class="tx-feed__bot">
                                            <span class="tx-pill is-dep">Salida</span>
                                            <span class="tx-feed__meta">
                                                <i class="ti ti-map-pin"></i> {{ $d->headquarter?->name ?? '—' }}
                                                <span class="tx-feed__sep">·</span>
                                                <i class="ti ti-users"></i> {{ (int) $d->passenger }}p
                                                <span class="tx-feed__sep">·</span>
                                                <i class="ti ti-clock"></i> {{ $hace }}
                                            </span>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </article>
        </div>
    </div>

{{-- ════════════════════════ ESTILOS (scoped) ════════════════════════ --}}
<style>
.tx-dashboard {
    --tx-ink:      #0B1B3D;
    --tx-ink-2:    #1B2A4E;
    --tx-paper:    #FAF7F1;
    --tx-bone:     #FFFFFF;
    --tx-rule:     #E8E2D5;
    --tx-rule-2:   #ECECF0;
    --tx-accent:   #009BDC;
    --tx-accent-2: #00B4D8;
    --tx-warn:     #C2410C;
    --tx-warn-bg:  #FFF4ED;
    --tx-ok:       #15803D;
    --tx-mute:     #6B6F7A;
    --tx-mute-2:   #9AA0AA;

    --tx-serif: 'Poppins', sans-serif;
    --tx-mono:  'Poppins', sans-serif;

    font-feature-settings: 'tnum' 1;
    padding: 0 16px 32px;
}

/* Tira superior */
.tx-topstrip {
    display: flex; align-items: flex-end; justify-content: space-between;
    padding: 4px 4px 18px; gap: 24px; flex-wrap: wrap;
}
.tx-greeting {
    font-family: 'Poppins', sans-serif;
    font-size: clamp(24px, 2.8vw, 34px); line-height: 1.15;
    color: var(--tx-ink); margin: 6px 0 0;
    letter-spacing: -0.01em; font-weight: 600;
}
.tx-greeting em { font-style: normal; color: var(--tx-accent); font-weight: 700; }
.tx-greeting__sub {
    display: block; font-size: 13px;
    color: var(--tx-mute); margin-top: 4px; font-weight: 400;
}
.tx-topstrip__right {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 6px 12px; border: 1px solid var(--tx-rule-2);
    border-radius: 999px; background: var(--tx-bone);
}
.tx-pulse {
    width: 8px; height: 8px; border-radius: 999px; background: var(--tx-ok);
    box-shadow: 0 0 0 0 rgba(21,128,61,.55); animation: txPulse 2s infinite;
}
@keyframes txPulse {
    0%   { box-shadow: 0 0 0 0 rgba(21,128,61,.55); }
    70%  { box-shadow: 0 0 0 8px rgba(21,128,61,0); }
    100% { box-shadow: 0 0 0 0 rgba(21,128,61,0); }
}

/* Eyebrows / utilitarias */
.tx-eyebrow {
    display: inline-block; font-size: 10px;
    letter-spacing: 0.18em; text-transform: uppercase;
    color: var(--tx-mute); font-weight: 600;
}
.tx-eyebrow--light { color: rgba(255,255,255,.62); }
.tx-eyebrow--mono  { letter-spacing: 0.06em; }
.tx-eyebrow--warn  { color: var(--tx-warn); }
.tx-mono { font-size: 11px; color: var(--tx-mute); letter-spacing: .02em; }

/* HERO */
.tx-hero {
    position: relative; overflow: hidden;
    background: radial-gradient(120% 140% at 0% 0%, #14274F 0%, #0B1B3D 55%, #07122A 100%);
    color: #fff; border-radius: 18px; padding: 28px 32px 24px;
    min-height: 320px; display: flex; flex-direction: column;
    box-shadow: 0 18px 40px -22px rgba(11,27,61,.55);
}
.tx-hero__grain {
    position: absolute; inset: 0; pointer-events: none; opacity: .35;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='160' height='160'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/><feColorMatrix values='0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 0.06 0'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>");
    mix-blend-mode: overlay;
}
.tx-hero__head {
    position: relative; z-index: 1;
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 12px; flex-wrap: wrap; gap: 8px;
}
.tx-chip {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.16);
    color: #fff; font-size: 11px; padding: 5px 11px; border-radius: 999px;
}
.tx-chip__dot {
    width: 6px; height: 6px; border-radius: 999px;
    background: var(--tx-accent-2);
    box-shadow: 0 0 8px var(--tx-accent-2);
}
.tx-hero__num {
    position: relative; z-index: 1;
    display: flex; align-items: baseline; gap: 6px;
    margin: 18px 0 8px; line-height: 1; color: #fff; flex-wrap: wrap;
}
.tx-currency { font-size: 24px; color: rgba(255,255,255,.55); font-weight: 500; }
.tx-bigint { font-size: clamp(40px, 6vw, 72px); font-weight: 700; letter-spacing: -0.025em; font-feature-settings: 'tnum' 1; }
.tx-decimal { font-size: 24px; color: rgba(255,255,255,.55); font-weight: 500; }
.tx-hero__foot {
    position: relative; z-index: 1; margin-top: auto; padding-top: 18px;
    display: grid; grid-template-columns: 1.2fr 1fr; gap: 24px; align-items: end;
    border-top: 1px solid rgba(255,255,255,.12);
}
.tx-hero__sparkwrap { display: flex; flex-direction: column; min-width: 0; }
.tx-hero__sparklbl  { font-size: 10px; color: rgba(255,255,255,.5); margin-top: 4px; }
.tx-hero__split { display: flex; gap: 28px; flex-wrap: wrap; }
.tx-hero__split > div { display: flex; flex-direction: column; gap: 4px; }
.tx-hero__val {
    font-weight: 600; font-size: 18px; color: #fff;
    font-feature-settings: 'tnum' 1;
}
.tx-hero__val--warn { color: #FFB57A; }
@media (max-width: 768px) {
    .tx-hero { padding: 22px 20px; min-height: auto; }
    .tx-hero__foot { grid-template-columns: 1fr; gap: 16px; }
}

/* OPS DEL DÍA */
.tx-ops {
    background: var(--tx-bone); border: 1px solid var(--tx-rule-2);
    border-radius: 18px; padding: 22px 22px 14px; height: 100%;
    display: flex; flex-direction: column;
}
.tx-ops__head {
    display: flex; justify-content: space-between; align-items: center;
    padding-bottom: 14px; border-bottom: 1px dashed var(--tx-rule);
}
.tx-ops__list { list-style: none; margin: 6px 0 0; padding: 0; flex: 1; display: flex; flex-direction: column; }
.tx-ops__row {
    display: grid; grid-template-columns: auto 1fr auto;
    align-items: center; gap: 12px; padding: 12px 0;
    border-bottom: 1px solid var(--tx-rule-2);
}
.tx-ops__row:last-child { border-bottom: 0; }
.tx-ops__row--total { margin-top: auto; padding-top: 14px; border-top: 1px dashed var(--tx-rule); border-bottom: 0; }
.tx-ops__dot { width: 8px; height: 8px; border-radius: 999px; }
.tx-ops__dot--cyan  { background: var(--tx-accent); }
.tx-ops__dot--green { background: var(--tx-ok); }
.tx-ops__dot--red   { background: #B91C1C; }
.tx-ops__dot--ink   { background: var(--tx-ink); }
.tx-ops__txt { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.tx-ops__lbl { font-size: 13px; color: var(--tx-ink); font-weight: 500; }
.tx-ops__sub { font-size: 10px; color: var(--tx-mute-2); }
.tx-ops__num { font-weight: 600; font-size: 15px; color: var(--tx-ink); font-feature-settings: 'tnum' 1; }
.tx-ops__num--big { font-size: 18px; font-weight: 700; }
.tx-ops__num.is-neg { color: var(--tx-warn); }

/* DIVISORES */
.tx-section { display: flex; align-items: center; gap: 14px; margin: 32px 0 16px; }
.tx-section__num { font-weight: 700; font-size: 16px; color: var(--tx-mute-2); }
.tx-section__lbl { font-size: 11px; letter-spacing: 0.22em; text-transform: uppercase; color: var(--tx-ink); font-weight: 600; }
.tx-section__rule { flex: 1; height: 1px; background: var(--tx-rule); }

/* KPI TILES */
.tx-kpi {
    background: var(--tx-bone); border: 1px solid var(--tx-rule-2);
    border-radius: 16px; padding: 18px 20px 16px; height: 100%;
    transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease;
}
.tx-kpi:hover { transform: translateY(-2px); border-color: var(--tx-ink); box-shadow: 0 12px 28px -18px rgba(11,27,61,.4); }
.tx-kpi__top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
.tx-kpi__icon { color: var(--tx-mute-2); font-size: 18px; }
.tx-kpi__num { font-weight: 700; font-size: 32px; line-height: 1; color: var(--tx-ink); letter-spacing: -0.02em; font-feature-settings: 'tnum' 1; }
.tx-kpi__num small { font-size: 14px; color: var(--tx-mute); margin-right: 4px; font-weight: 500; }
.tx-kpi__foot { margin-top: 10px; min-height: 16px; }
.tx-kpi--warn .tx-kpi__num { color: var(--tx-warn); }
.tx-kpi--warn .tx-kpi__icon { color: var(--tx-warn); }
.tx-kpi__bar { margin-top: 12px; height: 4px; background: var(--tx-rule-2); border-radius: 999px; overflow: hidden; }
.tx-kpi__bar span { display: block; height: 100%; background: linear-gradient(90deg, var(--tx-accent), var(--tx-ink)); border-radius: 999px; }
.tx-kpi--warn .tx-kpi__bar span { background: linear-gradient(90deg, #F59E0B, var(--tx-warn)); }

/* PANELS */
.tx-panel {
    background: var(--tx-bone); border: 1px solid var(--tx-rule-2);
    border-radius: 16px; overflow: hidden; height: 100%;
    display: flex; flex-direction: column;
}
.tx-panel--ink { background: linear-gradient(180deg, #0B1B3D 0%, #14274F 100%); color: #fff; border-color: transparent; }
.tx-panel--ink .tx-panel__title { color: #fff; }
.tx-panel--warn { border-left: 3px solid var(--tx-warn); }
.tx-panel__head { display: flex; justify-content: space-between; align-items: flex-start; padding: 18px 22px 12px; gap: 16px; flex-wrap: wrap; }
.tx-panel__title { font-size: 18px; color: var(--tx-ink); margin: 4px 0 0; letter-spacing: -0.01em; font-weight: 600; }
.tx-panel__title em { font-style: normal; font-weight: 700; font-feature-settings: 'tnum' 1; }
.tx-panel__title small { font-size: 13px; color: var(--tx-mute); margin-left: 4px; }
.tx-panel__title--light { color: #fff; }
.tx-panel__title--light small { color: rgba(255,255,255,.6); }
.tx-panel__body { padding: 6px 18px 18px; }
.tx-panel__body.p-0 { padding: 0; }
.tx-link {
    font-size: 11px; color: var(--tx-ink); text-decoration: none; font-weight: 500;
    letter-spacing: 0.04em; display: inline-flex; align-items: center; gap: 4px;
    border-bottom: 1px solid transparent; transition: border-color .2s ease, color .2s ease;
}
.tx-panel--ink .tx-link { color: rgba(255,255,255,.7); }
.tx-link:hover { color: var(--tx-accent); border-bottom-color: var(--tx-accent); }
.tx-legend { display: flex; gap: 12px; }
.tx-legend span { display: inline-flex; align-items: center; gap: 6px; font-size: 10px; color: var(--tx-mute); }
.tx-legend__sw { width: 8px; height: 8px; border-radius: 2px; display: inline-block; }

/* DONUT LEGEND */
.tx-donut__legend { list-style: none; margin: 0; padding: 14px 22px 18px; border-top: 1px solid rgba(255,255,255,.08); }
.tx-donut__legend li { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 10px; padding: 5px 0; font-size: 13px; color: rgba(255,255,255,.85); }
.tx-donut__legend li.tx-donut__empty { display: block; text-align: center; color: rgba(255,255,255,.5); font-size: 12px; padding: 12px 0; }
.tx-donut__sw { width: 10px; height: 10px; border-radius: 3px; }
.tx-donut__val { font-weight: 700; font-size: 13px; color: #fff; font-feature-settings: 'tnum' 1; }

/* ALERTAS */
.tx-alerts { list-style: none; margin: 0; padding: 0; }
.tx-alerts__row {
    display: grid; grid-template-columns: auto 1fr auto;
    align-items: center; gap: 12px;
    padding: 12px 22px; border-top: 1px solid var(--tx-rule-2);
    transition: background .2s ease;
}
.tx-alerts__row:first-child { border-top: 0; }
.tx-alerts__row:hover { background: var(--tx-warn-bg); }
.tx-alerts__row--debt:hover { background: rgba(0,155,220,.04); }
.tx-alerts__pill {
    display: inline-flex; align-items: center; justify-content: center;
    width: 34px; height: 34px; border-radius: 9px;
    font-size: 11px; font-weight: 800; letter-spacing: 0.05em; color: #fff;
}
.tx-alerts__pill--danger  { background: linear-gradient(135deg, #ef4444, #b91c1c); box-shadow: 0 4px 12px rgba(220,38,38,.32); }
.tx-alerts__pill--warning { background: linear-gradient(135deg, #f59e0b, #b45309); box-shadow: 0 4px 12px rgba(217,119,6,.32); }
.tx-alerts__pill--debt    { background: linear-gradient(135deg, #64748b, #334155); }
.tx-alerts__main { min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.tx-alerts__name { font-size: 13px; color: var(--tx-ink); font-weight: 600; text-decoration: none; }
.tx-alerts__name:hover { color: var(--tx-accent); }
.tx-alerts__meta { font-size: 10px; color: var(--tx-mute-2); }
.tx-alerts__num { text-align: right; }
.tx-alerts__val { display: block; font-weight: 700; font-size: 15px; color: var(--tx-ink); font-feature-settings: 'tnum' 1; }
.tx-alerts__days {
    display: inline-block; margin-top: 2px;
    font-size: 10px; color: var(--tx-warn); font-weight: 600;
    background: var(--tx-warn-bg); padding: 2px 6px; border-radius: 3px;
}
.tx-alerts__days.is-warn { color: #92400E; background: #FEF3C7; }

/* FEED */
.tx-feed { list-style: none; margin: 0; padding: 0; }
.tx-feed__row {
    display: grid; grid-template-columns: 40px 1fr;
    align-items: center; gap: 14px;
    padding: 14px 22px; border-top: 1px solid var(--tx-rule-2);
    transition: background .15s ease;
}
.tx-feed__row:first-child { border-top: 0; }
.tx-feed__row:hover { background: rgba(0,155,220,.04); }
.tx-feed__avatar {
    position: relative; width: 40px; height: 40px; border-radius: 12px;
    border: 1px solid; display: inline-flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700; letter-spacing: 0.02em; flex-shrink: 0;
    transition: transform .2s ease; user-select: none;
}
.tx-feed__row:hover .tx-feed__avatar { transform: scale(1.05); }
.tx-feed__dot {
    position: absolute; bottom: -3px; right: -3px;
    width: 18px; height: 18px; border-radius: 999px; border: 2px solid #fff;
    display: inline-flex; align-items: center; justify-content: center;
    box-shadow: 0 1px 3px rgba(0,0,0,.15);
}
.tx-feed__dot i { font-size: 10px; line-height: 1; }
.tx-feed__dot--pago    { background: #15803D; color: #fff; }
.tx-feed__dot--deuda   { background: #DC2626; color: #fff; }
.tx-feed__dot--retraso { background: #F59E0B; color: #fff; }
.tx-feed__dot--def     { background: #6B7280; color: #fff; }
.tx-feed__dot--dep     { background: var(--tx-accent); color: #fff; }
.tx-feed--grid { display: grid; grid-template-columns: 1fr; gap: 0; }
@media (min-width: 992px) {
    .tx-feed--grid { grid-template-columns: 1fr 1fr; gap: 0; }
    .tx-feed--grid .tx-feed__row:nth-child(2) { border-top: 0; }
    .tx-feed--grid .tx-feed__row:nth-child(odd) { border-right: 1px solid var(--tx-rule-2); }
}
.tx-feed__main { min-width: 0; display: flex; flex-direction: column; gap: 4px; }
.tx-feed__top { display: flex; justify-content: space-between; align-items: center; gap: 12px; min-width: 0; }
.tx-feed__name { font-size: 13.5px; font-weight: 600; color: var(--tx-ink); text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; min-width: 0; }
.tx-feed__amount { font-size: 16px; font-weight: 700; color: var(--tx-ink); letter-spacing: -0.01em; font-feature-settings: 'tnum' 1; white-space: nowrap; }
.tx-feed__amount--pago    { color: var(--tx-ok); }
.tx-feed__amount--deuda   { color: #991B1B; }
.tx-feed__amount--retraso { color: #92400E; }
.tx-feed__bot { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.tx-feed__meta { font-size: 11px; color: var(--tx-mute); display: inline-flex; align-items: center; gap: 4px; font-weight: 500; }
.tx-feed__meta i { font-size: 12px; opacity: .7; }
.tx-feed__sep { margin: 0 4px; opacity: .5; }

.tx-pill {
    display: inline-block; font-size: 10px; letter-spacing: 0.08em;
    padding: 3px 9px; border-radius: 3px; font-weight: 600;
    text-transform: uppercase; background: #ECECF0; color: #4A4F5A;
}
.tx-pill.is-pago    { background: #DCFCE7; color: #166534; }
.tx-pill.is-deuda   { background: #FEE2E2; color: #991B1B; }
.tx-pill.is-retraso { background: #FEF3C7; color: #92400E; }
.tx-pill.is-dep     { background: #E0F2FE; color: #075985; }

.tx-empty { text-align: center; padding: 40px 20px; color: var(--tx-mute); font-size: 13px; }
.tx-empty i { font-size: 32px; color: var(--tx-ok); display: block; margin-bottom: 8px; }
.tx-empty p { margin: 0; }

/* Animación */
.tx-anim {
    opacity: 0; transform: translateY(12px);
    animation: txRise .55s cubic-bezier(.2,.7,.2,1) forwards;
    animation-delay: calc(var(--i, 0) * 70ms);
}
@keyframes txRise { to { opacity: 1; transform: translateY(0); } }

/* Responsive */
@media (max-width: 992px) {
    .tx-greeting { font-size: 26px; }
    .tx-dashboard { padding: 0 8px 24px; }
}
</style>

</div>

@script
<script>
(function () {
    const dataIni = {
        spark: @json($spark7Vals),
        days:  @json(array_values(array_filter($days, fn($r) => ($r['income'] != 0 || $r['expense'] != 0)))),
        donut: {
            labels: @json(collect($topHQ)->pluck('hq')->values()),
            data:   @json(collect($topHQ)->pluck('sum')->map(fn($v) => (float)$v)->values()),
        },
    };
    const palette = ['#00B4D8','#F59E0B','#22C55E','#A855F7','#EC4899'];

    let chart30 = null;

    function ready(fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }

    function buildSpark() {
        const el = document.getElementById('txSpark');
        if (!el || el.dataset.rendered === '1') return;
        new ApexCharts(el, {
            chart: { type: 'area', height: 70, sparkline: { enabled: true }, animations: { speed: 700 } },
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.5, opacityTo: 0, stops: [0, 100] } },
            colors: ['#00B4D8'],
            series: [{ name: 'Ingresos', data: dataIni.spark }],
            tooltip: { theme: 'dark', x: { show: false }, y: { formatter: v => 'S/ ' + Number(v).toLocaleString('es-PE',{minimumFractionDigits:2}) } },
        }).render();
        el.dataset.rendered = '1';
    }

    function buildChart30(filtered) {
        const el = document.getElementById('txChart30');
        if (!el) return;
        if (chart30) { chart30.destroy(); chart30 = null; }
        if (!filtered || !filtered.length) return;

        chart30 = new ApexCharts(el, {
            chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'Poppins, sans-serif', animations: { speed: 600 } },
            series: [
                { name: 'Ingresos', data: filtered.map(d => parseFloat(Number(d.income).toFixed(2))) },
                { name: 'Egresos',  data: filtered.map(d => parseFloat(Number(d.expense).toFixed(2))) },
            ],
            colors: ['#15803D', '#B91C1C'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
            dataLabels: { enabled: false },
            xaxis: {
                categories: filtered.map(d => {
                    const parts = d.date.split('-');
                    return parts[2] + '/' + parts[1];
                }),
                labels: { style: { fontSize: '10px', colors: '#6B6F7A', fontFamily: 'Poppins, sans-serif' } },
                axisBorder: { show: false }, axisTicks: { show: false },
            },
            yaxis: {
                labels: {
                    style: { fontSize: '10px', colors: '#9AA0AA', fontFamily: 'Poppins, sans-serif' },
                    formatter: v => 'S/ ' + (v >= 1000 ? (v/1000).toFixed(1) + 'k' : v.toFixed(0)),
                },
            },
            grid: { borderColor: '#ECECF0', strokeDashArray: 3, padding: { left: 10, right: 10 } },
            tooltip: { y: { formatter: v => 'S/ ' + Number(v).toLocaleString('es-PE', {minimumFractionDigits:2}) } },
            legend: { show: false },
        });
        chart30.render();
    }

    function buildDonut() {
        const el = document.getElementById('txDonut');
        if (!el || el.dataset.rendered === '1' || !dataIni.donut.data.length) return;
        new ApexCharts(el, {
            chart: { type: 'donut', height: 200, fontFamily: 'Poppins, sans-serif', animations: { speed: 700 } },
            series: dataIni.donut.data,
            labels: dataIni.donut.labels,
            colors: dataIni.donut.labels.map((_, i) => palette[i % palette.length]),
            stroke: { width: 0 },
            dataLabels: { enabled: false },
            legend: { show: false },
            plotOptions: {
                pie: {
                    donut: {
                        size: '72%',
                        labels: {
                            show: true,
                            name:  { show: true, fontSize: '11px', fontWeight: 500, color: 'rgba(255,255,255,.65)', offsetY: 22 },
                            value: { show: true, fontSize: '20px', fontWeight: 700, color: '#fff', offsetY: -8, formatter: v => 'S/ ' + (v >= 1000 ? (v/1000).toFixed(1)+'k' : Number(v).toFixed(0)) },
                            total: { show: true, label: 'Total', fontSize: '11px', fontWeight: 500, color: 'rgba(255,255,255,.65)', formatter: w => 'S/ ' + (w.globals.seriesTotals.reduce((a,b)=>a+b,0) >= 1000 ? (w.globals.seriesTotals.reduce((a,b)=>a+b,0)/1000).toFixed(1)+'k' : w.globals.seriesTotals.reduce((a,b)=>a+b,0).toFixed(0)) },
                        }
                    }
                }
            },
            tooltip: { theme: 'dark', y: { formatter: v => 'S/ ' + Number(v).toLocaleString('es-PE', {minimumFractionDigits:2}) } },
        }).render();
        el.dataset.rendered = '1';
    }

    function buildAll() {
        if (typeof ApexCharts === 'undefined') { setTimeout(buildAll, 120); return; }
        buildSpark();
        buildChart30(dataIni.days);
        buildDonut();
    }

    ready(buildAll);

    // Refresca chart 30d cuando cambia el mes
    $wire.on('chart-data', (event) => {
        buildChart30(event.data);
    });
})();
</script>
@endscript
