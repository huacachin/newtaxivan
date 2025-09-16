<div class="container-fluid">
    <!-- Basic Table start -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Costo por placa - Lista General</h4>
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
                    <a href="#" class="f-s-14">Costo por placa</a>
                </li>
            </ul>
        </div>
    </div>
    <!-- Basic Table end -->
    <div class="row table-section">

        <!-- Simple Table start -->
        <div class="col-xl-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>Costo por placa</h5>
                    <button class="btn btn-primary" data-bs-toggle="modal"
                            data-bs-target="#modalGenerateCostPerPlate"><i class="ti ti-square-rounded-plus f-s-14"></i> Crear</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped table-hover">
                            <thead class="table-primary">
                            <tr>
                                <th scope="col">Item</th>
                                <th scope="col">Mes</th>
                                <th scope="col">Año</th>
                                <th scope="col">Placas</th>
                                <th scope="col">Monto</th>
                                <th scope="col"  width="10" class="text-center">Modificar</th>
                            </tr>
                            </thead>
                            <tbody>

                            @foreach($result as $item)
                                <tr>
                                    <td>{{$loop->iteration}}</td>
                                    <td>{{$item->month}}</td>
                                    <td>{{$item->year}}</td>
                                    <td>{{$item->plates}}</td>
                                    <td>{{$item->amount}}</td>
                                    <td width="10" class="text-center"><i class="ti ti-edit f-s-18 text-success" style="cursor:pointer" wire:click="openDetail({{$item->year}},{{$item->month}})"></i></td>
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

    <div class="modal fade" id="modalGenerateCostPerPlate" aria-hidden="true" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Generar Costo por Placa</h5>
                    <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="type" class="form-label">Tipo</label>
                                <select class="form-select" id="type" wire:model="type">
                                    <option value="cp">Costo por Placa</option>
                                    <option value="prueba">Prueba</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Fecha</label>
                                <input type="date" class="form-control" wire:model="date">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-primary" wire:click="generate">Generar</button>
                    <button type="button" class="btn btn-light-secondary"
                            data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>
