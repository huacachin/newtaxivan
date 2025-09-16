<div class="container-fluid">
    <!-- Basic Table start -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Eliminar Deudas, Salidas y Pagos</h4>
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
                    <a href="#" class="f-s-14">Eliminar Deudas, Salidas y Pagos.</a>
                </li>
            </ul>
        </div>
    </div>
    <!-- Basic Table end -->
    <div class="row">
        <!-- Simple Table start -->
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="type" class="form-label">Tipo</label>
                                <select class="form-select" id="type" wire:model.live="type">
                                    <option value="">Selecciona un tipo</option>
                                    <option value="pago">Pago</option>
                                    <option value="salida">Salida</option>
                                    <option value="deuda">Deuda</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="user" class="form-label">Usuario</label>
                                <select class="form-select" id="user" wire:model="user">
                                    <option value="">Selecciona un usuario</option>
                                    <option value="1">Elmer</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="headquarter" class="form-label">Sucursal</label>
                                <select class="form-select" id="headquarter" wire:model="headquarter">
                                    <option value="">Selecciona una sucursal</option>
                                    <option value="1">Huaycan</option>
                                    <option value="2">La Victoria</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Fecha</label>
                                <input type="date" class="form-control" wire:model.live="date">
                            </div>
                        </div>

                    </div>
                </div>
                <div class="card-footer text-end">
                    <button type="button" class="btn btn-primary" wire:click="delete">Eliminar</button>
                </div>
            </div>
        </div>
        <!-- Simple Table end -->

    </div>
</div>
