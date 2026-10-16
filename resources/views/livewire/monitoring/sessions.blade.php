@push('datepicker_css')
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>
@endpush
{{-- Se refresca solo cada REFRESH_SECONDS, salvo con la pestaña oculta, el calendario abierto
     (cada petición de Livewire rehace los datepickers y lo cerraría) o una confirmación en pantalla. --}}
<div class="container-fluid monitoring-sessions" x-data="{
    init() {
        setInterval(() => {
            if (document.hidden || (window.jQuery && jQuery('#ui-datepicker-div').is(':visible')) || Swal.isVisible()) { return }
            this.$wire.$refresh()
        }, {{ \App\Livewire\Monitoring\Sessions::REFRESH_SECONDS * 1000 }})
    },
    confirmClose(title, html, action) {
        Swal.fire({
            title: title,
            html: html,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, cerrar',
            cancelButtonText: 'Cancelar',
        }).then((result) => { if (result.isConfirmed) { action() } })
    }
}">
    <style>
        .monitoring-sessions { --mon-radius: 14px; --mon-shadow: 0 6px 18px rgba(16, 24, 40, .06); --mon-border: #eceff3; }
        .monitoring-sessions .mon-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 14px; }
        .monitoring-sessions .mon-kpi { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid var(--mon-border); border-radius: var(--mon-radius); box-shadow: var(--mon-shadow); padding: 14px 16px; }
        .monitoring-sessions .mon-kpi-ico { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; font-size: 22px; flex-shrink: 0; }
        .monitoring-sessions .mon-kpi-num { font-size: 26px; font-weight: 700; line-height: 1; color: #1b2430; }
        .monitoring-sessions .mon-kpi-label { font-size: 12px; color: #6b7280; margin-top: 3px; }
        .monitoring-sessions .mon-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; background: #fff; border: 1px solid var(--mon-border); border-radius: var(--mon-radius); box-shadow: var(--mon-shadow); padding: 10px 14px; margin-bottom: 16px; }
        .monitoring-sessions .mon-user-filter { position: relative; flex: 1 1 220px; max-width: 360px; }
        .monitoring-sessions .mon-user-filter i { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #9aa3af; pointer-events: none; }
        .monitoring-sessions .mon-user-filter select { padding-left: 32px; border-radius: 10px; }
        .monitoring-sessions .mon-dates { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .monitoring-sessions .mon-dates input { width: 112px; border-radius: 10px; }
        .monitoring-sessions .mon-user { background: #fff; border: 1px solid var(--mon-border); border-radius: var(--mon-radius); box-shadow: var(--mon-shadow); margin-bottom: 16px; overflow: hidden; }
        .monitoring-sessions .mon-head { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 14px 16px; border-bottom: 1px solid var(--mon-border); }
        .monitoring-sessions .mon-avatar { width: 46px; height: 46px; border-radius: 50%; display: grid; place-items: center; font-weight: 700; font-size: 15px; color: #fff; background: linear-gradient(135deg, #6b7280, #9aa3af); flex-shrink: 0; position: relative; }
        .monitoring-sessions .mon-avatar.online { background: linear-gradient(135deg, #16a34a, #4ade80); }
        .monitoring-sessions .mon-avatar.connected { background: linear-gradient(135deg, #d97706, #fbbf24); }
        .monitoring-sessions .mon-avatar .mon-avatar-dot { position: absolute; right: -1px; bottom: -1px; width: 13px; height: 13px; border-radius: 50%; border: 2px solid #fff; background: #adb5bd; }
        .monitoring-sessions .mon-avatar .mon-avatar-dot.active { background: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, .25); }
        .monitoring-sessions .mon-name { font-weight: 700; font-size: 15px; color: #1b2430; }
        .monitoring-sessions .mon-sub { font-size: 12px; color: #6b7280; }
        .monitoring-sessions .mon-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 999px; background: #f1f3f6; color: #374151; }
        .monitoring-sessions .mon-chip.green { background: #dcfce7; color: #166534; }
        .monitoring-sessions .mon-chip.amber { background: #fef3c7; color: #92400e; }
        .monitoring-sessions .mon-chip.red { background: #fee2e2; color: #991b1b; }
        .monitoring-sessions .mon-chip.gray { background: #f1f3f6; color: #6b7280; }
        .monitoring-sessions .mon-last { font-size: 12px; padding: 4px 10px; border: 1px solid transparent; }
        .monitoring-sessions .mon-last.green { border-color: #86efac; }
        .monitoring-sessions .mon-last.amber { border-color: #fcd34d; }
        .monitoring-sessions .mon-last b { font-weight: 700; }
        .monitoring-sessions .mon-col { padding: 12px 16px; min-width: 0; }
        .monitoring-sessions .mon-col + .mon-col { border-top: 1px solid var(--mon-border); }
        .monitoring-sessions .mon-title { font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #6b7280; margin-bottom: 8px; }
        .monitoring-sessions .mon-sessions { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(340px, 100%), 1fr)); gap: 8px; }
        .monitoring-sessions .mon-session { display: flex; align-items: center; gap: 12px; padding: 9px 10px; border-radius: 10px; border: 1px solid var(--mon-border); background: #fafbfc; min-width: 0; }
        .monitoring-sessions .mon-session.current { background: #f0fdf4; border-color: #bbf7d0; }
        .monitoring-sessions .mon-device { width: 38px; height: 38px; border-radius: 10px; background: #fff; border: 1px solid var(--mon-border); display: grid; place-items: center; font-size: 19px; color: #374151; flex-shrink: 0; }
        .monitoring-sessions .mon-session-text { flex: 1 1 auto; min-width: 0; }
        .monitoring-sessions .mon-session-l1 { font-size: 13px; font-weight: 600; color: #1b2430; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .monitoring-sessions .mon-session-l2 { font-size: 12px; color: #6b7280; display: flex; flex-wrap: wrap; gap: 4px 10px; }
        .monitoring-sessions .mon-live { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, .2); display: inline-block; }
        .monitoring-sessions .mon-idle { width: 8px; height: 8px; border-radius: 50%; background: #cbd5e1; display: inline-block; }
        .monitoring-sessions .mon-close { border-radius: 8px; font-size: 12px; white-space: nowrap; }
        .monitoring-sessions .mon-ip { display: inline-flex; align-items: center; gap: 4px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11.5px; font-weight: 600; padding: 2px 8px; border-radius: 999px; background: var(--ip-bg); border: 1px solid var(--ip-bd); color: var(--ip-tx); white-space: nowrap; }
        .monitoring-sessions .mon-logins { max-height: 230px; overflow: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(min(330px, 100%), 1fr)); gap: 4px 14px; padding: 8px 10px; border: 1px solid var(--mon-border); border-radius: 10px; background: #fafbfc; }
        .monitoring-sessions .mon-login { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #374151; min-width: 0; padding: 2px 0; }
        .monitoring-sessions .mon-login .mon-date { font-weight: 600; color: #1b2430; white-space: nowrap; }
        .monitoring-sessions .mon-login .mon-agent { color: #6b7280; flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .monitoring-sessions .mon-empty { font-size: 12px; color: #9aa3af; padding: 10px; border: 1px dashed var(--mon-border); border-radius: 10px; text-align: center; }
        @media (max-width: 575.98px) {
            .monitoring-sessions .mon-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .monitoring-sessions .mon-kpi-num { font-size: 22px; }
            .monitoring-sessions .mon-close span { display: none; }
        }
    </style>

    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">MONITOREO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-devices f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Seguridad</span></a>
                </li>
                <li class="d-flex active"><a class="f-s-14">Sesiones de los usuarios</a></li>
            </ul>
        </div>
    </div>

    @if(! $available)
        <div class="alert alert-warning py-2 small">
            <i class="ti ti-alert-triangle"></i> Este servidor guarda las sesiones en archivos: el monitoreo necesita que se guarden
            en la base de datos (<code>SESSION_DRIVER=database</code>).
        </div>
    @endif

    {{-- Indicadores --}}
    <div class="mon-kpis">
        <div class="mon-kpi">
            <div class="mon-kpi-ico" style="background:#dcfce7;color:#166534;"><i class="ti ti-users"></i></div>
            <div><div class="mon-kpi-num">{{ $usersWithSession }}</div><div class="mon-kpi-label">usuario(s) con sesión abierta</div></div>
        </div>
        <div class="mon-kpi">
            <div class="mon-kpi-ico" style="background:#e0e7ff;color:#3730a3;"><i class="ti ti-devices"></i></div>
            <div><div class="mon-kpi-num">{{ $totalSessions }}</div><div class="mon-kpi-label">sesión(es) en total</div></div>
        </div>
        <div class="mon-kpi">
            <div class="mon-kpi-ico" style="background:#d1fae5;color:#065f46;"><i class="ti ti-activity"></i></div>
            <div><div class="mon-kpi-num">{{ $activeNow }}</div><div class="mon-kpi-label">con actividad en {{ \App\Services\ActiveSessions::ACTIVE_MINUTES }} min</div></div>
        </div>
        <div class="mon-kpi">
            <div class="mon-kpi-ico" style="background:#fee2e2;color:#991b1b;"><i class="ti ti-shield-x"></i></div>
            <div><div class="mon-kpi-num">{{ $failedThisWeek }}</div><div class="mon-kpi-label">intento(s) fallido(s) en {{ \App\Livewire\Monitoring\Sessions::FAILED_LOGIN_DAYS }} días</div></div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mon-toolbar">
        <div class="mon-user-filter">
            <i class="ti ti-user"></i>
            <select class="form-select form-select-sm" wire:model.live="selectedUserId" title="Usuario">
                <option value="">Todos los usuarios activos</option>
                @foreach($activeUsers as $activeUser)
                    <option value="{{ $activeUser->id }}">{{ $activeUser->username }} · {{ $activeUser->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-check form-switch mb-0 small">
            <input class="form-check-input" type="checkbox" id="onlyWithSession" wire:model.live="onlyWithSession">
            <label class="form-check-label" for="onlyWithSession">Solo con sesión abierta</label>
        </div>
        <div class="mon-dates">
            <span class="text-muted small"><i class="ti ti-calendar"></i> Accesos</span>
            <input type="text" wire:ignore id="monitoring_date_from" class="form-control form-control-sm" wire:model.blur="dateFrom" autocomplete="off" placeholder="Desde" title="Desde (aaaa-mm-dd)">
            <span class="text-muted small">al</span>
            <input type="text" wire:ignore id="monitoring_date_to" class="form-control form-control-sm" wire:model.blur="dateTo" autocomplete="off" placeholder="Hasta" title="Hasta (aaaa-mm-dd)">
            @if($dateFrom !== '' || $dateTo !== '')
                <button type="button" class="btn btn-sm btn-light" wire:click="clearDates" title="Quitar el rango"><i class="ti ti-x"></i></button>
            @endif
        </div>
        <span class="ms-auto text-muted small"><i class="ti ti-refresh"></i> Se actualiza solo cada {{ \App\Livewire\Monitoring\Sessions::REFRESH_SECONDS }} s · <span class="mon-live"></span> actividad reciente</span>
    </div>

    @forelse($blocks as $block)
        @php
            $user = $block['user'];
            $count = $block['sessions']->count();
            $others = $block['sessions']->where('current', false)->count();
            $online = $block['sessions']->contains(fn (array $session) => $session['active']);
            $state = $online ? 'online' : ($count ? 'connected' : 'offline');
            $lastActivity = $block['last_activity'];
        @endphp
        <div class="mon-user" wire:key="user-{{ $user->id }}">
            <div class="mon-head">
                <div class="mon-avatar {{ $state === 'offline' ? '' : $state }}">
                    {{ mb_strtoupper(mb_substr((string) $user->username, 0, 2)) }}
                    <span class="mon-avatar-dot {{ $online ? 'active' : '' }}"></span>
                </div>
                <div class="flex-grow-1" style="min-width:0;">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="mon-name">{{ $user->username }}</span>
                        @if($block['role'])<span class="mon-chip">{{ __('roles.' . $block['role']) }}</span>@endif
                        @if($user->status !== 'active')<span class="mon-chip amber"><i class="ti ti-user-off"></i> Desactivado</span>@endif
                        @if($block['failed'] > 0)
                            <span class="mon-chip red" title="Intentos de acceso fallidos en los últimos {{ \App\Livewire\Monitoring\Sessions::FAILED_LOGIN_DAYS }} días"><i class="ti ti-alert-triangle"></i> {{ $block['failed'] }} fallido(s)</span>
                        @endif
                    </div>
                    <div class="mon-sub d-flex flex-wrap align-items-center gap-2">
                        <span>{{ $user->name }}</span>
                        @if($lastActivity)
                            {{-- Verde si es de hace minutos, ámbar si es de la última hora, gris si es más vieja. --}}
                            @php $minutesAgo = $lastActivity->diffInMinutes(now()); @endphp
                            <span class="mon-chip mon-last {{ $minutesAgo <= \App\Services\ActiveSessions::ACTIVE_MINUTES ? 'green' : ($minutesAgo <= 60 ? 'amber' : 'gray') }}" title="{{ $lastActivity->format('d/m/Y H:i:s') }}">
                                <i class="ti ti-clock-hour-4"></i> última actividad <b>{{ $lastActivity->diffForHumans() }}</b>
                            </span>
                        @endif
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    @if($state === 'online')
                        <span class="mon-chip green"><span class="mon-live"></span> En línea</span>
                    @elseif($state === 'connected')
                        <span class="mon-chip amber"><i class="ti ti-clock"></i> Conectado, sin actividad</span>
                    @else
                        <span class="mon-chip gray"><i class="ti ti-plug-off"></i> Sin sesión</span>
                    @endif
                    <span class="mon-chip">{{ $count }} sesión(es) abierta(s)</span>
                    @if($others > 0)
                        <button type="button" class="btn btn-sm btn-outline-danger mon-close"
                                x-on:click="confirmClose('Cerrar sesiones', @js(($others === 1 ? '¿Cerrar la sesión' : "¿Cerrar las {$others} sesiones") . ' de <b>' . e($user->username) . '</b>?<br><small>Tendrá que volver a iniciar sesión.</small>'), () => $wire.closeAll({{ $user->id }}))">
                            <i class="ti ti-logout"></i> <span>Cerrar {{ $others === 1 ? 'su sesión' : 'todas sus sesiones' }}</span>
                        </button>
                    @endif
                </div>
            </div>

            {{-- Sesiones abiertas --}}
            <div class="mon-col">
                <div class="mon-title"><i class="ti ti-devices"></i> Sesiones abiertas</div>
                @if($count === 0)
                    <div class="mon-empty">Sin sesiones abiertas.</div>
                @else
                    <div class="mon-sessions">
                        @foreach($block['sessions'] as $session)
                            <div class="mon-session {{ $session['current'] ? 'current' : '' }}" wire:key="session-{{ $session['key'] }}">
                                <div class="mon-device"><i class="ti {{ $session['agent']['device'] === 'Celular' ? 'ti-device-mobile' : ($session['agent']['device'] === 'Tablet' ? 'ti-device-tablet' : 'ti-device-desktop') }}"></i></div>
                                <div class="mon-session-text">
                                    <div class="mon-session-l1">
                                        <span class="{{ $session['active'] ? 'mon-live' : 'mon-idle' }}" title="{{ $session['active'] ? 'Con actividad ahora' : 'Sin actividad reciente' }}"></span>
                                        {{ $session['agent']['device'] }} · {{ $session['agent']['os'] }}
                                        <span class="text-muted fw-normal">· {{ $session['agent']['browser'] }}</span>
                                        @if($session['current'])<span class="mon-chip green">Esta sesión</span>@endif
                                    </div>
                                    <div class="mon-session-l2">
                                        <span class="mon-ip" style="{{ \App\Services\IpColor::style($ipPalette[$session['ip']] ?? null) }}"><i class="ti ti-world"></i> {{ $session['ip'] ?: '—' }}</span>
                                        <span title="{{ $session['last_activity']->format('d/m/Y H:i:s') }}"><i class="ti ti-clock"></i> {{ $session['last_activity']->diffForHumans() }}</span>
                                    </div>
                                </div>
                                @unless($session['current'])
                                    <button type="button" class="btn btn-sm btn-outline-danger mon-close" title="Cerrar esta sesión"
                                            x-on:click="confirmClose('Cerrar sesión', @js('¿Cerrar esta sesión de <b>' . e($user->username) . '</b>?<br><small>' . e($session['agent']['text']) . ' · IP ' . e($session['ip'] ?? '—') . '</small>'), () => $wire.closeSession('{{ $session['key'] }}'))">
                                        <i class="ti ti-logout"></i> <span>Cerrar</span>
                                    </button>
                                @endunless
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Accesos (auditoría): todos, en un recuadro con scroll; cada IP distinta con su color. --}}
            <div class="mon-col">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <div class="mon-title mb-0"><i class="ti ti-login"></i> Accesos <span class="mon-chip">{{ $block['logins']->count() }}</span></div>
                    @if($range[0] || $range[1])
                        <span class="mon-chip" title="Rango de fechas aplicado"><i class="ti ti-calendar"></i> {{ $range[0] ? 'del '.$range[0]->format('d/m/Y') : 'hasta' }} {{ $range[1] ? ($range[0] ? 'al ' : '').$range[1]->format('d/m/Y') : 'en adelante' }}</span>
                    @endif
                    @if($block['ips']->isNotEmpty())
                        <span class="mon-sub">· {{ $block['ips']->count() }} IP(s) distinta(s):</span>
                        @foreach($block['ips'] as $ip => $times)
                            <span class="mon-ip" style="{{ \App\Services\IpColor::style($ipPalette[$ip] ?? null) }}" title="{{ $times }} acceso(s) desde esta IP">{{ $ip }} <b>×{{ $times }}</b></span>
                        @endforeach
                    @endif
                </div>
                @if($block['logins']->isEmpty())
                    <div class="mon-empty">{{ ($range[0] || $range[1]) ? 'Sin accesos en ese rango.' : 'Sin accesos registrados.' }}</div>
                @else
                    <div class="mon-logins">
                        @foreach($block['logins'] as $login)
                            <div class="mon-login">
                                <span class="mon-date" title="{{ $login['method'] }}">{{ $login['date']->format('d/m/Y H:i') }}</span>
                                <span class="mon-agent">{{ $login['agent'] }}</span>
                                <span class="mon-ip" style="{{ \App\Services\IpColor::style($login['ip'] ? ($ipPalette[$login['ip']] ?? null) : null) }}">{{ $login['ip'] ?: '—' }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="mon-user"><div class="mon-col mon-empty">No hay usuarios que coincidan.</div></div>
    @endforelse
</div>

@push('datepicker_js')
    <script>
        $(function () {
            var wire = @this;
            initLivewireDatepicker([
                ['#monitoring_date_from', 'dateFrom'],
                ['#monitoring_date_to',   'dateTo'],
            ], wire);
        });
    </script>
@endpush
