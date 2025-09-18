<div class="container-fluid">
    <!-- Basic Table start -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Usuarios</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Configuración</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Usuarios</a>
                </li>
            </ul>
        </div>
    </div>
    <!-- Basic Table end -->
    <div class="row table-section">
        <!-- Simple Table start -->
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-10 mb-2 mb-md-0">
                            <form class="app-form app-icon-form" action="#">
                                <div class="position-relative">
                                    <input type="search" class="form-control" placeholder="Buscar..."
                                           aria-label="Buscar" wire:model.live="search">
                                    <i class="ti ti-search text-dark"></i>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-2 mb-2 mb-md-0">
                            <button class="btn btn-primary w-100" wire:click="openAddModal"><i class="ti ti-square-plus f-s-17"></i>
                                Nuevo
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <!-- Simple Table end -->
        <!-- Simple Table start -->
        <div class="col-xl-12">
            <div class="card">
                <div class="card-header">
                    <h5>Usuarios</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped table-hover">
                            <thead>
                            <tr>
                                <th scope="col">Id</th>
                                <th scope="col">Nombres</th>
                                <th scope="col">Usuario</th>
                                <th scope="col">Teléfono</th>
                                <th scope="col">Sede</th>
                                <th scope="col">Permisos</th> {{-- antes decía "Rol" --}}
                                <th scope="col">Acción</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if($users->count() > 0)
                                @foreach($users as $user)
                                    <tr>
                                        <td>{{$loop->iteration}}</td>
                                        <td>{{$user->name}}</td>
                                        <td>{{$user->username}}</td>
                                        <td>{{$user->phone}}</td>
                                        <td>{{$user->headquarter->name}}</td>
                                        <td>
                                            <span class="badge bg-dark">{{ $user->permissions->count() }} permisos</span>
                                        </td>
                                        <td width="10" class="text-center">
                                            <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer" wire:click="openEditModal({{$user->id}})"></i>
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="7" class="text-center">No se encontrarón resultados</td>
                                </tr>
                            @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- Simple Table end -->

    </div>

    {{-- MODAL: AGREGAR (sin permisos) --}}
    <div class="modal fade" id="modalAddUser" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Usuario</h5>
                    <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nombre</label>
                                <input id="name" type="text" class="form-control" placeholder="Ingresar Usuario" wire:model.live="name">
                                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Usuario</label>
                                <input id="username" type="text" class="form-control" placeholder="Ingresar Usuario" wire:model="username">
                                @error('username') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="pwd" class="form-label">Contraseña</label>
                                <input id="pwd" type="text" class="form-control" placeholder="Ingresar Contraseña" wire:model.live="pwd">
                                @error('pwd') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" placeholder="Ingresar Email" wire:model="email">
                                @error('email') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="document_type" class="form-label">Tipo de Documento</label>
                                <select class="form-select" id="document_type" wire:model="document_type">
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
                                <input id="document_number" type="text" class="form-control" placeholder="Ingresar Número de Documento" wire:model="document_number">
                                @error('document_number') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Télefono</label>
                                <input id="phone" type="text" class="form-control" placeholder="Ingresar Teléfono" wire:model="phone">
                                @error('phone') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="headquarter" class="form-label">Sucursal</label>
                                <select class="form-select" id="headquarter" wire:model="headquarter">
                                    <option value="">Selecciona una sucursal</option>
                                    @foreach($headquartes as $h)
                                        <option value="{{$h->id}}">{{$h->name}}</option>
                                    @endforeach
                                </select>
                                @error('headquarter') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-primary" wire:click="save">Agregar</button>
                    <button type="button" class="btn btn-light-secondary"
                            data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL: EDITAR (con permisos) --}}
    <div class="modal fade" id="modalEditUser" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Usuario</h5>
                    <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nombre</label>
                                <input id="name" type="text" class="form-control" placeholder="Ingresar Usuario" wire:model.live="name">
                                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Usuario</label>
                                <input id="username" type="text" class="form-control" placeholder="Ingresar Usuario" wire:model="username">
                                @error('username') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="pwd" class="form-label">Contraseña</label>
                                <input id="pwd" type="text" class="form-control" placeholder="Ingresar Contraseña" wire:model.live="pwd">
                                @error('pwd') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" placeholder="Ingresar Email" wire:model="email">
                                @error('email') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="document_type" class="form-label">Tipo de Documento</label>
                                <select class="form-select" id="document_type" wire:model="document_type">
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
                                <input id="document_number" type="text" class="form-control" placeholder="Ingresar Número de Documento" wire:model="document_number">
                                @error('document_number') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Télefono</label>
                                <input id="phone" type="text" class="form-control" placeholder="Ingresar Teléfono" wire:model="phone">
                                @error('phone') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="headquarter" class="form-label">Sucursal</label>
                                <select class="form-select" id="headquarter" wire:model="headquarter">
                                    <option value="">Selecciona una sucursal</option>
                                    @foreach($headquartes as $h)
                                        <option value="{{$h->id}}">{{$h->name}}</option>
                                    @endforeach
                                </select>
                                @error('headquarter') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

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
                    <button type="button" class="btn btn-light-secondary"
                            data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>
