{{-- resources/views/livewire/cost-per-plate/index.blade.php --}}
@push('styles')
    <style>
        table {
            border-collapse: collapse; /* opcional */
            width: 100%;
        }

        th,td{
            padding: 1px !important;
            font-size: 10px !important;
            text-align: center !important;
            vertical-align: middle;   /* <-- clave */
        }

        .btn, input,select {
            font-size: 10px !important;
        }
    </style>
@endpush

<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title text-danger">LISTADO GENERAL DE COSTO POR PLACA</h4>
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

    <div class="row table-section">
        <!-- Tabla -->
        <div class="col-xl-12">
            <div class="card shadow-sm">

                <div class="card-body pb-2">
                    <div class="my-2 d-flex align-items-center justify-content-end">

                        @role('admin')
                        <button class="btn btn-sm btn-primary" wire:click="questionGenerate">
                            <i class="ti ti-square-rounded-plus f-s-12"></i> Generar
                        </button>
                        @endrole
                    </div>
                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>Item</th>
                                <th>Mes</th>
                                <th>Año</th>
                                <th>Placas</th>
                                <th>Monto</th>
                                <th width="10" >Modificar</th>
                            </tr>
                            </thead>

                            <tbody>
                            @forelse($result as $item)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $item->month }}</td>
                                    <td>{{ $item->year }}</td>
                                    <td>{{ number_format($item->plates) }}</td>
                                    <td>{{ number_format($item->amount, 2) }}</td>
                                    <td>
                                        <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"
                                           wire:click="openDetail({{ $item->year }}, {{ $item->month }})"></i>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-4 text-muted" colspan="6">No se encontraron resultados</td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="bg-primary">
                            <tr>
                                <td></td>
                                <td>TOTAL</td>
                                <td></td>
                                <td>
                                    {{ number_format(collect($result)->sum('plates')) }}
                                </td>
                                <td>
                                    {{ number_format(collect($result)->sum('amount'), 2) }}
                                </td>
                                <td></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="mt-2" wire:loading.delay>
                        <span class="text-muted">
                            <span class="spinner-border spinner-border-sm"></span> Cargando…
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
