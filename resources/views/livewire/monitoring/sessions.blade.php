<div class="container-fluid monitoring-sessions" x-data="{
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
        .monitoring-sessions .mini-table { font-size: 12px; margin-bottom: 0; }
        .monitoring-sessions .dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 4px; vertical-align: middle; }
        .monitoring-sessions .dot-active { background: #28a745; box-shadow: 0 0 0 3px rgba(40,167,69,.2); }
        .monitoring-sessions .dot-idle { background: #adb5bd; }
        .monitoring-sessions .user-header { background: #fcfcfa; }
        .monitoring-sessions .mini-table .nowrap { white-space: nowrap; }
        @media (max-width: 575.98px) {
            .monitoring-sessions .stack-table thead { display: none; }
            .monitoring-sessions .stack-table, .monitoring-sessions .stack-table tbody, .monitoring-sessions .stack-table tr, .monitoring-sessions .stack-table td { display: block; width: 100%; }
            .monitoring-sessions .stack-table tr { border: 1px solid #dee2e6; border-radius: 6px; margin-bottom: 8px; padding: 4px 8px; background: #fff; }
            .monitoring-sessions .stack-table td { border: 0 !important; padding: 2px 0 !important; text-align: left !important; white-space: normal !important; }
            .monitoring-sessions .stack-table td[data-label]::before { content: attr(data-label) ": "; font-weight: 600; color: #6c757d; }
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

    {{-- Resumen y filtros --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge bg-success f-s-12"><i class="ti ti-users"></i> {{ $usersWithSession }} usuario(s) con sesión abierta</span>
                <span class="badge bg-dark f-s-12"><i class="ti ti-devices"></i> {{ $totalSessions }} sesión(es) en total</span>
                <span class="text-muted small"><span class="dot dot-active"></span> con actividad en los últimos {{ \App\Services\ActiveSessions::ACTIVE_MINUTES }} min</span>
                <div class="ms-md-auto d-flex flex-wrap align-items-center gap-2">
                    <div class="form-check form-switch mb-0 small">
                        <input class="form-check-input" type="checkbox" id="onlyWithSession" wire:model.live="onlyWithSession">
                        <label class="form-check-label" for="onlyWithSession">Solo con sesión abierta</label>
                    </div>
                    <input type="search" class="form-control form-control-sm" style="max-width:220px;" placeholder="Buscar usuario…" wire:model.live.debounce.400ms="search">
                </div>
            </div>
        </div>
    </div>

    @forelse($blocks as $block)
        @php
            $user = $block['user'];
            $count = $block['sessions']->count();
            $others = $block['sessions']->where('current', false)->count();
        @endphp
        <div class="card mb-3" wire:key="user-{{ $user->id }}">
            {{-- Título: usuario --}}
            <div class="card-header py-2 user-header">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <i class="ti ti-user-circle f-s-22 {{ $count ? 'text-success' : 'text-muted' }}"></i>
                    <div class="flex-grow-1">
                        <span class="fw-bold f-s-15">{{ $user->username }}</span>
                        <span class="text-muted">· {{ $user->name }}</span>
                        @if($block['role'])<span class="badge bg-secondary ms-1">{{ __('roles.' . $block['role']) }}</span>@endif
                        @if($user->status !== 'active')<span class="badge bg-warning text-dark ms-1">Desactivado</span>@endif
                        @if($block['failed'] > 0)
                            <span class="badge bg-danger ms-1" title="Intentos de acceso fallidos en los últimos {{ \App\Livewire\Monitoring\Sessions::FAILED_LOGIN_DAYS }} días"><i class="ti ti-alert-triangle"></i> {{ $block['failed'] }} fallido(s)</span>
                        @endif
                    </div>
                    <span class="badge {{ $count ? 'bg-success' : 'bg-secondary' }}">{{ $count }} sesión(es) abierta(s)</span>
                    @if($others > 0)
                        <button type="button" class="btn btn-sm btn-outline-danger py-0"
                                x-on:click="confirmClose('Cerrar sesiones', @js(($others === 1 ? '¿Cerrar la sesión' : "¿Cerrar las {$others} sesiones") . ' de <b>' . e($user->username) . '</b>?<br><small>Tendrá que volver a iniciar sesión.</small>'), () => $wire.closeAll({{ $user->id }}))">
                            <i class="ti ti-logout"></i> Cerrar {{ $others === 1 ? 'su sesión' : 'todas sus sesiones' }}
                        </button>
                    @endif
                </div>
            </div>
            <div class="card-body py-2">
                <div class="row g-3">
                    {{-- Sesiones abiertas --}}
                    <div class="col-12 col-xl-7">
                        <div class="small fw-semibold mb-1"><i class="ti ti-devices"></i> Sesiones abiertas</div>
                        @if($count === 0)
                            <div class="text-muted small">Sin sesiones abiertas.</div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mini-table stack-table">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>Dispositivo</th>
                                            <th>Navegador</th>
                                            <th>IP</th>
                                            <th>Última actividad</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($block['sessions'] as $session)
                                        <tr wire:key="session-{{ $session['key'] }}" @class(['table-success' => $session['current']])>
                                            <td data-label="Dispositivo">
                                                <span class="dot {{ $session['active'] ? 'dot-active' : 'dot-idle' }}" title="{{ $session['active'] ? 'Con actividad ahora' : 'Sin actividad reciente' }}"></span>
                                                <i class="ti {{ $session['agent']['device'] === 'Celular' ? 'ti-device-mobile' : ($session['agent']['device'] === 'Tablet' ? 'ti-device-tablet' : 'ti-device-desktop') }}"></i>
                                                {{ $session['agent']['device'] }} · {{ $session['agent']['os'] }}
                                            </td>
                                            <td data-label="Navegador">{{ $session['agent']['browser'] }}</td>
                                            <td data-label="IP" class="font-monospace nowrap">{{ $session['ip'] ?: '—' }}</td>
                                            <td data-label="Última actividad" style="white-space:nowrap;" title="{{ $session['last_activity']->format('d/m/Y H:i:s') }}">{{ $session['last_activity']->diffForHumans() }}</td>
                                            <td class="text-end nowrap">
                                                @if($session['current'])
                                                    <span class="badge bg-success">Esta sesión</span>
                                                @else
                                                    <button type="button" class="btn btn-sm btn-outline-danger py-0" style="font-size:11px;"
                                                            x-on:click="confirmClose('Cerrar sesión', @js('¿Cerrar esta sesión de <b>' . e($user->username) . '</b>?<br><small>' . e($session['agent']['text']) . ' · IP ' . e($session['ip'] ?? '—') . '</small>'), () => $wire.closeSession('{{ $session['key'] }}'))">
                                                        <i class="ti ti-logout"></i> Cerrar
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    {{-- Últimos accesos (auditoría) --}}
                    <div class="col-12 col-xl-5">
                        <div class="small fw-semibold mb-1"><i class="ti ti-login"></i> Últimos accesos</div>
                        @if($block['logins']->isEmpty())
                            <div class="text-muted small">Sin accesos registrados.</div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mini-table stack-table">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Dispositivo</th>
                                            <th>IP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($block['logins'] as $login)
                                        <tr>
                                            <td data-label="Fecha" style="white-space:nowrap;" title="{{ $login['method'] }}">{{ $login['date']->format('d/m/Y H:i') }}</td>
                                            <td data-label="Dispositivo">{{ $login['agent'] }}</td>
                                            <td data-label="IP" class="font-monospace nowrap">{{ $login['ip'] ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="card"><div class="card-body text-muted small">No hay usuarios que coincidan.</div></div>
    @endforelse
</div>
