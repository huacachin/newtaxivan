{{-- resources/views/livewire/cost-per-plate/days.blade.php --}}
@push('styles')
    <style>
        table {
            border-collapse: collapse; /* opcional */
            width: 100%;
        }

        th,td{
            padding: 3px !important;
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
            <h4 class="main-title">Costo por placa por Días</h4>
            <small class="text-muted">Detalle por placa</small>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Configuración</span></a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Costo por placa</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <!-- Filtros -->
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body pt-3">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-10 col-6">
                            <form class="app-form app-icon-form" action="#">
                                <div class="position-relative">
                                    <input type="text" class="form-control" placeholder="Buscar por placa"
                                           wire:model.live="plate" aria-label="Buscar por placa">
                                    <i class="ti ti-abc text-dark"></i>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-2 col-6 d-grid">
                            <button class="btn btn-sm btn-primary" wire:click="goBack">
                                <i class="ti ti-arrow-back-up f-s-17"></i> Regresar
                            </button>
                        </div>
                    </div>

                    <div class="mt-2" wire:loading.delay>
                        <span class="text-muted">
                            <span class="spinner-border spinner-border-sm"></span> Cargando…
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0" style="color:#e11d48;">
                        DÍAS — Monto al {{ $now->format('d/m/Y') }}
                    </h5>
                </div>
                <div class="card-body pb-2">
                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>Item</th>
                                <th>Mes</th>
                                <th>Año</th>
                                <th>Placa</th>
                                <th>Monto ({{ $now->format('d/m/Y') }})</th>
                                <th width="10" >Modificar</th>
                            </tr>
                            </thead>

                            <tbody>
                            @if($result->count() > 0)
                                @foreach($result as $item)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $item->month }}</td>
                                        <td>{{ $item->year }}</td>
                                        <td>{{ $item->plate }}</td>
                                        <td>{{ number_format($item->amount, 2) }}</td>
                                        <td>
                                            <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"
                                               wire:click="openCalendar('{{ $item->plate }}', {{ $item->year }}, {{ $item->month }})"></i>
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="6" class="py-4 text-muted">No se encontraron resultados</td>
                                </tr>
                            @endif
                            </tbody>

                            <tfoot class="bg-primary">
                            <tr>
                                <td></td>
                                <td>TOTAL</td>
                                <td></td>
                                <td>
                                    {{-- cantidad de registros (placas listadas) --}}
                                    {{ $result->count() }} registro{{ $result->count() === 1 ? '' : 's' }}
                                </td>
                                <td class="num">
                                    {{ number_format($result->sum('amount'), 2) }}
                                </td>
                                <td></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
