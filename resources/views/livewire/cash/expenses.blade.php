<div class="container-fluid">

    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Egresos</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Caja</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Egresos</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-xl-6 col-md-6 mb-2 mb-md-0">
                            <form class="app-form app-icon-form" action="#">
                                <label class="form-label">Buscar: </label>
                                <div class="position-relative">

                                    <input type="search" class="form-control" placeholder="Buscar..."
                                           aria-label="Buscar" wire:model.live.debounce.400ms="search">
                                    <i class="ti ti-search text-dark"></i>
                                </div>
                            </form>
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <label class="form-label">Filtro</label>
                            <select class="form-select" aria-label="Estado del vehiculo" wire:model.live="filterType">
                                <option value="1">A</option>
                                <option value="2">Motivo</option>
                                <option value="3">Usuario</option>
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-4 mb-2 mb-md-0">
                            <label class="form-label">Fecha Inicio</label>
                            <input type="date" class="form-control" wire:model.live="date_start">
                        </div>
                        <div class="col-xl-2 col-md-4 mb-2 mb-md-0">
                            <label class="form-label">Fecha Fin</label>
                            <input type="date" class="form-control" wire:model.live="date_end">
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row justify-content-end g-2">
                    <div class="col-xl-2 col-md-4">
                        <button class="btn btn-primary w-100">
                            <i class="ti ti-file-analytics f-s-16"></i> Exportar
                        </button>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <button class="btn btn-primary w-100">
                            <i class="ti ti-square-plus f-s-16"></i> Nuevo
                        </button>
                    </div>
                    <div class="col-xl-1 col-md-4">
                        <button id="down" class="btn btn-primary w-100">
                            <i class="ti ti-square-chevrons-down f-s-17"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped table-hover">
                        <thead class="table-primary">
                        <tr>
                            <th>Op</th>
                            <th>Nº</th>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>A</th>
                            <th>Motivo</th>

                            <th class="text-center">T.Comp.</th>
                            <th class="text-center">Respons.</th>
                            <th class="text-end">Monto</th>
                        </tr>
                        </thead>

                        <tbody>
                        {{-- Spinner mientras Livewire refresca --}}
                        <tr wire:loading>
                            <td colspan="9" class="text-center">
                                <div class="d-flex justify-content-center align-items-center gap-2 py-3">
                                    <div class="spinner-border spinner-border-sm" role="status"
                                         aria-hidden="true"></div>
                                    <span>Cargando...</span>
                                </div>
                            </td>
                        </tr>

                        @forelse($expenses as $e)
                            <tr>
                                <td data-label="Opciones">
                                    <i wire:ignore class="ti ti-edit f-s-18 text-success" style="cursor:pointer" wire:click="openEditModal({{$e->id}})"></i>
                                </td>
                                <td>{{ $expenses->firstItem() + $loop->index }}</td>
                                <td data-label="Fecha">
                                    {{ \Carbon\Carbon::parse($e->date)->format('d/m/Y') }}
                                </td>
                                <td data-label="Usuario">
                                    {{ $e->user->name ?? '-' }}
                                </td>
                                <td data-label="A">
                                    {{ $e->reason }}
                                </td>
                                <td data-label="Motivo">
                                    {{ $e->detail }}
                                </td>

                                <td class="text-center" data-label="T.Comp.">
                                    {{ $e->document_type }}
                                </td>
                                <td class="text-center" data-label="Respons.">
                                    {{ $e->in_charge }}
                                </td>
                                <td class="text-end" data-label="S/.">
                                    {{ number_format($e->total, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr wire:loading.remove>
                                <td colspan="9" class="text-center">Sin resultados para los filtros seleccionados.</td>
                            </tr>
                        @endforelse
                        </tbody>

                        <tfoot>
                        <tr>
                            <td colspan="8" class="text-end f-fw-700">Total general</td>
                            <td class="text-end f-fw-700">{{ number_format($pageSum ?? 0, 2) }}</td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEditExpense" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-xl">
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
                                <label for="expense" class="form-label">Sede</label>
                                <input id="expense" type="text" class="form-control" placeholder="Ingresar sede"  >
                                @error('expense') <span class="text-danger">{{ $message }}</span> @enderror
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
