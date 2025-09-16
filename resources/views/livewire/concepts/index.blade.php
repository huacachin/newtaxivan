<div class="container-fluid">
    <!-- Basic Table start -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Conceptos</h4>
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
    <!-- Basic Table end -->
    <div class="row table-section">
        <!-- Simple Table start -->
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8 mb-2 mb-md-0">
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
                        <div class="col-md-2 mb-2 mb-md-0">
                            <button class="btn btn-primary w-100"><i class="ti ti-file-analytics f-s-17"></i>
                                Exportar
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
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped table-hover">
                            <thead class="table-primary">
                            <tr>
                                <th scope="col">Id</th>
                                <th scope="col">Código</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Tipo</th>
                                <th scope="col">Acción</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if($concepts->count() > 0 )
                                @foreach($concepts as $concept)
                                    <tr>
                                        <td>{{$loop->iteration}}</td>
                                        <td>{{$concept->code}}</td>
                                        <td>{{$concept->name}}</td>
                                        <td>{{$concept->type}}</td>
                                        <td width="10" class="text-center"><i class="ti ti-edit f-s-18 text-success" style="cursor:pointer" wire:click="openEditModal({{$concept->id}})"></i></td>
                                    </tr>
                                @endforeach
                                @else
                                <tr>
                                    <td class="text-center" colspan="5">No se encontrarón resultados</td>
                                </tr>
                            @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- Simple Table end -->

        <div class="modal fade" id="modalAddConcept" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
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
                                    <input id="code" type="text" class="form-control" placeholder="Ingresar Código" wire:model="code">
                                    @error('code') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nombre del Concepto</label>
                                    <input type="text" class="form-control" placeholder="Ingresar Nombre del Concepto" wire:model="name">
                                    @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Estado</label>
                                    <select class="form-select" id="status" wire:model="status">
                                        <option value="inactive">Cancelado</option>
                                        <option value="active">Vigente</option>
                                    </select>
                                    @error('status') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="type" class="form-label">Tipo</label>
                                    <select class="form-select" id="type" wire:model="type">
                                        <option value="ingreso">Ingreso</option>
                                        <option value="egreso">Egreso</option>
                                    </select>
                                    @error('type') <span class="text-danger">{{ $message }}</span> @enderror
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

        <div class="modal fade" id="modalEditConcept" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
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
                                    <label for="code" class="form-label">Código</label>
                                    <input id="code" type="text" class="form-control" placeholder="Ingresar Código" wire:model="code">
                                    @error('code') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nombre del Concepto</label>
                                    <input type="text" class="form-control" placeholder="Ingresar Nombre del Concepto" wire:model="name">
                                    @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Estado</label>
                                    <select class="form-select" id="status" wire:model="status">
                                        <option value="inactive">Cancelado</option>
                                        <option value="active">Vigente</option>
                                    </select>
                                    @error('status') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="type" class="form-label">Tipo</label>
                                    <select class="form-select" id="type" wire:model="type">
                                        <option value="ingreso">Ingreso</option>
                                        <option value="egreso">Egreso</option>
                                    </select>
                                    @error('type') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light-primary" wire:click="update">Agregar</button>
                        <button type="button" class="btn btn-light-secondary"
                                data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
