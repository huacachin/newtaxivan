<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6"><h4 class="main-title title-modules">MI PERFIL</h4></div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-user-circle f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Cuenta</span></a>
                </li>
                <li class="d-flex active"><a class="f-s-14">Mi perfil</a></li>
            </ul>
        </div>
    </div>

    {{-- Mis datos (solo lectura: los cambia quien administra usuarios) --}}
    <div class="card">
        <div class="card-header py-2 d-flex align-items-center gap-2">
            <h6 class="mb-0"><i class="ti ti-id"></i> Mis datos</h6>
            <span class="badge bg-secondary" title="Para corregir algún dato, pídeselo a quien administra los usuarios"><i class="ti ti-lock"></i> Solo lectura</span>
        </div>
        <div class="card-body">
            <div class="perm-grid form-two-cols">
                @foreach([
                    'Nombre' => $user->name,
                    'Usuario' => $user->username,
                    'Correo' => $user->email,
                    'Rol' => $role ? __('roles.' . $role) : null,
                    'Teléfono' => $user->phone,
                    'Documento' => trim(strtoupper((string) $user->document_type) . ' ' . $user->document_number),
                    'Sede principal' => $user->headquarter?->name,
                    'Sedes asignadas' => $user->headquarters->sortBy('name')->pluck('name')->implode(', '),
                ] as $label => $value)
                    <div class="perm-row">
                        <div class="perm-col-title">{{ $label }}</div>
                        <div class="perm-col-controls">
                            <input type="text" class="form-control form-control-sm" value="{{ $value ?: '—' }}" readonly disabled>
                        </div>
                    </div>
                @endforeach
            </div>
            <small class="text-muted d-block mt-2"><i class="ti ti-info-circle"></i> Estos datos solo los modifica quien administra los usuarios.</small>
        </div>
    </div>

    {{-- Sesiones activas --}}
    <div class="card">
        <div class="card-header py-2"><h6 class="mb-0"><i class="ti ti-devices"></i> Sesiones activas</h6></div>
        <div class="card-body">
            @if(! $available)
                <div class="alert alert-warning py-2 mb-0 small">
                    <i class="ti ti-alert-triangle"></i> Este servidor guarda las sesiones en archivos: la lista de sesiones
                    necesita que se guarden en la base de datos (<code>SESSION_DRIVER=database</code>).
                </div>
            @else
                <p class="small text-muted mb-2">Equipos y navegadores donde tu cuenta está abierta ahora. Si ves alguno que no reconoces, cierra las otras sesiones y pide que te cambien la contraseña.</p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-2" style="font-size:12px;">
                        <thead class="table-secondary">
                            <tr>
                                <th>Dispositivo</th>
                                <th>Navegador</th>
                                <th>Dirección IP</th>
                                <th>Última actividad</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($sessions as $session)
                            <tr wire:key="session-{{ $session['key'] }}" @class(['table-success' => $session['current']])>
                                <td>
                                    <i class="ti {{ $session['agent']['device'] === 'Celular' ? 'ti-device-mobile' : ($session['agent']['device'] === 'Tablet' ? 'ti-device-tablet' : 'ti-device-desktop') }} f-s-16"></i>
                                    {{ $session['agent']['device'] }} · {{ $session['agent']['os'] }}
                                </td>
                                <td>{{ $session['agent']['browser'] }}</td>
                                <td class="font-monospace">{{ $session['ip'] ?: '—' }}</td>
                                <td style="white-space:nowrap;" title="{{ $session['last_activity']->format('d/m/Y H:i:s') }}">{{ $session['last_activity']->diffForHumans() }}</td>
                                <td>@if($session['current'])<span class="badge bg-success">Esta sesión</span>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">No hay sesiones registradas.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{-- Confirmación en dos pasos: el botón abre el cuadro con la contraseña. La contraseña va como
                     parámetro de la acción, nunca como estado; se envía con Alpine (x-on + $wire). --}}
                @php $others = $sessions->where('current', false)->count(); @endphp
                <div x-data="{ confirming: {{ $errors->has('sessionsPassword') ? 'true' : 'false' }}, visible: false }"
                     x-on:profile-sessions-closed.window="confirming = false; visible = false; $refs.password.value = ''">
                    <button type="button" class="btn btn-outline-danger btn-sm" x-show="!confirming"
                            x-on:click="confirming = true; $nextTick(() => $refs.password.focus())"
                            @if($others === 0) disabled title="No hay otras sesiones abiertas" @endif>
                        <i class="ti ti-logout"></i> Cerrar las otras sesiones
                    </button>
                    <div class="border rounded p-2" style="max-width:520px; background:#fcfcfa;" x-show="confirming" x-cloak>
                        <label for="close-sessions-password" class="form-label small fw-semibold mb-1">
                            <i class="ti ti-shield-lock"></i> Confirma con tu contraseña para cerrar {{ $others === 1 ? 'la otra sesión' : "las otras {$others} sesiones" }}
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="ti ti-lock"></i></span>
                            <input :type="visible ? 'text' : 'password'" id="close-sessions-password" class="form-control @error('sessionsPassword') is-invalid @enderror"
                                   x-ref="password" autocomplete="current-password" placeholder="Tu contraseña"
                                   x-on:keydown.enter="$wire.closeOtherSessions($event.target.value)">
                            <button type="button" class="btn btn-outline-secondary" x-on:click="visible = !visible" :title="visible ? 'Ocultar' : 'Mostrar'">
                                <i class="ti" :class="visible ? 'ti-eye-off' : 'ti-eye'"></i>
                            </button>
                        </div>
                        @error('sessionsPassword') <div class="text-danger small mt-1"><i class="ti ti-alert-circle"></i> {{ $message }}</div> @enderror
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <button type="button" class="btn btn-danger btn-sm" x-on:click="$wire.closeOtherSessions($refs.password.value)"
                                    wire:loading.attr="disabled" wire:target="closeOtherSessions">
                                <i class="ti ti-logout"></i>
                                <span wire:loading.remove wire:target="closeOtherSessions">Cerrar las otras sesiones</span>
                                <span wire:loading wire:target="closeOtherSessions">Cerrando…</span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" x-on:click="confirming = false; visible = false; $refs.password.value = ''">
                                <i class="ti ti-x"></i> Cancelar
                            </button>
                        </div>
                        <small class="text-muted d-block mt-1">Esta sesión seguirá abierta; las demás tendrán que volver a iniciar sesión.</small>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
