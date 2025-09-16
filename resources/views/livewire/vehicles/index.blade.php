<div class="container-fluid">
    <!-- Basic Table start -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Vehículos</h4>
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
                    <a href="#" class="f-s-14">Vehiculos</a>
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
                        <div class="col-xl-3 col-md-6 mb-2 mb-md-0">
                            <form class="app-form app-icon-form" action="#">
                                <div class="position-relative">
                                    <input type="search" class="form-control" placeholder="Buscar..."
                                           aria-label="Buscar" wire:model.live="search">
                                    <i class="ti ti-search text-dark"></i>
                                </div>
                            </form>
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <select class="form-select" aria-label="Estado del vehiculo" wire:model.live="status">
                                <option value="active">Activo</option>
                                <option value="inactive">Cesado</option>
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-4 mb-2 mb-md-0">
                            <select class="form-select" aria-label="Default select example" wire:model.live="filter">
                                <option value="plate">Placa</option>
                                <option value="brand">Marca</option>
                                <option value="year">Año</option>
                                <option value="owner">Propietario</option>
                                <option value="driver">Conductor</option>
                                <option value="condition">Condición</option>
                                <option value="company">Empresa</option>
                                <option value="category">Categoría</option>
                                <option value="code">Código</option>
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-4 mb-2 mb-md-0">
                            <button class="btn btn-primary w-100" wire:click="openAddModal"><i
                                    class="ti ti-square-plus f-s-17"></i>
                                Nuevo
                            </button>
                        </div>
                        <div class="col-xl-2 col-md-4 mb-2 mb-md-0">
                            <button class="btn btn-primary w-100"><i class="ti ti-file-analytics f-s-17"></i>
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
                    <h5>Total vehículos: {{$vehicles->count()}}</h5>
                    <p><strong>D2:</strong> {{$vehicles->where('fuel','D2')->count()}} -
                        <strong>Gas:</strong> {{$vehicles->where('fuel','GAS')->count()}} -
                        <strong>V.T:</strong> {{ $vehicles->whereIn('fuel', ['GAS', 'D2'])->count() }} - <strong>V.Q.N.T:</strong> {{ $vehicles->whereNotIn('fuel', ['GAS', 'D2'])->count() }}
                        - <strong>Propietario:</strong> {{$owners}} - <strong>Conductor:</strong> {{$drivers}}</p>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped table-hover">
                            <thead class="table-primary">
                            <tr>
                                <th scope="col">Acción</th>
                                <th scope="col">Id</th>
                                <th scope="col">Placa</th>
                                <th scope="col">Marca</th>
                                <th scope="col">Año</th>
                                <th scope="col">Categoría</th>
                                <th scope="col">Propietario</th>
                                <th scope="col">Conductor</th>
                                <th scope="col">Modalidad</th>
                                <th scope="col">Combu.</th>
                                <th scope="col">Condición</th>
                                <th scope="col">Empresa Afil.</th>
                                @if($status === "inactive")
                                    <th scope="col">Fecha Cese</th>
                                @endif

                            </tr>
                            </thead>
                            <tbody>
                            @if($vehicles->count())
                                @foreach ($vehicles as $vehicle)
                                    <tr>
                                        <td width="10" class="text-center"><i class="ti ti-edit f-s-18 text-success"
                                                                              style="cursor:pointer"
                                                                              wire:click="openEditModal({{$vehicle->id}})"></i>
                                        </td>

                                        <td>{{$loop->iteration}}</td>
                                        <td>{{$vehicle->plate}}</td>
                                        <td>{{$vehicle->brand}}</td>
                                        <td>{{$vehicle->year}}</td>
                                        <td>{{$vehicle->class}}</td>
                                        <td>{{$vehicle->owner->name ?? "-"}}</td>
                                        <td>{{$vehicle->driver->name ?? "-"}}</td>
                                        <td>{{$vehicle->type}}</td>
                                        <td>{{$vehicle->fuel}}</td>
                                        <td>{{$vehicle->condition}}</td>
                                        <td>{{$vehicle->affiliated_company}}</td>
                                        @if($status === "inactive")
                                            <td>{{ $vehicle->termination_date?->format('d/m/Y') ?? '-' }}</td>
                                        @endif

                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="11" class="text-center">No se encontrarón resultados</td>
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

    <div class="modal fade" id="modalAddVehicle" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Vehiculo</h5>
                    <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="plate" class="form-label">Placa</label>
                                <input id="plate" type="text" class="form-control" placeholder="Ingresar placa"
                                       wire:model="plate">
                                @error('plate') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="headquarter" class="form-label">Sede</label>
                                <select name="headquarter" class="form-control" wire:model="headquarter" id="headquarter">
                                    <option value="">Seleccione</option>
                                    @foreach($listHeadquarters as $headquarter)
                                        <option value="{{$headquarter->name}}">{{$headquarter->name}}</option>
                                    @endforeach
                                </select>
                                @error('headquarter') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="entry_date" class="form-label">F. Ingreso</label>
                                <input type="date" class="form-control" wire:model="entry_date">
                                @error('entry_date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="termination_date" class="form-label">Fecha Cese</label>
                                <input type="date" class="form-control" wire:model="termination_date">
                                @error('termination_date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="class" class="form-label">Categoría</label>
                                <select name="class" class="form-control" wire:model="class" id="class">
                                    <option value="">Seleccione</option>
                                    <option value="M1">M1</option>
                                    <option value="M1-C3">M1-C3</option>
                                    <option value="M2">M2</option>
                                    <option value="MICROBUS">MICROBUS</option>
                                    <option value="M3.C3">M3.C3</option>
                                    <option value="M3.C1 OMNIBUS">M3.C1 OMNINUS</option>
                                    <option value="M3-C3">M3-C3</option>
                                    <option value="M2-C3">M2-C3</option>
                                </select>
                                @error('class') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="brand" class="form-label">Marca</label>
                                <select name="brand" class="form-control" wire:model="brand" id="brand">
                                    <option value="">Seleccione</option>
                                    <option value="Hyunday">Hyundai</option>
                                    <option value="Jac">Jac</option>
                                    <option value="Changan">Changan</option>
                                    <option value="DFSK">DFSK</option>
                                    <option value="Change">Change</option>
                                    <option value="Mitsubishi">Mitsubishi</option>
                                    <option value="Faw">Faw</option>
                                    <option value="Volkswagen">Volkswagen</option>
                                </select>
                                @error('brand') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="`year" class="form-label">Año</label>
                                <input type="text" class="form-control" placeholder="Ingresar año" wire:model="year">
                                @error('year') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="model" class="form-label">Modelo</label>
                                <input type="text" class="form-control" placeholder="Ingresar model" wire:model="model">
                                @error('model') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="bodywork" class="form-label">Carrocería</label>
                                <select name="bodywork" class="form-control" wire:model="bodywork" id="bodywork">
                                    <option value="">Seleccione</option>
                                    <option value="MULTIPROPOSITO">MULTIPROPOSITO</option>
                                    <option value="MICROBUS">MICROBUS</option>
                                    <option value="Minibus">Minibus</option>
                                </select>
                                @error('bodywork') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="color" class="form-label">Color</label>
                                <select class="form-select" id="color" wire:model="color">
                                    <option value="">Seleccionar</option>
                                    <option value="Azul">Azul</option>
                                    <option value="Azul Acero">Azul Acero</option>
                                    <option value="Azul Oscuro">Azul Oscuro</option>
                                    <option value="Beige">Beige</option>
                                    <option value="Blanco">Blanco</option>
                                    <option value="Blanco Perla">Blanco Perla</option>
                                    <option value="Dorado">Dorado</option>
                                    <option value="Gris">Gris</option>
                                    <option value="Gris Oscuro">Gris Oscuro</option>
                                    <option value="Marron">Marron</option>
                                    <option value="Moca Arabe">Moca Arabe</option>
                                    <option value="Negro">Negro</option>
                                    <option value="Negro Atemporal">Negro Atemporal</option>
                                    <option value="Oceanico">Oceanico</option>
                                    <option value="Plata">Plata</option>
                                    <option value="Plata Diamond">Plata Diamond</option>
                                    <option value="Plata Metalizado">Plata Metalizado</option>
                                    <option value="Plomo">Plomo</option>
                                </select>
                                @error('color') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="type" class="form-label">Modalidad</label>
                                <select class="form-select" id="type" wire:model="type">
                                    <option value="">Seleccionar</option>
                                    <option value="Particular ">Particular</option>
                                    <option value="Taxi Ejecutivo">Taxi Ejecutivo</option>
                                    <option value="Taxi Independiente">Taxi Independiente</option>
                                    <option value="Transporte Personal ">Transporte Personal</option>
                                    <option value="Transporte Turismo">Transporte Turismo</option>
                                </select>
                                @error('type') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="affiliated_company" class="form-label">Empresa Afiliada</label>
                                <input type="text" class="form-control" placeholder="Ingresar empresa afiliada"
                                       wire:model="affiliated_company">
                                @error('affiliated_company') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="condition" class="form-label">Condición</label>
                                <select name="condition" class="form-control" id="condition" wire:model="condition">
                                    <option value="">Seleccione</option>
                                    <option value="DT">DT</option>
                                    <option value="GN">GN</option>
                                    <option value="EX">EX</option>
                                    <option value="EX5">EX5</option>
                                </select>
                                @error('condition') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="owner_id" class="form-label">Propietario</label>
                                <select class="form-select" id="owner_id" wire:model="owner_id">
                                    <option value="">Seleccionar</option>
                                    @foreach($listOwners as $owner)
                                        <option value="{{$owner->id}}">{{$owner->name}}</option>
                                    @endforeach
                                </select>
                                @error('owner_id') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="driver_id" class="form-label">Conductor</label>
                                <select class="form-select" id="driver_id" wire:model="driver_id">
                                    <option value="">Seleccionar</option>
                                    @foreach($listDrivers as $driver)
                                        <option value="{{$driver->id}}">{{$driver->name}}</option>
                                    @endforeach
                                </select>
                                @error('driver_id') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="fuel" class="form-label">Combustible</label>
                                <select class="form-select" id="fuel" wire:model="fuel">
                                    <option value="">Seleccionar</option>
                                    <option value="D2">D2</option>
                                        <option value="GAS">GAS</option>
                                </select>
                                @error('fuel') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="soat_date" class="form-label">Soat F.V</label>
                                <input type="date" class="form-control" wire:model="soat_date">
                                @error('soat_date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="certificate_date" class="form-label">Certificado F.V</label>
                                <input type="date" class="form-control" wire:model="certificate_date">
                                @error('certificate_date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="technical_review" class="form-label">Revisión Técnica F.V</label>
                                <input type="date" class="form-control" wire:model="technical_review">
                                @error('technical_review') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="detail" class="form-label">Detalle</label>
                                <textarea class="form-control" name="" id="" rows="2" wire:model="detail"></textarea>
                                @error('detail') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-primary" wire:click="save">Agregar</button>
                    <button type="button" class="btn btn-light-secondary"
                            data-bs-dismiss="modal">Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalEditVehicle" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Vehiculo</h5>
                    <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="plate" class="form-label">Placa</label>
                                <input id="plate" type="text" class="form-control" placeholder="Ingresar placa"
                                       wire:model="plate">
                                @error('plate') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="headquarter" class="form-label">Sede</label>
                                <select name="headquarter" class="form-control" wire:model="headquarter" id="headquarter">
                                    <option value="">Seleccione</option>
                                    @foreach($listHeadquarters as $headquarter)
                                        <option value="{{$headquarter->name}}">{{$headquarter->name}}</option>
                                    @endforeach
                                </select>
                                @error('headquarter') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="entry_date" class="form-label">F. Ingreso</label>
                                <input type="date" class="form-control" wire:model="entry_date">
                                @error('entry_date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="termination_date" class="form-label">Fecha Cese</label>
                                <input type="date" class="form-control" wire:model="termination_date">
                                @error('termination_date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="class" class="form-label">Categoría</label>
                                <select name="class" class="form-control" wire:model="class" id="class">
                                    <option value="">Seleccione</option>
                                    <option value="M1">M1</option>
                                    <option value="M1-C3">M1-C3</option>
                                    <option value="M2">M2</option>
                                    <option value="MICROBUS">MICROBUS</option>
                                    <option value="M3.C3">M3.C3</option>
                                    <option value="M3.C1 OMNIBUS">M3.C1 OMNINUS</option>
                                    <option value="M3-C3">M3-C3</option>
                                    <option value="M2-C3">M2-C3</option>
                                </select>
                                @error('class') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="brand" class="form-label">Marca</label>
                                <select name="brand" class="form-control" wire:model="brand" id="brand">
                                    <option value="">Seleccione</option>
                                    <option value="Hyunday">Hyundai</option>
                                    <option value="Jac">Jac</option>
                                    <option value="Changan">Changan</option>
                                    <option value="DFSK">DFSK</option>
                                    <option value="Change">Change</option>
                                    <option value="Mitsubishi">Mitsubishi</option>
                                    <option value="Faw">Faw</option>
                                    <option value="Volkswagen">Volkswagen</option>
                                </select>
                                @error('brand') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="`year" class="form-label">Año</label>
                                <input type="text" class="form-control" placeholder="Ingresar año" wire:model="year">
                                @error('year') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="model" class="form-label">Modelo</label>
                                <input type="text" class="form-control" placeholder="Ingresar model" wire:model="model">
                                @error('model') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="bodywork" class="form-label">Carrocería</label>
                                <select name="bodywork" class="form-control" wire:model="bodywork" id="bodywork">
                                    <option value="">Seleccione</option>
                                    <option value="MULTIPROPOSITO">MULTIPROPOSITO</option>
                                    <option value="MICROBUS">MICROBUS</option>
                                    <option value="Minibus">Minibus</option>
                                </select>
                                @error('bodywork') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="color" class="form-label">Color</label>
                                <select class="form-select" id="color" wire:model="color">
                                    <option value="">Seleccionar</option>
                                    <option value="Azul">Azul</option>
                                    <option value="Azul Acero">Azul Acero</option>
                                    <option value="Azul Oscuro">Azul Oscuro</option>
                                    <option value="Beige">Beige</option>
                                    <option value="Blanco">Blanco</option>
                                    <option value="Blanco Perla">Blanco Perla</option>
                                    <option value="Dorado">Dorado</option>
                                    <option value="Gris">Gris</option>
                                    <option value="Gris Oscuro">Gris Oscuro</option>
                                    <option value="Marron">Marron</option>
                                    <option value="Moca Arabe">Moca Arabe</option>
                                    <option value="Negro">Negro</option>
                                    <option value="Negro Atemporal">Negro Atemporal</option>
                                    <option value="Oceanico">Oceanico</option>
                                    <option value="Plata">Plata</option>
                                    <option value="Plata Diamond">Plata Diamond</option>
                                    <option value="Plata Metalizado">Plata Metalizado</option>
                                    <option value="Plomo">Plomo</option>
                                </select>
                                @error('color') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="type" class="form-label">Modalidad</label>
                                <select class="form-select" id="type" wire:model="type">
                                    <option value="">Seleccionar</option>
                                    <option value="Particular ">Particular</option>
                                    <option value="Taxi Ejecutivo">Taxi Ejecutivo</option>
                                    <option value="Taxi Independiente">Taxi Independiente</option>
                                    <option value="Transporte Personal ">Transporte Personal</option>
                                    <option value="Transporte Turismo">Transporte Turismo</option>
                                </select>
                                @error('type') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="affiliated_company" class="form-label">Empresa Afiliada</label>
                                <input type="text" class="form-control" placeholder="Ingresar empresa afiliada"
                                       wire:model="affiliated_company">
                                @error('affiliated_company') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="condition" class="form-label">Condición</label>
                                <select name="condition" class="form-control" id="condition" wire:model="condition">
                                    <option value="">Seleccione</option>
                                    <option value="DT">DT</option>
                                    <option value="GN">GN</option>
                                    <option value="EX">EX</option>
                                    <option value="EX5">EX5</option>
                                </select>
                                @error('condition') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="owner_id" class="form-label">Propietario</label>
                                <select class="form-select" id="owner_id" wire:model="owner_id">
                                    <option value="">Seleccionar</option>
                                    @foreach($listOwners as $owner)
                                        <option value="{{$owner->id}}">{{$owner->name}}</option>
                                    @endforeach
                                </select>
                                @error('owner_id') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="driver_id" class="form-label">Conductor</label>
                                <select class="form-select" id="driver_id" wire:model="driver_id">
                                    <option value="">Seleccionar</option>
                                    @foreach($listDrivers as $driver)
                                        <option value="{{$driver->id}}">{{$driver->name}}</option>
                                    @endforeach
                                </select>
                                @error('driver_id') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="fuel" class="form-label">Combustible</label>
                                <select class="form-select" id="fuel" wire:model="fuel">
                                    <option value="">Seleccionar</option>
                                    <option value="D2">D2</option>
                                    <option value="GAS">GAS</option>
                                </select>
                                @error('fuel') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="soat_date" class="form-label">Soat F.V</label>
                                <input type="date" class="form-control" wire:model="soat_date">
                                @error('soat_date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="certificate_date" class="form-label">Certificado F.V</label>
                                <input type="date" class="form-control" wire:model="certificate_date">
                                @error('certificate_date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="technical_review" class="form-label">Revisión Técnica F.V</label>
                                <input type="date" class="form-control" wire:model="technical_review">
                                @error('technical_review') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="detail" class="form-label">Detalle</label>
                                <textarea class="form-control" name="" id="" rows="2" wire:model="detail"></textarea>
                                @error('detail') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-primary" wire:click="update">Editar</button>
                    <button type="button" class="btn btn-light-secondary"
                            data-bs-dismiss="modal">Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
