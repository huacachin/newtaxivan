{{-- resources/views/livewire/cost-per-plate/index.blade.php --}}
@push('styles')
    <style>
        /* ===== Estilo matriz (igual al de Pagos/Usuarios/Vehículos) ===== */
        .tableFixHead thead th{
            position: sticky; top: 0; z-index: 3;
            background-color:#009BDC !important; color:#fff !important;
            vertical-align: middle; text-align:center;
        }
        .tableFixHead tfoot th,
        .tableFixHead tfoot td{
            position: sticky; bottom: 0; z-index: 2;
            background-color:#009BDC !important; color:#fff !important;
            vertical-align: middle;
        }

        /* Zebra suave y ajustes */
        .tableFixHead table.table th,
        .tableFixHead table.table td{ white-space: nowrap; vertical-align: middle; }
        tbody tr:nth-child(even) td{ background-color:#f9fafb; }

        /* Sticky cols (Item + Mes) */
        :root{ --w-item:72px; --w-mes:140px; }
        .tableFixHead .sticky-col   { position: sticky; left: 0;             z-index: 4; width: var(--w-item); }
        .tableFixHead .sticky-col-2 { position: sticky; left: var(--w-item); z-index: 4; width: var(--w-mes); }
        .tableFixHead tbody td.sticky-col,
        .tableFixHead tbody td.sticky-col-2{
            background:#fff !important; background-clip: padding-box;
            box-shadow: 1px 0 0 rgba(0,0,0,.06) inset;
        }

        .num{ text-align: right; }
    </style>
@endpush

<div class="container-fluid">
    <!-- Header -->
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

    <div class="row table-section">
        <!-- Tabla -->
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0" style="color:#e11d48;">COSTO POR PLACA</h5>
                    <button class="btn btn-primary" data-bs-toggle="modal"
                            data-bs-target="#modalGenerateCostPerPlate">
                        <i class="ti ti-square-rounded-plus f-s-14"></i> Crear
                    </button>
                </div>
                <div class="card-body pb-2">
                    <div class="table-responsive tableFixHead">
                        <table class="table table-sm table-bordered table-striped table-hover align-middle">
                            <thead>
                            <tr>
                                <th class="sticky-col">Item</th>
                                <th class="sticky-col-2">Mes</th>
                                <th>Año</th>
                                <th>Placas</th>
                                <th>Monto</th>
                                <th width="10" class="text-center">Modificar</th>
                            </tr>
                            </thead>

                            <tbody>
                            @forelse($result as $item)
                                <tr>
                                    <td class="sticky-col text-center">{{ $loop->iteration }}</td>
                                    <td class="sticky-col-2 fw-semibold">{{ $item->month }}</td>
                                    <td>{{ $item->year }}</td>
                                    <td class="num">{{ number_format($item->plates) }}</td>
                                    <td class="num">{{ number_format($item->amount, 2) }}</td>
                                    <td class="text-center">
                                        <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"
                                           wire:click="openDetail({{ $item->year }}, {{ $item->month }})"></i>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="text-center py-4 text-muted" colspan="6">No se encontraron resultados</td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="fw-semibold">
                            <tr>
                                <td class="sticky-col"></td>
                                <td class="sticky-col-2 text-start">TOTAL</td>
                                <td></td>
                                <td class="num">
                                    {{ number_format(collect($result)->sum('plates')) }}
                                </td>
                                <td class="num">
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

    {{-- Modal: Generar --}}
    <div class="modal fade" id="modalGenerateCostPerPlate" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Generar Costo por Placa</h5>
                    <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="type" class="form-label">Tipo</label>
                            <select class="form-select" id="type" wire:model="type">
                                <option value="cp">Costo por Placa</option>
                                <option value="prueba">Prueba</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha</label>
                            <input type="date" class="form-control" wire:model="date">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-primary" wire:click="generate">Generar</button>
                    <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>
