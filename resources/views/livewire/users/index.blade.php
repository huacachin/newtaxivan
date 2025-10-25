{{-- resources/views/livewire/users/index.blade.php --}}
@push('styles')
    <style>
        /* ====== Estilos base tabla / botones ====== */
        table { border-collapse: collapse; width: 100%; }
        th,td{
            padding: 1px !important;
            font-size: 10px !important;
            text-align: center !important;
            vertical-align: middle;
        }
        .btn, input,select { font-size: 10px !important; }
        .screen-overlay {
            position: fixed; inset: 0;
            display: none; align-items: center; justify-content: center;
            background: rgba(0,0,0,.35); backdrop-filter: blur(2px);
            z-index: 2000; pointer-events: all;
        }

        /* ====== Utilidades compactas reutilizables (Agregar/Editar/Permisos) ====== */
        .perm-grid { display: flex; flex-direction: column; gap: 6px; }
        .perm-row {
            display: grid;
            grid-template-columns: 180px 1fr; /* título fijo | controles flex */
            gap: 8px;
            align-items: center;
            padding: 6px 8px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid #eee;
            margin-bottom: 6px;
        }
        .perm-col-title { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .perm-col-controls { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .perm-chips { display: flex; gap: 6px; flex-wrap: wrap; }

        .chip-check, .chip-radio, .chip-hq {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 8px;
            border: 1px solid #e5e7eb; border-radius: 999px;
            background: #fff; cursor: pointer; user-select: none;
        }
        .chip-check input, .chip-radio input, .chip-hq input { margin: 0; width: 14px; height: 14px; }
        .chip-check span, .chip-radio span, .chip-hq span { line-height: 1; }

        /* Sucursal: radio "Primaria" embebido */
        .chip-hq .hq-primary {
            display: inline-flex; align-items: center; gap: 4px;
            padding-left: 6px; margin-left: 6px;
            border-left: 1px dashed #e5e7eb;
            font-size: 11px;
        }
        .chip-hq.is-default { border-color: #60a5fa; background: #f0f9ff; }
        .chip-hq.is-selected { border-color: #a7f3d0; background: #ecfdf5; }

        /* ====== Modal Permisos: detalles visuales ====== */
        #modalPerms .modal-content { font-size: 12px; }
        #modalPerms .modal-header, #modalPerms .modal-footer { background: #fafafa; }
        #modalPerms .btn, #modalPerms input, #modalPerms label, #modalPerms small { font-size: 12px !important; }
        #modalPerms .form-check-input { width: 14px; height: 14px; margin-top: 0; }
        .action-icon { display: inline-flex; align-items: center; color: #6b7280; }
        .action-icon:hover { color: #111827; }

        /* === 2 columnas en móvil para AGREGAR/EDITAR === */
        #modalAddUser .form-two-cols,
        #modalEditUser .form-two-cols {
            display: grid;
            grid-template-columns: 1fr 1fr;   /* móvil: 2 columnas */
            gap: 6px;
        }
        #modalAddUser .form-two-cols .perm-row,
        #modalEditUser .form-two-cols .perm-row { margin: 0; }
        #modalAddUser .form-two-cols .span-2,
        #modalEditUser .form-two-cols .span-2 { grid-column: 1 / -1; }

        /* Inputs compactos en modales Add/Edit */
        #modalAddUser .form-control, #modalAddUser .form-select,
        #modalEditUser .form-control, #modalEditUser .form-select {
            padding: 4px 8px;
            min-height: 30px;
            font-size: 12px;
        }

        /* Responsive interno de cada fila */
        @media (max-width: 576px) {
            .perm-row { grid-template-columns: 1fr; padding: 6px; }
            .perm-col-title { margin-bottom: 2px; }
            .chip-check, .chip-radio, .chip-hq { padding: 3px 6px; }
        }

        /* Si quieres 3 columnas en desktop (opcional):
        @media (min-width: 992px) {
            #modalAddUser .form-two-cols,
            #modalEditUser .form-two-cols {
                grid-template-columns: 1fr 1fr 1fr;
            }
        }
        */
    </style>
@endpush

<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Usuarios</h4>
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
                <div class="card-header">
                    <h5>LISTADO DE USUARIOS</h5>
                    <div class="row g-3 align-items-end mt-2">
                        <div class="col-md-10 col-6">
                            <input type="search" class="form-control" placeholder="Buscar..."
                                   aria-label="Buscar" wire:model.live="search">
                        </div>
                        <div class="col-md-2 col-6 d-flex justify-content-md-end">
                            <button class="btn btn-sm btn-primary w-100" wire:click="openAddModal">
                                <i class="ti ti-square-plus f-s-12"></i> Nuevo
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body pb-2">
                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>Id</th>
                                <th>Nombres</th>
                                <th>Usuario</th>
                                <th>Teléfono</th>
                                <th>Sede</th>
                                <th>Rol</th>
                                <th>Permisos</th>
                                <th>Acción</th>
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
                                            {{ $user->headquarters->pluck('name')->implode(', ') ?: '—' }}
                                            @if($user->headquarter)
                                                <br><small class="text-muted">Primaria: {{ $user->headquarter->name }}</small>
                                            @endif
                                        </td>
                                        <td>{{ optional($user->roles->first())->name ?? '—' }}</td>
                                        <td>
                                            <span class="badge bg-dark">
                                                {{ $user->permissions->count() }} permisos
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            {{-- Editar datos --}}
                                            <button class="btn btn-sm btn-outline-success me-1"
                                                    title="Editar datos"
                                                    wire:click="openEditModal({{ $user->id }})">
                                                <i class="ti ti-edit"></i>
                                            </button>

                                            {{-- Rol & Permisos (modal nuevo) --}}
                                            <button class="btn btn-sm btn-outline-dark"
                                                    title="Rol & Permisos"
                                                    wire:click="openPermsModal({{ $user->id }})">
                                                <i class="ti ti-shield-lock"></i>
                                            </button>
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

    {{-- MODAL: AGREGAR (FULLSCREEN, ULTRA-COMPACT + 2 columnas móvil) --}}
    <div class="modal fade" id="modalAddUser" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0">Agregar Usuario</h6>
                    <button type="button" class="btn-close m-0" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-2">
                    <div class="perm-grid form-two-cols">
                        {{-- Nombre --}}
                        <div class="perm-row">
                            <div class="perm-col-title">Nombre</div>
                            <div class="perm-col-controls">
                                <input id="name" type="text" class="form-control" placeholder="Ingresar nombre" wire:model.live="name">
                                @error('name') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Usuario --}}
                        <div class="perm-row">
                            <div class="perm-col-title">Usuario</div>
                            <div class="perm-col-controls">
                                <input id="username" type="text" class="form-control" placeholder="Ingresar usuario" wire:model="username">
                                @error('username') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Contraseña --}}
                        <div class="perm-row">
                            <div class="perm-col-title">Contraseña</div>
                            <div class="perm-col-controls">
                                <input id="pwd" type="text" class="form-control" placeholder="Ingresar contraseña" wire:model.live="pwd">
                                @error('pwd') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Email --}}
                        <div class="perm-row">
                            <div class="perm-col-title">Email</div>
                            <div class="perm-col-controls">
                                <input id="email" type="email" class="form-control" placeholder="Ingresar email" wire:model="email">
                                @error('email') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Tipo Documento --}}
                        <div class="perm-row">
                            <div class="perm-col-title">Tipo Documento</div>
                            <div class="perm-col-controls">
                                <select id="document_type" class="form-select" wire:model="document_type">
                                    <option value="dni">DNI</option>
                                    <option value="ruc">RUC</option>
                                    <option value="ce">CE</option>
                                </select>
                                @error('document_type') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- N° Documento --}}
                        <div class="perm-row">
                            <div class="perm-col-title">N° Documento</div>
                            <div class="perm-col-controls">
                                <input id="document_number" type="text" class="form-control" placeholder="Ingresar número" wire:model="document_number">
                                @error('document_number') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Teléfono --}}
                        <div class="perm-row">
                            <div class="perm-col-title">Teléfono</div>
                            <div class="perm-col-controls">
                                <input id="phone" type="text" class="form-control" placeholder="Ingresar teléfono" wire:model="phone">
                                @error('phone') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Sucursales (chips + primaria integrada) --}}
                        <div class="perm-row span-2">
                            <div class="perm-col-title">Sucursales</div>
                            <div class="perm-col-controls">
                                <div class="perm-chips">
                                    @foreach($headquartes as $h)
                                        @php
                                            $isSelected = in_array($h->id, (array)$selectedHeadquarters, true);
                                            $isDefault  = (int)$defaultHeadquarter === (int)$h->id;
                                        @endphp
                                        <label class="chip-hq {{ $isSelected ? 'is-selected' : '' }} {{ $isDefault ? 'is-default' : '' }}">
                                            <input class="form-check-input" type="checkbox"
                                                   value="{{ $h->id }}" wire:model="selectedHeadquarters">
                                            <span>{{ $h->name }}</span>
                                            <span class="hq-primary" title="Marcar como sede primaria">
                                                <input class="form-check-input" type="radio"
                                                       name="default_hq_add"
                                                       value="{{ $h->id }}" wire:model="defaultHeadquarter">
                                                <small>Primaria</small>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('selectedHeadquarters') <span class="text-danger d-block small mt-1">{{ $message }}</span> @enderror
                                @error('defaultHeadquarter')  <span class="text-danger d-block small mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Rol (chips) --}}
                        <div class="perm-row span-2">
                            <div class="perm-col-title">Rol</div>
                            <div class="perm-col-controls">
                                <div class="perm-chips">
                                    @forelse($roles as $r)
                                        <label class="chip-radio" title="{{ $r->name }}">
                                            <input type="radio" class="form-check-input"
                                                   name="role_single_add" value="{{ $r->id }}" wire:model="selectedRoleId">
                                            <span>{{ $r->name }}</span>
                                        </label>
                                    @empty
                                        <span class="text-warning small">No hay roles definidos.</span>
                                    @endforelse
                                </div>
                                @error('selectedRoleId') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>

                    </div> {{-- /perm-grid form-two-cols --}}
                </div>

                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="save">Agregar</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL: EDITAR (FULLSCREEN, ULTRA-COMPACT + 2 columnas móvil) --}}
    <div class="modal fade" id="modalEditUser" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0">Editar Usuario</h6>
                    <button type="button" class="btn-close m-0" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-2">
                    <div class="perm-grid form-two-cols">
                        {{-- Nombre --}}
                        <div class="perm-row">
                            <div class="perm-col-title">Nombre</div>
                            <div class="perm-col-controls">
                                <input id="name_e" type="text" class="form-control" placeholder="Ingresar nombre" wire:model.live="name">
                                @error('name') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Usuario --}}
                        <div class="perm-row">
                            <div class="perm-col-title">Usuario</div>
                            <div class="perm-col-controls">
                                <input id="username_e" type="text" class="form-control" placeholder="Ingresar usuario" wire:model="username">
                                @error('username') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Contraseña (opcional) --}}
                        <div class="perm-row">
                            <div class="perm-col-title">Contraseña</div>
                            <div class="perm-col-controls">
                                <input id="pwd_e" type="text" class="form-control" placeholder="Nueva contraseña (opcional)" wire:model.live="pwd">
                                @error('pwd') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Email --}}
                        <div class="perm-row">
                            <div class="perm-col-title">Email</div>
                            <div class="perm-col-controls">
                                <input id="email_e" type="email" class="form-control" placeholder="Ingresar email" wire:model="email">
                                @error('email') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Tipo Documento --}}
                        <div class="perm-row">
                            <div class="perm-col-title">Tipo Documento</div>
                            <div class="perm-col-controls">
                                <select id="document_type_e" class="form-select" wire:model="document_type">
                                    <option value="dni">DNI</option>
                                    <option value="ruc">RUC</option>
                                    <option value="ce">CE</option>
                                </select>
                                @error('document_type') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- N° Documento --}}
                        <div class="perm-row">
                            <div class="perm-col-title">N° Documento</div>
                            <div class="perm-col-controls">
                                <input id="document_number_e" type="text" class="form-control" placeholder="Ingresar número" wire:model="document_number">
                                @error('document_number') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Teléfono --}}
                        <div class="perm-row">
                            <div class="perm-col-title">Teléfono</div>
                            <div class="perm-col-controls">
                                <input id="phone_e" type="text" class="form-control" placeholder="Ingresar teléfono" wire:model="phone">
                                @error('phone') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Sucursales (chips + primaria integrada) --}}
                        <div class="perm-row span-2">
                            <div class="perm-col-title">Sucursales</div>
                            <div class="perm-col-controls">
                                <div class="perm-chips">
                                    @foreach($headquartes as $h)
                                        @php
                                            $isSelected = in_array($h->id, (array)$selectedHeadquarters, true);
                                            $isDefault  = (int)$defaultHeadquarter === (int)$h->id;
                                        @endphp
                                        <label class="chip-hq {{ $isSelected ? 'is-selected' : '' }} {{ $isDefault ? 'is-default' : '' }}">
                                            <input class="form-check-input" type="checkbox"
                                                   value="{{ $h->id }}" wire:model="selectedHeadquarters">
                                            <span>{{ $h->name }}</span>
                                            <span class="hq-primary" title="Marcar como sede primaria">
                                                <input class="form-check-input" type="radio"
                                                       name="default_hq_edit"
                                                       value="{{ $h->id }}" wire:model="defaultHeadquarter">
                                                <small>Primaria</small>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('selectedHeadquarters') <span class="text-danger d-block small mt-1">{{ $message }}</span> @enderror
                                @error('defaultHeadquarter')  <span class="text-danger d-block small mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Rol (chips) --}}
                        <div class="perm-row span-2">
                            <div class="perm-col-title">Rol</div>
                            <div class="perm-col-controls">
                                <div class="perm-chips">
                                    @forelse($roles as $r)
                                        <label class="chip-radio" title="{{ $r->name }}">
                                            <input type="radio" class="form-check-input"
                                                   name="role_single_edit" value="{{ $r->id }}" wire:model="selectedRoleId">
                                            <span>{{ $r->name }}</span>
                                        </label>
                                    @empty
                                        <span class="text-warning small">No hay roles definidos.</span>
                                    @endforelse
                                </div>
                                @error('selectedRoleId') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>

                    </div> {{-- /perm-grid form-two-cols --}}
                </div>

                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light-primary btn-sm" wire:click="update">Actualizar</button>
                    <button type="button" class="btn btn-light-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- === MODAL: Rol & Permisos (FULLSCREEN, ULTRA-COMPACT) === --}}
    <div class="modal fade" id="modalPerms" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
        {{-- Fullscreen para evitar scroll --}}
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0">
                        Rol & Permisos
                        @if($permsUserName)
                            <small class="text-muted d-block fw-normal">Usuario: {{ $permsUserName }}</small>
                        @endif
                    </h6>
                    <button type="button" class="btn-close m-0" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-2">
                    {{-- Rol (línea compacta) --}}
                    <div class="perm-row border rounded px-2 py-2 mb-2">
                        <div class="perm-col-title">Rol</div>
                        <div class="perm-col-controls">
                            <div class="perm-chips">
                                @forelse($roles as $r)
                                    <label class="chip-radio" title="{{ $r->name }}">
                                        <input type="radio" class="form-check-input" name="role_single_perms"
                                               value="{{ $r->id }}" wire:model="selectedRoleId">
                                        <span>{{ $r->name }}</span>
                                    </label>
                                @empty
                                    <span class="text-warning small">No hay roles definidos.</span>
                                @endforelse
                            </div>
                            @error('selectedRoleId') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Permisos (grid sin tarjetas; compacto si solo hay 1 permiso en el grupo) --}}
                    <div class="perm-grid">
                        @foreach($aclGroups as $groupKey => $group)
                            @php
                                $count   = count($group['items'] ?? []);
                                $compact = ($group['type'] === 'single') || ($count === 1);
                                $only    = $group['items'][0] ?? null;
                            @endphp

                            {{-- Grupo con un solo permiso: título + checkbox a la derecha --}}
                            @if($compact && $only)
                                <div class="perm-row">
                                    <div class="perm-col-title">{{ $group['title'] }}</div>
                                    <div class="perm-col-controls">
                                        <label class="chip-check" title="{{ $only['key'] }}">
                                            <input class="form-check-input" type="checkbox"
                                                   value="{{ $only['key'] }}"
                                                   wire:model="selectedPermissionNames">
                                            <span>{{ $group['title'] }}</span>
                                        </label>
                                    </div>
                                </div>
                            @else
                                {{-- Grupo con varios permisos --}}
                                <div class="perm-row">
                                    <div class="perm-col-title d-flex align-items-center gap-2">
                                        <span>{{ $group['title'] }}</span>
                                        <a href="javascript:void(0)" class="action-icon" title="Marcar todo"
                                           wire:click="selectGroup('{{ $groupKey }}')">
                                            <i class="ti ti-square-check"></i>
                                        </a>
                                        <a href="javascript:void(0)" class="action-icon" title="Desmarcar"
                                           wire:click="deselectGroup('{{ $groupKey }}')">
                                            <i class="ti ti-square-x"></i>
                                        </a>
                                    </div>
                                    <div class="perm-col-controls">
                                        <div class="perm-chips">
                                            @foreach($group['items'] as $it)
                                                <label class="chip-check" title="{{ $it['key'] }}">
                                                    <input class="form-check-input" type="checkbox"
                                                           value="{{ $it['key'] }}"
                                                           wire:model="selectedPermissionNames">
                                                    <span>{{ $it['label'] }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>

                </div>

                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="savePerms">Guardar</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="openAddModal,openEditModal,openPermsModal,selectGroup,deselectGroup,save,update,savePerms">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>
