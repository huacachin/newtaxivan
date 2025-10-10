{{-- resources/views/livewire/concepts/index.blade.php --}}
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
        }

        /* Zebra suave y ajustes */
        .tableFixHead table.table th,
        .tableFixHead table.table td{ white-space: nowrap; vertical-align: middle; }
        tbody tr:nth-child(even) td{ background-color:#f9fafb; }

        /* Sticky cols (Id + Nombre) */
        :root{ --w-id:72px; --w-name:240px; }
        .tableFixHead .sticky-col   { position: sticky; left: 0;             z-index: 4; width: var(--w-id); }
        .tableFixHead .sticky-col-2 { position: sticky; left: var(--w-id);   z-index: 4; width: var(--w-name); }
        .tableFixHead tbody td.sticky-col,
        .tableFixHead tbody td.sticky-col-2{
            background:#fff !important; background-clip: padding-box;
            box-shadow: 1px 0 0 rgba(0,0,0,.06) inset;
        }

        .text-start{ text-align: left !important; }
        .num{ text-align: right; }
    </style>
@endpush

<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Conceptos</h4>
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
                    <a href="#" class="f-s-14">Conceptos</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">

        <!-- Tabla -->
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0" style="color:#e11d48;">LISTADO DE CONCEPTOS</h5>

                    <div class="row g-3 align-items-end mt-2">
                        <div class="col-md-10">
                            <form class="app-form app-icon-form" action="#" onsubmit="return false;">
                                <div class="position-relative">
                                    <input type="search" class="form-control" placeholder="Buscar..."
                                           aria-label="Buscar" wire:model.live="search">
                                    <i class="ti ti-search text-dark"></i>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-2 d-flex justify-content-md-end">
                            <button class="btn btn-primary w-100" wire:click="openAddModal">
                                <i class="ti ti-square-plus f-s-17"></i> Nuevo
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body pb-2">
                    <div class="table-responsive tableFixHead">
                        <table class="table table-sm table-bordered table-striped table-hover align-middle">
                            <thead>
                            <tr>
                                <th class="sticky-col">Id</th>
                                <th>Código</th>
                                <th class="sticky-col-2">Nombre</th>
                                <th>Tipo</th>
                                <th>Acción</th>
                            </tr>
                            </thead>

                            <tbody>
                            @if($concepts->count() > 0)
                                @foreach($concepts as $concept)
                                    <tr>
                                        <td class="sticky-col text-center">{{ $loop->iteration }}</td>
                                        <td>{{ $concept->code }}</td>
                                        <td class="sticky-col-2 text-start fw-semibold">{{ $concept->name }}</td>
                                        <td>{{ ucfirst($concept->type) }}</td>
                                        <td class="text-center">
                                            <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"
                                               wire:click="openEditModal({{ $concept->id }})"></i>
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td class="text-center py-4 text-muted" colspan="5">No se encontraron resultados</td>
                                </tr>
                            @endif
                            </tbody>

                            <tfoot class="fw-semibold">
                            <tr>
                                <td class="sticky-col"></td>
                                <td class="text-start">TOTAL</td>
                                <td class="sticky-col-2"></td>
                                <td colspan="2" class="num">{{ number_format($concepts->count()) }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="mt-2" wire:loading.delay>
                        <span class="text-muted">
                            <span class="spinner-border spinner-border-sm"></span> Actualizando…
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- MODAL: AGREGAR --}}
        <div class="modal fade" id="modalAddConcept" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Agregar Concepto</h5>
                        <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="code" class="form-label">Código</label>
                                    <input id="code" type="text" class="form-control" placeholder="Ingresar Código" wire:model="code">
                                    @error('code') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nombre del Concepto</label>
                                    <input id="name" type="text" class="form-control" placeholder="Ingresar Nombre del Concepto" wire:model="name">
                                    @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Estado</label>
                                    <select class="form-select" id="status" wire:model="status">
                                        <option value="inactive">Cancelado</option>
                                        <option value="active">Vigente</option>
                                    </select>
                                    @error('status') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="type" class="form-label">Tipo</label>
                                    <select class="form-select" id="type" wire:model="type">
                                        <option value="ingreso">Ingreso</option>
                                        <option value="egreso">Egreso</option>
                                    </select>
                                    @error('type') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light-primary" wire:click="save">Agregar</button>
                        <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- MODAL: EDITAR --}}
        <div class="modal fade" id="modalEditConcept" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar Concepto</h5>
                        <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="code_e" class="form-label">Código</label>
                                    <input id="code_e" type="text" class="form-control" placeholder="Ingresar Código" wire:model="code">
                                    @error('code') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name_e" class="form-label">Nombre del Concepto</label>
                                    <input id="name_e" type="text" class="form-control" placeholder="Ingresar Nombre del Concepto" wire:model="name">
                                    @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status_e" class="form-label">Estado</label>
                                    <select class="form-select" id="status_e" wire:model="status">
                                        <option value="inactive">Cancelado</option>
                                        <option value="active">Vigente</option>
                                    </select>
                                    @error('status') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="type_e" class="form-label">Tipo</label>
                                    <select class="form-select" id="type_e" wire:model="type">
                                        <option value="ingreso">Ingreso</option>
                                        <option value="egreso">Egreso</option>
                                    </select>
                                    @error('type') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        {{-- <button type="button" class="btn btn-light-primary" wire:click="update">Agregar</button> --}}
                        <button type="button" class="btn btn-light-primary" wire:click="update">Actualizar</button>
                        <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
