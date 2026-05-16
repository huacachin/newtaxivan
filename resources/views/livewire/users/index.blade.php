{{-- resources/views/livewire/users/index.blade.php --}}
<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">LISTADO GENERAL DE USUARIOS</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Configuración</span></a>
                </li>
                <li class="d-flex active"><a href="#" class="f-s-14">Usuarios</a></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <!-- Tabla -->
        <div class="col-xl-12">
            <div class="card shadow-sm">


                <div class="card-body pb-2">


                       <div class="row my-2">
                           <div class="col-12">
                               <div class="d-flex flex-wrap flex-md-nowrap align-items-end gap-2 py-1">

                                   <!-- Input: full width en mobile, ~260px en desktop -->
                                   <div class="flex-grow-1 flex-md-grow-0" style="min-width:180px; max-width:260px;">
                                       <input type="search"
                                              class="form-control form-control-sm"
                                              placeholder="Buscar..."
                                              aria-label="Buscar"
                                              wire:model="search">
                                   </div>

                                   <button class="btn btn-sm btn-dark flex-shrink-0" wire:click="$refresh">
                                       <i class="ti ti-search f-s-12"></i>
                                   </button>

                                   <!-- Botón a la derecha -->
                                   @if(auth()->user()->isDirector())
                                   <a class="btn btn-sm btn-primary flex-shrink-0"
                                      href="{{ route('settings.users.create') }}" target="_blank">
                                       <i class="ti ti-square-plus f-s-12"></i> Nuevo
                                   </a>
                                   @endif

                                   <button class="btn btn-sm btn-export flex-shrink-0"
                                           wire:click="export">
                                       <i class="ti ti-file-analytics f-s-12"></i> Exportar
                                   </button>

                               </div>
                           </div>
                       </div>

                    {{-- ════════ MOBILE: cards de usuarios ════════ --}}
                    <div class="d-md-none list-cards">
                        @if($users->count() > 0)
                            @foreach($users as $user)
                                @php
                                    $roleSlug = optional($user->roles->first())->name;
                                    $roleLabel = $roleSlug ? __('roles.' . $roleSlug, [], 'es') : '—';
                                    $isAdminRole = in_array($roleSlug, ['director','gerente']);
                                    $hqText = $isAdminRole
                                        ? '—'
                                        : ($user->headquarters->pluck('name')->implode(', ') ?: '—');
                                @endphp
                                <article class="list-card">
                                    <header class="list-card__head">
                                        <div class="list-card__title-wrap">
                                            <span class="list-card__index">{{ $loop->iteration }}</span>
                                            <span class="list-card__title">{{ $user->name }}</span>
                                        </div>
                                        @if(auth()->user()->canManageUser($user))
                                            <a href="{{ route('settings.users.edit', $user->id) }}" class="list-card__edit" aria-label="Editar">
                                                <i class="ti ti-edit"></i>
                                            </a>
                                        @endif
                                    </header>

                                    <div class="list-card__chips">
                                        <span class="list-chip list-chip--info">{{ $user->username }}</span>
                                        @if($roleLabel !== '—')
                                            <span class="list-chip">{{ $roleLabel }}</span>
                                        @endif
                                        <span class="list-chip list-chip--muted">{{ $user->permissions->count() }} permisos</span>
                                    </div>

                                    <ul class="list-card__meta">
                                        @if($user->phone)
                                            <li>
                                                <span class="list-card__meta-lbl"><i class="ti ti-phone"></i> Teléfono</span>
                                                <span class="list-card__meta-val">{{ $user->phone }}</span>
                                            </li>
                                        @endif
                                        <li>
                                            <span class="list-card__meta-lbl"><i class="ti ti-building"></i> Sede</span>
                                            <span class="list-card__meta-val">{{ $hqText }}</span>
                                        </li>
                                        @if(auth()->user()->canManageUser($user))
                                            <li class="d-flex justify-content-end gap-2 pt-1">
                                                <a class="btn btn-sm btn-outline-dark"
                                                   href="{{ route('settings.users.perms', $user->id) }}" target="_blank"
                                                   title="Permisos">
                                                    <i class="ti ti-shield-lock"></i> Permisos
                                                </a>
                                                @if(!$user->hasRole('director'))
                                                    <button class="btn btn-sm btn-outline-danger"
                                                            wire:click="questionDelete({{ $user->id }}, '{{ $roleLabel }}', '{{ $user->name }}')"
                                                            title="Desactivar usuario">
                                                        <i class="ti ti-trash"></i>
                                                    </button>
                                                @endif
                                            </li>
                                        @endif
                                    </ul>
                                </article>
                            @endforeach
                        @else
                            <div class="list-cards__empty">Sin usuarios</div>
                        @endif
                    </div>

                    {{-- ════════ DESKTOP: tabla original ════════ --}}
                    <div class="table-responsive tableFixHead d-none d-md-block">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>Nº</th>
                                <th>Nombres</th>
                                <th>Usuario</th>
                                <th>Teléfono</th>
                                <th>Sede</th>
                                <th>Rol</th>
                                <th>Permisos</th>
                                <th></th>
                            </tr>
                            </thead>

                            <tbody>
                            @if($users->count() > 0)
                                @foreach($users as $user)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $user->name }}</td>
                                        <td>{{ $user->username }}</td>
                                        <td>{{ $user->phone ?: '—' }}</td>
                                        <td>
                                            @if(in_array(optional($user->roles->first())->name, ['director','gerente']))
                                                —
                                            @else
                                                {{ $user->headquarters->pluck('name')->implode(', ') ?: '—' }}
                                                @if($user->headquarter)
                                                    <br><small class="text-muted">Primaria: {{ $user->headquarter->name }}</small>
                                                @endif
                                            @endif
                                        </td>
                                        <td>{{ ($r = optional($user->roles->first())->name) ? __('roles.' . $r, [], 'es') : '—' }}</td>
                                        <td>
                                            <span class="badge bg-dark">
                                                {{ $user->permissions->count() }} permisos
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            @if(auth()->user()->canManageUser($user))
                                                {{-- Editar datos --}}
                                                <a class="btn btn-sm btn-outline-success me-1"
                                                   title="Editar datos"
                                                   href="{{ route('settings.users.edit', $user->id) }}">
                                                    <i class="ti ti-edit"></i>
                                                </a>

                                                {{-- Permisos --}}
                                                <a class="btn btn-sm btn-outline-dark"
                                                   title="Permisos"
                                                   href="{{ route('settings.users.perms', $user->id) }}" target="_blank">
                                                    <i class="ti ti-shield-lock"></i>
                                                </a>

                                                @if(!$user->hasRole('director'))
                                                <button class="btn btn-sm btn-outline-danger ms-1"
                                                        title="Desactivar usuario"
                                                        wire:click="questionDelete({{ $user->id }}, '{{ __('roles.' . ($user->roles->first()->name ?? '')) }}', '{{ $user->name }}')">
                                                    <i class="ti ti-trash"></i>
                                                </button>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="8" class="py-4 text-muted">No se encontraron resultados</td>
                                </tr>
                            @endif
                            </tbody>

                            <tfoot class="bg-primary">
                            <tr>
                                <td></td>
                                <td>TOTAL USUARIOS</td>
                                <td colspan="4"></td>
                                <td>{{ number_format($users->count()) }}</td>
                                <td></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
