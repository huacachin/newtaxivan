<li class="header-notification">
    <div class="flex-shrink-0 app-dropdown">
        <button type="button" class="d-block head-icon position-relative"
                data-bs-toggle="dropdown"
                data-bs-auto-close="outside" aria-expanded="false"
                aria-label="Notificaciones">

            <i class="ti ti-bell"></i>

            {{-- Punto animado solo si hay alertas --}}
            @if($count > 0)
                <span class="position-absolute translate-middle p-1 bg-danger border border-light rounded-circle animate__animated animate__fadeIn animate__infinite animate__slower"></span>
            @endif
        </button>

        <div class="dropdown-menu dropdown-menu-end notif-panel">
            <div class="notif-panel__shell">
                <div class="notif-panel__head">
                    <div>
                        <h6 class="notif-panel__title">Vencimientos próximos</h6>
                        <div class="notif-panel__subtitle">Vehículos · Propietarios · Conductores</div>
                    </div>
                    @if($count > 0)
                        <span class="notif-panel__chip">
                            <i class="ti ti-bell"></i>
                            {{ $count }}
                        </span>
                    @else
                        <i class="ti ti-bell text-white f-s-20"></i>
                    @endif
                </div>

                <div class="notif-panel__body">
                    @forelse($alerts as $n)
                        @php
                            $rowClass = ($n['color'] ?? 'danger') === 'warning'
                                ? 'notif-panel__row--warning'
                                : 'notif-panel__row--danger';
                            $status = $n['status'] ?? 'upcoming';
                            $statusLabel = match($status) {
                                'expired'  => 'Vencido',
                                'today'    => 'Hoy',
                                default    => 'Por vencer',
                            };
                            $countClass = $status === 'today' ? 'notif-panel__count notif-panel__count--today' : 'notif-panel__count';

                            $kind = $n['kind'] ?? 'vehicle';
                            $routeName = match($kind) {
                                'owner'  => 'settings.owners.expiration',
                                'driver' => 'settings.drivers.expiration',
                                default  => 'settings.vehicles.expiration',
                            };
                            $href = route($routeName, ['id' => $n['id'], 'field' => $n['field']]);
                        @endphp
                        <a href="{{ $href }}"
                           class="notif-panel__row notif-panel__row--{{ $kind }} {{ $rowClass }}"
                           title="{{ ($n['kind_label'] ?? '') . ' · ' . ($n['message'] ?? '') }}">
                            <span class="notif-panel__rail"></span>
                            <span class="notif-panel__abbr">{{ $n['abbr'] }}</span>
                            <div class="notif-panel__main">
                                <div>
                                    <span class="notif-panel__plate">{{ $n['subject'] ?? ($n['plate'] ?? '—') }}</span>
                                    <span class="notif-panel__label">{{ $n['label'] }}</span>
                                </div>
                                <div class="notif-panel__meta">
                                    <span>{{ \Carbon\Carbon::parse($n['due_date'])->format('d/m/Y') }}</span>
                                    <span class="notif-panel__dot"></span>
                                    <span class="notif-panel__status">{{ $statusLabel }}</span>
                                </div>
                            </div>
                            <div class="{{ $countClass }}">
                                @if($status === 'today')
                                    <div class="notif-panel__count-num">HOY</div>
                                    <div class="notif-panel__count-unit">vence</div>
                                @else
                                    <div class="notif-panel__count-num">{{ $n['days'] }}</div>
                                    <div class="notif-panel__count-unit">
                                        {{ $status === 'expired' ? 'días atrás' : 'días' }}
                                    </div>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="notif-panel__empty">
                            <span class="notif-panel__empty-icon">
                                <i class="ti ti-check"></i>
                            </span>
                            <div class="notif-panel__empty-title">Todo al día</div>
                            <p class="notif-panel__empty-text">
                                No hay vencimientos próximos en los siguientes 10 días.
                            </p>
                        </div>
                    @endforelse
                </div>

            </div>
        </div>
    </div>
</li>
