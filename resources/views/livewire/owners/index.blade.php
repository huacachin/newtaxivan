<div class="container-fluid">
    <!-- Basic Table start -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Propietarios</h4>
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
                    <a href="#" class="f-s-14">Propietarios</a>
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
                        <div class="col-xl-5 col-md-6 mb-2 mb-md-0">
                            <form class="app-form app-icon-form" action="#">
                                <div class="position-relative">
                                    <input type="search" class="form-control" placeholder="Buscar..."
                                           aria-label="Buscar" wire:model.live="search">
                                    <i class="ti ti-search text-dark"></i>
                                </div>
                            </form>
                        </div>
                        <div class="col-xl-2 col-md-4 mb-2 mb-md-0">
                            <select class="form-select" aria-label="Selecciona item a filtrar" wire:model.live="filter">
                                <option value="plate">Placa</option>
                                <option value="name">Nombre</option>
                                <option value="code">Código</option>
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-4 mb-2 mb-md-0">
                            <button class="btn btn-primary w-100" wire:click="openAddModal"><i class="ti ti-square-plus f-s-17"></i>
                                Nuevo
                            </button>
                        </div>
                        <div class="col-xl-2 col-md-4 mb-2 mb-md-0">
                            <button class="btn btn-primary w-100" wire:click="export"><i class="ti ti-file-analytics f-s-17"></i>
                                Exportar
                            </button>
                        </div>

                        <div class="col-xl-1 col-md-4 mb-2 mb-md-0">
                            <button id="down" class="btn btn-primary w-100"><i class="ti ti-square-chevrons-down f-s-17"></i>
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
                    <h5>Total propietarios: {{$owners->count()}}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped table-hover">
                            <thead class="table-primary">
                            <tr>
                                <th scope="col">Id</th>
                                <th scope="col">Placa</th>
                                <th scope="col">Nombre/Empresa</th>
                                <th scope="col">DNI/RUC</th>
                                <th scope="col">Cel.</th>
                                <th scope="col">Acción</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if($owners->count() >0)
                                @foreach ($owners as $owner)
                                    <tr>
                                        <td>{{$loop->iteration}}</td>
                                        <td>{{$owner->plate}}</td>
                                        <td>{{$owner->name}}</td>
                                        <td>{{$owner->document_number}}</td>
                                        <td>{{$owner->phone}}</td>
                                        <td width="10" class="text-center"><i class="ti ti-edit f-s-18 text-success" style="cursor:pointer" wire:click="openEditModal({{$owner->id}})"></i></td>

                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="6" class="text-center">No se encontrarón resultados</td>
                                </tr>
                            @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- Simple Table end -->

        <!-- Simple Table start -->
        <div class="col-xl-12">
            <div class="card">
                <div class="card-header">
                    <h5>Propietarios Libres: {{$ownersFree->count()}}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped table-hover">
                            <thead class="table-primary">
                            <tr>
                                <th scope="col">Id</th>
                                <th scope="col">Nombre/Empresa</th>
                                <th scope="col">DNI/RUC</th>
                                <th scope="col">Cel.</th>
                                <th scope="col">Acción</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($ownersFree as $owner)
                                <tr>
                                    <td>{{$loop->iteration}}</td>
                                    <td>{{$owner->name}}</td>
                                    <td>{{$owner->document_number}}</td>
                                    <td>{{$owner->phone}}</td>
                                    <td width="10" class="text-center"><i class="ti ti-edit f-s-18 text-success" style="cursor:pointer" wire:click="openEditModal({{$owner->id}})"></i></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- Simple Table end -->




    </div>


    <div class="modal fade" id="modalAddOwner" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Propietario</h5>
                    <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nombres</label>
                                <input id="name" type="text" class="form-control" placeholder="Ingresar nombres y apellidos" wire:model="name">
                                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="document_type" class="form-label">Tipo de documento</label>
                                <select class="form-select" id="document_type" wire:model="document_type">
                                    <option value="">Seleccionar</option>
                                    <option value="dni">DNI</option>
                                    <option value="ruc">RUC</option>
                                </select>
                                @error('document_type') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="document_number" class="form-label">Número de documento</label>
                                <input type="text" class="form-control" placeholder="Ingresar número de documento" wire:model="document_number">
                                @error('document_number') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="document_expiration_date" class="form-label">Doc F.Vencimiento</label>
                                <input type="date" class="form-control" wire:model="document_expiration_date">
                                @error('document_expiration_date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="birthdate" class="form-label">Fecha Nacimiento</label>
                                <input type="date" class="form-control" wire:model="birthdate">
                                @error('birthdate') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="district" class="form-label">Distrito</label>
                                <input type="text" class="form-control" placeholder="Ingresar distrito" wire:model="district">
                                @error('district') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="address" class="form-label">Dirección</label>
                                <input type="text" class="form-control" placeholder="Ingresar dirección" wire:model="address">
                                @error('address') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="`phone`" class="form-label">Teléfono</label>
                                <input type="text" class="form-control" placeholder="Ingresar teléfono" wire:model="phone">
                                @error('phone') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="`email`" class="form-label">Email</label>
                                <input type="email" class="form-control" placeholder="Ingresar email" wire:model="email">
                                @error('email') <span class="text-danger">{{ $message }}</span> @enderror
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

    <div class="modal fade" id="modalEditOwner" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Propietario</h5>
                    <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nombres</label>
                                <input id="name" type="text" class="form-control" placeholder="Ingresar nombres y apellidos" wire:model="name">
                                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="document_type" class="form-label">Tipo de documento</label>
                                <select class="form-select" id="document_type" wire:model="document_type">
                                    <option value="">Seleccionar</option>
                                    <option value="dni">DNI</option>
                                    <option value="ruc">RUC</option>
                                </select>
                                @error('document_type') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="document_number" class="form-label">Número de documento</label>
                                <input type="text" class="form-control" placeholder="Ingresar número de documento" wire:model="document_number">
                                @error('document_number') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="document_expiration_date" class="form-label">Doc F.Vencimiento</label>
                                <input type="date" class="form-control" wire:model="document_expiration_date">
                                @error('document_expiration_date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                {{$birthdate}}
                                <label for="birthdate" class="form-label">Fecha Nacimiento</label>
                                <input type="date" class="form-control" wire:model="birthdate">
                                @error('birthdate') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="district" class="form-label">Distrito</label>
                                <input type="text" class="form-control" placeholder="Ingresar distrito" wire:model="district">
                                @error('district') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="address" class="form-label">Dirección</label>
                                <input type="text" class="form-control" placeholder="Ingresar dirección" wire:model="address">
                                @error('address') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="`phone`" class="form-label">Teléfono</label>
                                <input type="text" class="form-control" placeholder="Ingresar teléfono" wire:model="phone">
                                @error('`phone') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="`email`" class="form-label">Email</label>
                                <input type="email" class="form-control" placeholder="Ingresar email" wire:model="email`">
                                @error('email') <span class="text-danger">{{ $message }}</span> @enderror
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
