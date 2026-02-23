{{-- resources/views/livewire/concepts/index.blade.php --}}
<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">LISTADO GENERAL DE CONCEPTOS</h4>
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
                    <a href="#" class="f-s-14">Conceptos</a>
                </li>
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
                            <div class="d-flex flex-wrap align-items-end gap-2 overflow-auto py-1">

                                <!-- Input con ancho fijo (ajusta a gusto) -->
                                <div class="flex-shrink-0" style="width: 260px;">
                                    <input type="search"
                                           class="form-control form-control-sm"
                                           placeholder="Buscar..."
                                           aria-label="Buscar"
                                           wire:model.live="search">
                                </div>

                                <!-- Botón a la derecha (ancho intrínseco) -->
                                <button class="btn btn-sm btn-primary flex-shrink-0"
                                        wire:click="openAddModal">
                                    <i class="ti ti-square-plus f-s-12"></i> Nuevo
                                </button>

                            </div>
                        </div>
                    </div>
                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>Id</th>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Acción</th>
                            </tr>
                            </thead>

                            <tbody>
                            @if($concepts->count() > 0)
                                @foreach($concepts as $concept)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $concept->code }}</td>
                                        <td>{{ $concept->name }}</td>
                                        <td>{{ ucfirst($concept->type) }}</td>
                                        <td>
                                            <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"
                                               wire:click="openEditModal({{ $concept->id }})"></i>
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td class=" py-4 text-muted" colspan="5">No se encontraron resultados
                                    </td>
                                </tr>
                            @endif
                            </tbody>

                            <tfoot class="bg-primary">
                            <tr>
                                <td class="sticky-col"></td>
                                <td class="text-start">TOTAL</td>
                                <td class="sticky-col-2"></td>
                                <td colspan="2" class="num">{{ number_format($concepts->count()) }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- MODAL: AGREGAR --}}
        <div class="modal fade" id="modalAddConcept" aria-hidden="true" tabindex="-1" data-bs-backdrop="static"
             wire:ignore.self>
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Agregar Concepto</h5>
                        <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="code" class="form-label">Código</label>
                                    <input id="code" type="text" class="form-control" placeholder="Ingresar Código"
                                           wire:model="code">
                                    @error('code') <span class="title-modules">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nombre del Concepto</label>
                                    <input id="name" type="text" class="form-control"
                                           placeholder="Ingresar Nombre del Concepto" wire:model="name">
                                    @error('name') <span class="title-modules">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Estado</label>
                                    <select class="form-select" id="status" wire:model="status">
                                        <option value="inactive">Cancelado</option>
                                        <option value="active">Vigente</option>
                                    </select>
                                    @error('status') <span class="title-modules">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="type" class="form-label">Tipo</label>
                                    <select class="form-select" id="type" wire:model="type">
                                        <option value="ingreso">Ingreso</option>
                                        <option value="egreso">Egreso</option>
                                    </select>
                                    @error('type') <span class="title-modules">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light-primary" wire:click="save">Agregar</button>
                        <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- MODAL: EDITAR --}}
        <div class="modal fade" id="modalEditConcept" aria-hidden="true" tabindex="-1" data-bs-backdrop="static"
             wire:ignore.self>
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar Concepto</h5>
                        <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="code_e" class="form-label">Código</label>
                                    <input id="code_e" type="text" class="form-control" placeholder="Ingresar Código"
                                           wire:model="code">
                                    @error('code') <span class="title-modules">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name_e" class="form-label">Nombre del Concepto</label>
                                    <input id="name_e" type="text" class="form-control"
                                           placeholder="Ingresar Nombre del Concepto" wire:model="name">
                                    @error('name') <span class="title-modules">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status_e" class="form-label">Estado</label>
                                    <select class="form-select" id="status_e" wire:model="status">
                                        <option value="inactive">Cancelado</option>
                                        <option value="active">Vigente</option>
                                    </select>
                                    @error('status') <span class="title-modules">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="type_e" class="form-label">Tipo</label>
                                    <select class="form-select" id="type_e" wire:model="type">
                                        <option value="ingreso">Ingreso</option>
                                        <option value="egreso">Egreso</option>
                                    </select>
                                    @error('type') <span class="title-modules">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        {{-- <button type="button" class="btn btn-light-primary" wire:click="update">Agregar</button> --}}
                        <button type="button" class="btn btn-light-primary" wire:click="update">Actualizar</button>
                        <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="save,update">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>
