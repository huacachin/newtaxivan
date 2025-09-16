<div class="container-fluid">
    <!-- Basic Table start -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Pagos</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-currency-dollar f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Pagos</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Listar</a>
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
                        <div class="col-xl-9 col-md-6 mb-2 mb-md-0">
                            <label class="form-label">Buscar</label>
                            <form class="app-form app-icon-form" action="#">

                                <div class="position-relative">
                                    <input type="search" class="form-control" placeholder="Buscar..."
                                           aria-label="Buscar" wire:model.live="search">
                                    <i class="ti ti-search text-dark"></i>
                                </div>
                            </form>
                        </div>
                        <div class="col-xl-3 col-md-4 mb-2 mb-md-0">
                            <label class="form-label">Filtro</label>
                            <select class="form-select" aria-label="Selecciona item a filtrar" wire:model.live="filter">
                                <option value="">Seleccione un filtro</option>
                                <option value="1">Placa</option>
                                <option value="2">Usuario</option>
                                <option value="3">Serie</option>
                            </select>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <!-- Simple Table end -->
        <!-- Simple Table start -->
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-xl-3 col-md-4 mb-2 mb-md-0">
                            <label class="form-label">Fecha Inicio</label>
                            <input type="date" class="form-control" wire:model.live="date_start">
                        </div>
                        <div class="col-xl-3 col-md-4 mb-2 mb-md-0">
                            <label class="form-label">Fecha Fin</label>
                            <input type="date" class="form-control" wire:model.live="date_end">
                        </div>
                        <div class="col-xl-3 col-md-4 mb-2 mb-md-0">
                            <label class="form-label">Sucursal</label>
                            <select class="form-select" aria-label="Selecciona item a filtrar"
                                    wire:model.live="headquarter_id">
                                <option value="">Todos</option>
                                @foreach($headquarters as $h)

                                    <option value="{{$h->id}}">{{$h->name}}</option>

                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-4 mb-2 mb-md-0">
                            <label class="form-label">Tipo</label>
                            <select class="form-select" aria-label="Selecciona item a filtrar" wire:model.live="type">
                                <option value="">Todos</option>
                                <option value="PAGO">Pago</option>
                                <option value="DEUDA">Deuda</option>
                                <option value="RETRASOr">Retraso</option>
                            </select>
                        </div>


                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-xl-2 col-md-4 mb-2 mb-md-0">
                            <button class="btn btn-primary w-100"><i class="ti ti-report-analytics f-s-16"></i>
                                Diario
                            </button>
                        </div>
                        <div class="col-xl-2 col-md-4 mb-2 mb-md-0">
                            <button class="btn btn-primary w-100"><i class="ti ti-report-analytics f-s-16"></i>
                                Mensual
                            </button>
                        </div>
                        <div class="col-xl-2 col-md-4 mb-2 mb-md-0">
                            <button class="btn btn-primary w-100"><i class="ti ti-report-analytics f-s-16"></i>
                                Estadis.
                            </button>
                        </div>
                        <div class="col-xl-2 col-md-4 mb-2 mb-md-0">
                            <button class="btn btn-primary w-100"><i class="ti ti-file-analytics f-s-16"></i>
                                Exportar
                            </button>
                        </div>
                        <div class="col-xl-2 col-md-4 mb-2 mb-md-0">
                            <button class="btn btn-primary w-100"><i class="ti ti-square-plus f-s-16"></i>
                                Nuevo
                            </button>
                        </div>
                        <div class="col-xl-2 col-md-4 mb-2 mb-md-0">
                            <button class="btn btn-primary w-100" id="down"><i class="ti ti-square-chevrons-down f-s-17"></i>
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

                            <thead class="table-primary text-center">
                            <tr>
                                <th scope="col">Id</th>
                                <th scope="col">Placa</th>
                                <th scope="col">Serie</th>
                                <th scope="col">Fecha Registro</th>
                                <th scope="col">Fecha Pago</th>
                                <th scope="col">Hora</th>
                                <th scope="col">Tipo</th>
                                <th scope="col">Sucursal</th>
                                <th scope="col">Usuario</th>
                                <th scope="col">S/.</th>
                                <th scope="col">Map</th>
                            </tr>
                            </thead>

                            <tbody class="text-center">
                            @if($payments->count() > 0)
                                @foreach($payments as $p)
                                    <tr>
                                        <td>{{$loop->iteration}}</td>
                                        <td>{{$p->legacy_plate}}
                                        <td>{{$p->serie}}</td>
                                        <td>{{$p->date_register}}</td>
                                        <td>{{$p->date_payment}}</td>
                                        <td>{{$p->hour}}</td>
                                        <td>{{$p->type}}</td>
                                        <td>{{$p->headquarter->name}}</td>
                                        <td>{{$p->user->name}}</td>
                                        <td>{{$p->amount}}</td>
                                        <td>
                                            @if(!empty($p->latitude) && !empty($p->longitude))
                                                <a href="https://maps.google.com/?q={{ $p->latitude }},{{ $p->longitude }}"
                                                   target="_blank" class="underline">🌍</a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="11">No se encontrarón resultados</td>
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
