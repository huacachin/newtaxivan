<div class="container-fluid">
    <!-- Basic Table start -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Costo por placa por Días</h4>
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
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-10 mb-2 mb-md-0">
                            <form class="app-form app-icon-form" action="#">
                                <div class="position-relative">
                                    <input type="text" class="form-control" placeholder="Buscar por placa"
                                           wire:model.live="plate">
                                    <i class="ti ti-abc text-dark"></i>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-2 mb-2 mb-md-0">
                            <button class="btn btn-primary w-100" wire:click="goBack"><i class="ti ti-arrow-back-up f-s-17"></i>
                                Regresar
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <!-- Simple Table start -->
        <div class="col-xl-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>Días</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped table-hover">
                            <thead class="table-primary">
                            <tr>
                                <th width="10">Item</th>
                                <th scope="col">Mes</th>
                                <th scope="col">Año</th>
                                <th scope="col">Placas</th>
                                <th scope="col">Monto ( {{$now->format("d/m/Y")}} ) </th>
                                <th scope="col"  width="10" class="text-center">Modificar</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if($result->count() > 0)
                                @foreach($result as $item)
                                    <tr>
                                        <td>{{$loop->iteration}}</td>
                                        <td>{{$item->month}}</td>
                                        <td>{{$item->year}}</td>
                                        <td>{{$item->plate}}</td>
                                        <td>{{$item->amount}}</td>
                                        <td width="10" class="text-center"><i class="ti ti-edit f-s-18 text-success" style="cursor:pointer" wire:click="openCalendar('{{$item->plate}}',{{$item->year}},{{$item->month}})"></i></td>
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

    </div>

</div>
