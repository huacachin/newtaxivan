<div class="container-fluid">
    <!-- Basic Table start -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Conductores</h4>
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
                    <a href="#" class="f-s-14">Conductores</a>
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
                    <h5>Total conductores: {{$drivers->count()}}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped table-hover">
                            <thead class="table-primary">
                            <tr>
                                <th scope="col">Id</th>
                                <th scope="col">Placa</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">DNI</th>
                                <th scope="col">I.Contrato</th>
                                <th scope="col">F.Contrato</th>
                                <th scope="col">Celular</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Acción</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if($drivers->count() > 0)
                                @foreach($drivers as $driver)
                                    <tr>
                                        <td>{{$loop->iteration}}</td>
                                        <td>{{$driver->vehicles->first()->plate ?? "-"}}</td>
                                        <td>{{$driver->name}}</td>
                                        <td>{{$driver->document_number}}</td>
                                        <td>{{($driver->contract_start && $driver->contract_start !== '0000-00-00') ? \Illuminate\Support\Carbon::parse($driver->contract_start)->format('d/m/Y') : '—'}}</td>
                                        <td>{{($driver->contract_end && $driver->contract_end !== '0000-00-00') ? \Illuminate\Support\Carbon::parse($driver->contract_end)->format('d/m/Y') : '—'}}</td>
                                        <td>{{$driver->phone}}</td>
                                        <td>{{$driver->condition}}</td>
                                        <td width="10" class="text-center"><i class="ti ti-edit f-s-18 text-success" style="cursor:pointer" wire:click="openEditModal({{$driver->id}})"></i></td>

                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="9" class="text-center">No se encontrarón resultados</td>
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
                    <h5>Conductores Libres: {{$driversFree->count()}}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped table-hover">
                            <thead class="table-primary">
                            <tr>
                                <th scope="col">Id</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">DNI</th>
                                <th scope="col">I.Contrato</th>
                                <th scope="col">F.Contrato</th>
                                <th scope="col">Celular</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Acción</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($driversFree as $driver)
                                <tr>
                                    <td>{{$loop->iteration}}</td>
                                    <td>{{$driver->name}}</td>
                                    <td>{{$driver->document_number}}</td>
                                    <td>{{($driver->contract_start && $driver->contract_start !== '0000-00-00') ? \Illuminate\Support\Carbon::parse($driver->contract_start)->format('d/m/Y') : '—'}}</td>
                                    <td>{{($driver->contract_end && $driver->contract_end !== '0000-00-00') ? \Illuminate\Support\Carbon::parse($driver->contract_end)->format('d/m/Y') : '—'}}</td>
                                    <td>{{$driver->phone}}</td>
                                    <td>{{$driver->condition}}</td>
                                    <td width="10" class="text-center"><i class="ti ti-edit f-s-18 text-success" style="cursor:pointer" wire:click="openEditModal({{$driver->id}})"></i></td>

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

    <div class="modal fade" id="modalAddDriver" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Conductor</h5>
                    <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nombres</label>
                                <input id="name" type="text" class="form-control" placeholder="Ingresar nombres y apellidos" wire:model="name">
                                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="document_number" class="form-label">Número de documento</label>
                                <input type="text" class="form-control" placeholder="Ingresar número de documento" wire:model="document_number">
                                @error('document_number') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="document_expiration_date" class="form-label">Doc F.Vencimiento</label>
                                <input type="date" class="form-control" wire:model="document_expiration_date">
                                @error('document_expiration_date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="birthdate" class="form-label">Fecha Nacimiento</label>
                                <input type="date" class="form-control" wire:model="birthdate">
                                @error('birthdate') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="district" class="form-label">Distrito</label>
                                <input type="text" class="form-control" placeholder="Ingresar distrito" wire:model="district">
                                @error('district') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="condition" class="form-label">Condición</label>
                                <select class="form-select" id="condition" wire:model="condition">
                                    <option value="Propietario">Propietario</option>
                                    <option value="Alquilado">Alquilado</option>
                                </select>
                                @error('condition') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="address" class="form-label">Dirección</label>
                                <input type="text" class="form-control" placeholder="Ingresar dirección" wire:model="address">
                                @error('address') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Teléfono</label>
                                <input type="text" class="form-control" placeholder="Ingresar teléfono" wire:model="phone">
                                @error('phone') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" placeholder="Ingresar email" wire:model="email">
                                @error('email') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="license" class="form-label">Licencia</label>
                                <input type="text" class="form-control" placeholder="Ingresar número de licencia" wire:model="license">
                                @error('license') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="license_issue_date" class="form-label">Fecha Expedición</label>
                                <input type="date" class="form-control" wire:model="license_issue_date">
                                @error('license_issue_date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="license_revalidation_date" class="form-label">Fecha Revalidación</label>
                                <input type="date" class="form-control" wire:model="license_revalidation_date">
                                @error('license_revalidation_date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="class" class="form-label">Clase</label>
                                <input type="text" class="form-control" placeholder="Ingresar clase" wire:model="class">
                                @error('class') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="category" class="form-label">Categoría</label>
                                <select class="form-select" id="category" wire:model="category">
                                    <option value="A A1">A1</option>
                                    <option value="A 2A">A 2A</option>
                                    <option value="A 2B">A 2B</option>
                                    <option value="A 3A">A 3A</option>
                                    <option value="A 3B">A 3B</option>
                                    <option value="A 3C">A 3C</option>
                                </select>
                                @error('category') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="score" class="form-label">Puntos Acumulados</label>
                                <input type="text" class="form-control" placeholder="Ingresar puntos acumulados" wire:model="score">
                                @error('score') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="contract_start" class="form-label">F.Inicio Contrato</label>
                                <input type="date" class="form-control" wire:model="contract_start">
                                @error('contract_start') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="contract_end" class="form-label">F.Fin Contrato</label>
                                <input type="date" class="form-control" wire:model="contract_end">
                                @error('contract_end') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                    </div>

                    <div class="app-divider-v justify-content-center">
                        <p>Credencial de Educación y Seguridad Vial.</p>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="credential" class="form-label">Fecha Expedición</label>
                                <input type="date" class="form-control" wire:model="credential">
                                @error('credential') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="credential_expiration_date" class="form-label">Fecha Vencimiento</label>
                                <input type="date" class="form-control" wire:model="credential_expiration_date">
                                @error('credential_expiration_date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="credential_municipality" class="form-label">Municipalidad</label>
                                <select class="form-select" id="credential_municipality" wire:model="credential_municipality">
                                    <option value="lima">Lima</option>
                                    <option value="callao">Callao</option>
                                </select>
                                @error('credential_municipality') <span class="text-danger">{{ $message }}</span> @enderror
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

    <div class="modal fade" id="modalEditDriver" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Conductor</h5>
                    <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nombres</label>
                                <input id="name" type="text" class="form-control" placeholder="Ingresar nombres y apellidos" wire:model="name">
                                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="document_number" class="form-label">Número de documento</label>
                                <input type="text" class="form-control" placeholder="Ingresar número de documento" wire:model="document_number">
                                @error('document_number') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="document_expiration_date" class="form-label">Doc F.Vencimiento</label>
                                <input type="date" class="form-control" wire:model="document_expiration_date">
                                @error('document_expiration_date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="birthdate" class="form-label">Fecha Nacimiento</label>
                                <input type="date" class="form-control" wire:model="birthdate">
                                @error('birthdate') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="district" class="form-label">Distrito</label>
                                <input type="text" class="form-control" placeholder="Ingresar distrito" wire:model="district">
                                @error('district') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="condition" class="form-label">Condición</label>
                                <select class="form-select" id="condition" wire:model="condition">
                                    <option value="Propietario">Propietario</option>
                                    <option value="Alquilado">Alquilado</option>
                                </select>
                                @error('condition') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="address" class="form-label">Dirección</label>
                                <input type="text" class="form-control" placeholder="Ingresar dirección" wire:model="address">
                                @error('address') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Teléfono</label>
                                <input type="text" class="form-control" placeholder="Ingresar teléfono" wire:model="phone">
                                @error('phone') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" placeholder="Ingresar email" wire:model="email">
                                @error('email') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="license" class="form-label">Licencia</label>
                                <input type="text" class="form-control" placeholder="Ingresar número de licencia" wire:model="license">
                                @error('license') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="license_issue_date" class="form-label">Fecha Expedición</label>
                                <input type="date" class="form-control" wire:model="license_issue_date">
                                @error('license_issue_date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="license_revalidation_date" class="form-label">Fecha Revalidación</label>
                                <input type="date" class="form-control" wire:model="license_revalidation_date">
                                @error('license_revalidation_date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="class" class="form-label">Clase</label>
                                <input type="text" class="form-control" placeholder="Ingresar clase" wire:model="class">
                                @error('class') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="category" class="form-label">Categoría</label>
                                <select class="form-select" id="category" wire:model="category">
                                    <option value="A A1">A1</option>
                                    <option value="A 2A">A 2A</option>
                                    <option value="A 2B">A 2B</option>
                                    <option value="A 3A">A 3A</option>
                                    <option value="A 3B">A 3B</option>
                                    <option value="A 3C">A 3C</option>
                                </select>
                                @error('category') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="score" class="form-label">Puntos Acumulados</label>
                                <input type="text" class="form-control" placeholder="Ingresar puntos acumulados" wire:model="score">
                                @error('score') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="contract_start" class="form-label">F.Inicio Contrato</label>
                                <input type="date" class="form-control" wire:model="contract_start">
                                @error('contract_start') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="contract_end" class="form-label">F.Fin Contrato</label>
                                <input type="date" class="form-control" wire:model="contract_end">
                                @error('contract_end') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>


                    </div>

                    <div class="app-divider-v justify-content-center">
                        <p>Credencial de Educación y Seguridad Vial.</p>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="credential" class="form-label">Fecha Expedición</label>
                                <input type="date" class="form-control" wire:model="credential">
                                @error('credential') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="credential_expiration_date" class="form-label">Fecha Vencimiento</label>
                                <input type="date" class="form-control" wire:model="credential_expiration_date">
                                @error('credential_expiration_date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="credential_municipality" class="form-label">Municipalidad</label>
                                <select class="form-select" id="credential_municipality" wire:model="credential_municipality">
                                    <option value="lima">Lima</option>
                                    <option value="callao">Callao</option>
                                </select>
                                @error('credential_municipality') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-primary" wire:click="update">Editar</button>
                    <button type="button" class="btn btn-light-secondary"
                            data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>


</div>
