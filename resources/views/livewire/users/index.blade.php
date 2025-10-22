{{-- resources/views/livewire/users/index.blade.php --}}
@push('styles')
    <style>


        table {
            border-collapse: collapse; /* opcional */
            width: 100%;
        }

        th,td{
            padding: 3px !important;
            font-size: 10px !important;
            text-align: center !important;
            vertical-align: middle;   /* <-- clave */
        }

        .btn, input,select {
            font-size: 10px !important;
        }

        .screen-overlay {
            position: fixed;
            inset: 0;                 /* full viewport */
            display: none;            /* Livewire lo pondrá en flex */
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,.35);
            backdrop-filter: blur(2px);
            z-index: 2000;            /* sobre modals/backdrops de Bootstrap */
            pointer-events: all;      /* bloquea clics */
        }
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
                            <form class="app-form app-icon-form" action="#" onsubmit="return false;">

                                    <input type="search" class="form-control" placeholder="Buscar..."
                                           aria-label="Buscar" wire:model.live="search">

                            </form>
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
                                        <td>
                                            {{ optional($user->roles->first())->name ?? '—' }}
                                        </td>
                                        <td>
                                            <span class="badge bg-dark">
                                                {{ $user->permissions->count() }} permisos
                                            </span>
                                        </td>
                                        <td>
                                            <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"
                                               wire:click="openEditModal({{ $user->id }})"></i>
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

    {{-- MODAL: AGREGAR (todos los campos) --}}
    <div class="modal fade" id="modalAddUser" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Usuario</h5>
                    <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        {{-- Campos --}}
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nombre</label>
                                <input id="name" type="text" class="form-control" placeholder="Ingresar nombre" wire:model.live="name">
                                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Usuario</label>
                                <input id="username" type="text" class="form-control" placeholder="Ingresar usuario" wire:model="username">
                                @error('username') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="pwd" class="form-label">Contraseña</label>
                                <input id="pwd" type="text" class="form-control" placeholder="Ingresar contraseña" wire:model.live="pwd">
                                @error('pwd') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input id="email" type="email" class="form-control" placeholder="Ingresar email" wire:model="email">
                                @error('email') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="document_type" class="form-label">Tipo de Documento</label>
                                <select id="document_type" class="form-select" wire:model="document_type">
                                    <option value="dni">DNI</option>
                                    <option value="ruc">RUC</option>
                                    <option value="ce">CE</option>
                                </select>
                                @error('document_type') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="document_number" class="form-label">Número de Documento</label>
                                <input id="document_number" type="text" class="form-control" placeholder="Ingresar número" wire:model="document_number">
                                @error('document_number') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Teléfono</label>
                                <input id="phone" type="text" class="form-control" placeholder="Ingresar teléfono" wire:model="phone">
                                @error('phone') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Sucursales (múltiple) + Primaria --}}
                        <div class="col-12">
                            <label class="form-label">Sucursales</label>
                            <div class="row g-2">
                                @foreach($headquartes as $h)
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="border rounded p-2 d-flex align-items-center justify-content-between">
                                            <label class="form-check-label d-flex align-items-center gap-2 mb-0">
                                                <input class="form-check-input"
                                                       type="checkbox"
                                                       value="{{ $h->id }}"
                                                       wire:model="selectedHeadquarters">
                                                <span>{{ $h->name }}</span>
                                            </label>

                                            {{-- Radio para marcar como primaria --}}
                                            <div class="form-check mb-0" title="Marcar como sede primaria">
                                                <input class="form-check-input"
                                                       type="radio"
                                                       name="default_hq"
                                                       value="{{ $h->id }}"
                                                       wire:model="defaultHeadquarter">
                                                <small class="text-muted">Primaria</small>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            @error('selectedHeadquarters') <span class="text-danger d-block mt-1">{{ $message }}</span> @enderror
                            @error('defaultHeadquarter')  <span class="text-danger d-block mt-1">{{ $message }}</span> @enderror

                            <small class="text-muted d-block mt-1">
                                Selecciona una o más sucursales y elige cuál será la <strong>primaria</strong>.
                            </small>
                        </div>


                        <div class="col-12 mt-2">
                            <h6 class="mb-2">Rol</h6>
                            <div class="row g-2">
                                @forelse($roles as $r)
                                    <div class="col-6 col-md-3">
                                        <label class="form-check-label">
                                            <input class="form-check-input"
                                                   type="radio"
                                                   name="role_single_add"
                                                   value="{{ $r->id }}"
                                                   wire:model="selectedRoleId">
                                            <span class="ms-1">{{ $r->name }}</span>
                                        </label>
                                    </div>
                                @empty
                                    <div class="col-12">
                                        <div class="alert alert-warning mb-0">No hay roles definidos.</div>
                                    </div>
                                @endforelse
                            </div>
                            @error('selectedRoleId') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                    </div> {{-- row --}}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-primary" wire:click="save">Agregar</button>
                    <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL: EDITAR (todos los campos + permisos) --}}
    <div class="modal fade" id="modalEditUser" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Usuario</h5>
                    <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    {{-- === Mismos campos que en Agregar === --}}
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name_e" class="form-label">Nombre</label>
                                <input id="name_e" type="text" class="form-control" placeholder="Ingresar nombre" wire:model.live="name">
                                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username_e" class="form-label">Usuario</label>
                                <input id="username_e" type="text" class="form-control" placeholder="Ingresar usuario" wire:model="username">
                                @error('username') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="pwd_e" class="form-label">Contraseña</label>
                                <input id="pwd_e" type="text" class="form-control" placeholder="Nueva contraseña (opcional)" wire:model.live="pwd">
                                @error('pwd') <span class="text-danger">{{ $message }}</span> @enderror
                                <small class="text-muted">Déjalo en blanco para no cambiarla.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email_e" class="form-label">Email</label>
                                <input id="email_e" type="email" class="form-control" placeholder="Ingresar email" wire:model="email">
                                @error('email') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="document_type_e" class="form-label">Tipo de Documento</label>
                                <select id="document_type_e" class="form-select" wire:model="document_type">
                                    <option value="dni">DNI</option>
                                    <option value="ruc">RUC</option>
                                    <option value="ce">CE</option>
                                </select>
                                @error('document_type') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="document_number_e" class="form-label">Número de Documento</label>
                                <input id="document_number_e" type="text" class="form-control" placeholder="Ingresar número" wire:model="document_number">
                                @error('document_number') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone_e" class="form-label">Teléfono</label>
                                <input id="phone_e" type="text" class="form-control" placeholder="Ingresar teléfono" wire:model="phone">
                                @error('phone') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Sucursales (múltiple) + Primaria --}}
                    <div class="col-12">
                        <label class="form-label">Sucursales</label>
                        <div class="row g-2">
                            @foreach($headquartes as $h)
                                <div class="col-12 col-md-6 col-lg-4">
                                    <div class="border rounded p-2 d-flex align-items-center justify-content-between">
                                        <label class="form-check-label d-flex align-items-center gap-2 mb-0">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   value="{{ $h->id }}"
                                                   wire:model="selectedHeadquarters">
                                            <span>{{ $h->name }}</span>
                                        </label>

                                        {{-- Radio para marcar como primaria --}}
                                        <div class="form-check mb-0" title="Marcar como sede primaria">
                                            <input class="form-check-input"
                                                   type="radio"
                                                   name="default_hq"
                                                   value="{{ $h->id }}"
                                                   wire:model="defaultHeadquarter">
                                            <small class="text-muted">Primaria</small>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @error('selectedHeadquarters') <span class="text-danger d-block mt-1">{{ $message }}</span> @enderror
                        @error('defaultHeadquarter')  <span class="text-danger d-block mt-1">{{ $message }}</span> @enderror

                        <small class="text-muted d-block mt-1">
                            Selecciona una o más sucursales y elige cuál será la <strong>primaria</strong>.
                        </small>
                    </div>

                    {{-- === ROLES (crear/editar) === --}}
                    <div class="row mt-2">
                        <div class="col-12">
                            <h6 class="mb-2">Rol</h6>
                        </div>
                        <div class="col-12">
                            <div class="card border">
                                <div class="card-body">
                                    <div class="row g-2">
                                        @forelse($roles as $r)
                                            <div class="col-6 col-md-3">
                                                <label class="form-check-label">
                                                    <input class="form-check-input"
                                                           type="radio"
                                                           name="role_single_edit"
                                                           value="{{ $r->id }}"
                                                           wire:model="selectedRoleId">
                                                    <span class="ms-1">{{ $r->name }}</span>
                                                </label>
                                            </div>
                                        @empty
                                            <div class="col-12">
                                                <div class="alert alert-warning mb-0">No hay roles definidos.</div>
                                            </div>
                                        @endforelse
                                    </div>
                                    @error('selectedRoleId') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- === /ROLES === --}}

                    {{-- === PERMISOS (solo en EDITAR) === --}}
                    <div class="row mt-2">
                        <div class="col-12">
                            <h6 class="mb-2">Permisos por módulo</h6>
                        </div>

                        @forelse($permissionGroups as $module => $perms)
                            @php $moduleTitle = $perms->first()->module_label ?? ucfirst($module); @endphp

                            <div class="col-12 mb-3">
                                <div class="card border">
                                    <div class="card-header d-flex justify-content-between align-items-center py-2">
                                        <strong>{{ $moduleTitle }}</strong>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                                    wire:click="selectModule('{{ $module }}')">
                                                Marcar todo
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                                    wire:click="deselectModule('{{ $module }}')">
                                                Desmarcar
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-2">
                                            @foreach($perms as $p)
                                                <div class="col-6 col-md-3">
                                                    <label class="form-check-label" title="{{ $p->description }}">
                                                        <input class="form-check-input"
                                                               type="checkbox"
                                                               value="{{ $p->id }}"
                                                               wire:model="selectedPermissions">
                                                        <span class="ms-1">{{ $p->label ?? $p->name }}</span>
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="alert alert-warning mb-0">
                                    No hay permisos cargados. Ejecuta el seeder de catálogo.
                                </div>
                            </div>
                        @endforelse
                    </div>
                    {{-- === /PERMISOS === --}}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-primary" wire:click="update">Actualizar</button>
                    <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="openAddModal,openEditModal,toggleGroup,save,update">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>
