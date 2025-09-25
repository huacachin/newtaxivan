{{-- resources/views/livewire/cash/expenses.blade.php --}}
<div class="container-fluid">

    {{-- Header --}}
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

    {{-- Filtros --}}
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-xl-6 col-md-6">
                            <form class="app-form app-icon-form" action="#">
                                <label class="form-label">Buscar</label>
                                <div class="position-relative">
                                    <input
                                        type="search"
                                        class="form-control"
                                        placeholder="Buscar..."
                                        aria-label="Buscar"
                                        wire:model.live.debounce.400ms="search"
                                    >
                                    <i class="ti ti-search text-dark"></i>
                                </div>
                            </form>
                        </div>

                        <div class="col-xl-2 col-md-4">
                            <label class="form-label">Filtro</label>
                            <select class="form-select" wire:model.live="filterType">
                                <option value="1">A</option>
                                <option value="2">Motivo</option>
                                <option value="3">Usuario</option>
                                <option value="4">Respons.</option>
                            </select>
                        </div>

                        <div class="col-xl-2 col-md-4">
                            <label class="form-label">Fecha Inicio</label>
                            <input type="date" class="form-control" wire:model.live="date_start">
                        </div>

                        <div class="col-xl-2 col-md-4">
                            <label class="form-label">Fecha Fin</label>
                            <input type="date" class="form-control" wire:model.live="date_end">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Acciones --}}
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row justify-content-end g-2">
                    <div class="col-xl-2 col-md-4">
                        <button class="btn btn-primary w-100" wire:click="export">
                            <i class="ti ti-file-analytics f-s-16"></i> Exportar
                        </button>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <button class="btn btn-primary w-100" wire:click="openCreateModal">
                            <i class="ti ti-square-plus f-s-16"></i> Nuevo
                        </button>
                    </div>
                    <div class="col-xl-1 col-md-4">
                        <button id="down" class="btn btn-primary w-100" type="button">
                            <i class="ti ti-square-chevrons-down f-s-17"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabla --}}
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
                                    <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
                                    <span>Cargando...</span>
                                </div>
                            </td>
                        </tr>

                        @forelse($expenses as $e)
                            <tr wire:loading.remove>
                                <td data-label="Opciones">
                                    <i class="ti ti-edit f-s-18 text-success"
                                       style="cursor:pointer"
                                       wire:click="openEditModal({{ $e->id }})"></i>
                                </td>
                                <td>{{ $expenses->firstItem() + $loop->index }}</td>
                                <td>{{ \Carbon\Carbon::parse($e->date)->format('d/m/Y') }}</td>
                                <td>{{ $e->user->name ?? '-' }}</td>
                                <td>{{ $e->reason }}</td>
                                <td>{{ $e->detail }}</td>
                                <td class="text-center">{{ $e->document_type }}</td>
                                <td class="text-center">{{ $e->in_charge }}</td>
                                <td class="text-end">{{ number_format($e->total, 2) }}</td>
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
                            <td class="text-end f-fw-700">{{ number_format($totalGeneral ?? 0, 2) }}</td>
                        </tr>
                        </tfoot>
                    </table>

                </div>
            </div>
        </div>
    </div>

    {{-- Modal: Crear / Editar --}}
    <div class="modal fade" id="modalExpense" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form class="modal-content" wire:submit.prevent="save">
                <div class="modal-header">
                    <h5 class="modal-title">
                        {{ $editId ? 'Editar egreso' : 'Nuevo egreso' }}
                    </h5>
                    <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-4">
                            <label class="form-label">Fecha</label>
                            <input type="date" class="form-control @error('date') is-invalid @enderror"
                                   wire:model.defer="date">
                            @error('date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Tipo de egreso</label>
                            <select class="form-select @error('expenseKind') is-invalid @enderror"
                                    wire:model.live="expenseKind">
                                <option value="Otros">Otros</option>
                                <option value="Fijos">Fijos</option>
                            </select>
                            @error('expenseKind') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Campo A: depende de expenseKind --}}
                        <div class="col-md-4">
                            <label class="form-label">A</label>

                            @if($expenseKind === 'Fijos')
                                <select class="form-select @error('concept_id') is-invalid @enderror"
                                        wire:model.live="concept_id">
                                    <option value="">-- Seleccionar concepto --</option>
                                    @foreach($concepts as $c)
                                        <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('concept_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @else
                                <input type="text" class="form-control @error('reason_text') is-invalid @enderror"
                                       placeholder="Ej: Combustible, Servicio, etc."
                                       wire:model.defer="reason_text">
                                @error('reason_text') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @endif
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">Motivo / Detalle</label>
                            <input type="text" class="form-control @error('detail') is-invalid @enderror"
                                   placeholder="Descripción breve"
                                   wire:model.defer="detail">
                            @error('detail') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Monto (S/)</label>
                            <input type="number" step="0.01" min="0"
                                   class="form-control @error('total') is-invalid @enderror"
                                   wire:model.defer="total">
                            @error('total') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">T. Comprobante</label>
                            <input type="text" class="form-control @error('document_type') is-invalid @enderror"
                                   placeholder="Factura, Boleta, etc."
                                   wire:model.defer="document_type">
                            @error('document_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Responsable</label>
                            <select class="form-select"  wire:model.defer="in_charge">
                                <option value="">-- Seleccionar responsable --</option>
                                @foreach($users as $i => $u)
                                    <option value="{{ $u }}">{{ $u }}</option>
                                @endforeach
                            </select>
                            @error('in_charge') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">
                        Guardar
                    </button>
                    <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">
                        Cerrar
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

@push('styles')
    <style>
        /* Un toque más compacto para listas largas */
        .table td, .table th { vertical-align: middle; }
    </style>
@endpush

@push('scripts')
    <script>
        // Botón "ir abajo"
        document.addEventListener('livewire:load', () => {
            const btn = document.getElementById('down');
            if (btn) {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
                });
            }
        });
    </script>
@endpush
