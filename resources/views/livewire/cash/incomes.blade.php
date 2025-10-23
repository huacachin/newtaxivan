@push('styles')
    <style>

        table {
            border-collapse: collapse; /* opcional */
            width: 100%;
        }

        th, td {
            padding: 3px !important;
            font-size: 10px !important;
            text-align: center !important;
            vertical-align: middle; /* <-- clave */
        }

        .btn, input, select {
            font-size: 10px !important;
        }

        .screen-overlay {
            position: fixed;
            inset: 0; /* full viewport */
            display: none; /* Livewire lo pondrá en flex */
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, .35);
            backdrop-filter: blur(2px);
            z-index: 2000; /* sobre modals/backdrops de Bootstrap */
            pointer-events: all; /* bloquea clics */
        }

        .bg-foot {
            background: #009BDC;
        }
    </style>
@endpush
<div class="container-fluid">

    {{-- ===== Header & Breadcrumb ===== --}}
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Ingresos</h4>
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
                    <a href="#" class="f-s-14">Ingresos</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row">

        {{-- ===== Table ===== --}}
        <div class="col-xl-12">
            <div class="card">
                <div class="card-header">

                    <div class="row g-3">
                        <div class="col-md-4 col-4 ">

                            <label class="form-label">Buscar: </label>

                            <input type="search" class="form-control" placeholder="Buscar..." aria-label="Buscar"
                                   wire:model.live.debounce.400ms="search">


                        </div>

                        <div class="col-md-2 col-4">
                            <label class="form-label">Filtro</label>
                            <select class="form-select" aria-label="Filtro" wire:model.live="filterType">
                                <option value="1">A</option>
                                <option value="2">Motivo</option>
                                <option value="3">Usuario</option>
                            </select>
                        </div>

                        <div class="col-md-3 col-4">
                            <label class="form-label">Fecha Inicio</label>
                            <input type="date" class="form-control" wire:model="date_start">
                        </div>
                        <div class="col-md-3 col-4">
                            <label class="form-label">Fecha Fin</label>
                            <input type="date" class="form-control" wire:model="date_end">
                        </div>


                        <div class="col-md-3 col-4 d-flex align-items-end">
                            <button class="btn btn-sm btn-primary w-100" wire:click="applyDate">
                                <i class="ti ti-search f-s-12"></i> Buscar
                            </button>
                        </div>
                        <div class="col-md-3 col-4 d-flex align-items-end">
                            <button class="btn btn-sm btn-primary w-100" wire:click="export">
                                <i class="ti ti-file-analytics f-s-12"></i> Exportar
                            </button>
                        </div>
                        <div class="col-md-3 col-6 d-flex align-items-end">
                            <button class="btn btn-sm btn-primary w-100" wire:click="openAddModal">
                                <i class="ti ti-square-plus f-s-12"></i> Nuevo
                            </button>
                        </div>
                        <div class="col-md-3 col-6 d-flex align-items-end">
                            <button id="down" class="btn btn-sm btn-primary w-100" wire:click="downloadLast">
                                <i class="ti ti-square-chevrons-down f-s-12"></i>
                            </button>
                        </div>
                    </div>

                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>Op</th>
                                <th>Item</th>
                                <th>Fecha</th>
                                <th>Usuario</th>
                                <th>A</th>
                                <th>Motivo</th>
                                <th class="text-end">Monto</th>
                            </tr>
                            </thead>
                            <tbody>

                            @forelse($incomes as $i)
                                <tr>
                                    <td data-label="Opciones">
                                        <i wire:ignore class="ti ti-edit f-s-18 text-success" style="cursor:pointer"
                                           wire:click="openEditModal({{ $i->id }})"></i>
                                    </td>
                                    <td>{{ $incomes->firstItem() + $loop->index }}</td>
                                    <td data-label="Fecha">{{ \Carbon\Carbon::parse($i->date)->format('d/m/Y') }}</td>
                                    <td data-label="Usuario">{{ $i->user->name ?? '-' }}</td>
                                    <td data-label="A">{{ $i->reason }}</td>
                                    <td data-label="Motivo">{{ $i->detail }}</td>
                                    <td class="text-end" data-label="S/">{{ number_format($i->total, 2) }}</td>
                                </tr>
                            @empty
                                <tr wire:loading.remove>
                                    <td colspan="7">Sin resultados para los filtros seleccionados.</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                            <tr>
                                <td colspan="6" class="f-fw-700 text-end">Total General</td>
                                <td>{{ number_format($totalGeneral, 2) }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>


                </div>
            </div>
        </div>

    </div>

    {{-- ===== Modal: Nuevo Ingreso ===== --}}
    <div class="modal fade" id="modalAddIncome" aria-hidden="true" tabindex="-1" data-bs-backdrop="static"
         wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo ingreso</h5>
                    <button type="button" class="btn-close btn-close-white m-0 fs-5" data-bs-dismiss="modal"
                            aria-label="Close" wire:click="closeModal('modalAddIncome')"></button>
                </div>

                <div class="modal-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <strong>Corrige los siguientes errores:</strong>
                            <ul class="mb-0 mt-2 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Fecha</label>
                            <input type="date" class="form-control" wire:model.live="date">
                            @error('date') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Moneda</label>
                            <select class="form-select" wire:model.live="currency">
                                <option value="Soles">Soles</option>
                                <option value="Dolares">Dólares</option>
                            </select>
                            @error('currency') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Monto</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" placeholder="0.00"
                                   wire:model.live="amount_input">
                            @error('amount_input') <span class="text-danger">{{ $message }}</span> @enderror
                            @if(!is_null($converted_total))
                                <small class="text-muted">Total en S/: {{ number_format($converted_total, 2) }}</small>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">A</label>
                            <input type="text" class="form-control" placeholder="A quién / Área"
                                   wire:model.defer="reason">
                            @error('reason') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Motivo</label>
                            <input type="text" class="form-control" placeholder="Detalle" wire:model.defer="detail">
                            @error('detail') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Comprobante (imagen)</label>
                            <input type="file" class="form-control" wire:model="image_file" accept="image/*">
                            @error('image_file') <span class="text-danger">{{ $message }}</span> @enderror

                            {{-- Vista previa si el usuario ya seleccionó una imagen --}}
                            @if ($image_file)
                                <div class="mt-2">
                                    <img src="{{ $image_file->temporaryUrl() }}" alt="Vista previa"
                                         class="img-fluid rounded border" style="max-height: 220px;">
                                </div>
                            @endif
                        </div>

                        <div class="col-12">
                            <div class="form-text">TC usado (MVP): 3.80</div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light-secondary" data-bs-dismiss="modal"
                            wire:click="closeModal('modalAddIncome')">Cerrar
                    </button>
                    <button type="button" class="btn btn-sm btn-light-primary" wire:click="save">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Modal: Editar Ingreso ===== --}}
    <div class="modal fade" id="modalEditIncome" aria-hidden="true" tabindex="-1" data-bs-backdrop="static"
         wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar ingreso</h5>
                    <button type="button" class="btn-close btn-close-white m-0 fs-5" data-bs-dismiss="modal"
                            aria-label="Close" wire:click="closeModal('modalEditIncome')"></button>
                </div>

                <div class="modal-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <strong>Corrige los siguientes errores:</strong>
                            <ul class="mb-0 mt-2 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Fecha</label>
                            <input type="date" class="form-control" wire:model.live="date">
                            @error('date') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Moneda</label>
                            <select class="form-select" wire:model.live="currency">
                                <option value="Soles">Soles</option>
                                <option value="Dolares">Dólares</option>
                            </select>
                            @error('currency') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Monto</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" placeholder="0.00"
                                   wire:model.live="amount_input">
                            @error('amount_input') <span class="text-danger">{{ $message }}</span> @enderror
                            @if(!is_null($converted_total))
                                <small class="text-muted">Total en S/: {{ number_format($converted_total, 2) }}</small>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">A</label>
                            <input type="text" class="form-control" placeholder="A quién / Área"
                                   wire:model.defer="reason">
                            @error('reason') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Motivo</label>
                            <input type="text" class="form-control" placeholder="Detalle" wire:model.defer="detail">
                            @error('detail') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Comprobante (imagen)</label>
                            <input type="file" class="form-control" wire:model="image_file" accept="image/*">
                            @error('image_file') <span class="text-danger">{{ $message }}</span> @enderror

                            <div class="mt-2">
                                @if ($image_file)
                                    {{-- Si el usuario acaba de seleccionar una nueva imagen, preview temporal --}}
                                    <img src="{{ $image_file->temporaryUrl() }}" alt="Vista previa"
                                         class="img-fluid rounded border" style="max-height: 220px;">
                                @else
                                    @php
                                        // Usa el disco 'public' y arma la URL con asset('storage/...') para evitar problemas de APP_URL/subcarpetas
                                        $path = $image_path;
                                        $exists = $path && \Illuminate\Support\Facades\Storage::disk('public')->exists($path);
                                        $url = $exists ? asset('storage/'.$path) : asset('images/placeholder-income.png');
                                    @endphp
                                    <img src="{{ $url }}" alt="Comprobante" class="img-fluid rounded border"
                                         style="max-height: 220px;">
                                @endif
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-text">TC usado (MVP): 3.80</div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light-secondary" data-bs-dismiss="modal"
                            wire:click="closeModal('modalEditIncome')">Cerrar
                    </button>
                    <button type="button" class="btn btn-sm btn-light-primary" wire:click="update">Guardar cambios</button>
                </div>
            </div>
        </div>
    </div>
    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="export,applyDate">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>

</div>


