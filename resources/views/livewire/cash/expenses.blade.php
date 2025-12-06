{{-- resources/views/livewire/cash/expenses.blade.php --}}
@push('datepicker_css')
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>
@endpush
@push('styles')
    <style>

        table {
            border-collapse: collapse; /* opcional */
            width: 100%;
        }

        th, td {
            padding: 1px !important;
            font-size: 10px !important;
            text-align: center !important;
            vertical-align: middle; /* <-- clave */
        }

        .btn, input, select {
            font-size: 10px !important;
        }

        .screen-overlay {
            position: fixed;
            inset: 0;                 /* full viewport */
            display: none;            /* Livewire lo pondrá en flex */
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,.35);
            backdrop-filter: blur(2px);
            z-index: 2000;            /* sobre modals/backdrops de Bootstrap */
            pointer-events: all;      /* bloquea clics */
        }

        #date_start, #date_end{
            background: url({{asset('images/calen.png')}}) #fff no-repeat right;
            background-size: 21px 16px;
            padding-right: 2rem;
        }


    </style>
@endpush
<div class="container-fluid">

    {{-- Header --}}
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">EGRESOS</h4>
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

    {{-- Tabla --}}
    <div class="col-xl-12">
        <div class="card">

            <div class="card-body">
                <div class="row my-2 g-2">
                    {{-- Fila 1: Inputs --}}
                    <div class="col-12">
                        <div class="d-flex flex-wrap align-items-end gap-2 py-1">

                            <!-- Buscar -->
                            <div class="flex-shrink-0">
                                <div class="row g-2 mb-2">
                                    <div class="col-12 f-s-11">
                                        <div class="d-flex flex-wrap align-items-center gap-3">
                                            <span class="small text-muted">Buscar:</span>

                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input mg-e-4"
                                                       type="radio"
                                                       name="rbFilter"
                                                       id="rbA"
                                                       value="1"
                                                       wire:model.live="filterType">  {{-- sin .live ni wire:click --}}
                                                <label class="form-check-label" for="rbA">A</label>
                                            </div>

                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input mg-e-4"
                                                       type="radio"
                                                       name="rbFilter"
                                                       id="rbMotive"
                                                       value="2"
                                                       wire:model.live="filterType">
                                                <label class="form-check-label" for="rbMotive">Motivo</label>
                                            </div>

                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input mg-e-4"
                                                       type="radio"
                                                       name="rbFilter"
                                                       id="rbUser"
                                                       value="3"
                                                       wire:model.live="filterType">
                                                <label class="form-check-label" for="rbUser">Usuario</label>
                                            </div>

                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input mg-e-4"
                                                       type="radio"
                                                       name="rbFilter"
                                                       id="rbRespons"
                                                       value="4"
                                                       wire:model.live="filterType">
                                                <label class="form-check-label" for="rbRespons">Respons.</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <input
                                    type="search"
                                    class="form-control form-control-sm"
                                    placeholder="Buscar..."
                                    aria-label="Buscar"
                                    wire:model.live.debounce.400ms="search">
                            </div>

                            <!-- Fecha Inicio -->
                            <div class="flex-shrink-0" >
                                <label class="form-label mb-1">Fecha Inicio</label>
                                <input type="text" id="date_start" class="form-control form-control-sm" wire:ignore wire:model="ui_date_start">
                            </div>

                            <!-- Fecha Fin -->
                            <div class="flex-shrink-0">
                                <label class="form-label mb-1">Fecha Fin</label>
                                <input type="text" id="date_end"  class="form-control form-control-sm" wire:ignore wire:model="ui_date_end">
                            </div>

                        </div>
                    </div>

                    {{-- Fila 2: Botones --}}
                    <div class="col-12">
                        <div class="d-flex flex-wrap gap-2 justify-content-start py-1">

                            <button class="btn btn-sm btn-search flex-shrink-0" wire:click="applyDate">
                                <i class="ti ti-search f-s-12"></i> Buscar
                            </button>

                            <button class="btn btn-sm btn-success flex-shrink-0" wire:click="openCreateModal">
                                <i class="ti ti-square-plus f-s-12"></i> Nuevo
                            </button>

                            <button class="btn btn-sm btn-primary flex-shrink-0" wire:click="export">
                                <i class="ti ti-file-analytics f-s-12"></i> Excel
                            </button>



                            <button id="down" type="button" class="btn btn-sm btn-primary flex-shrink-0">
                                <i class="fa-solid fa-angle-down"></i>
                            </button>

                        </div>
                    </div>
                </div>
                <div class="table-responsive">

                    <table class="table table-bordered table-striped table-hover">
                        <thead class="bg-primary">
                        <tr>
                            <th>Op</th>
                            <th>Nº</th>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>A</th>
                            <th>Motivo</th>
                            <th class="text-end">Monto</th>
                            <th>T.Comp.</th>
                            <th>Respons.</th>

                        </tr>
                        </thead>

                        <tbody>


                        @forelse($expenses as $e)
                            <tr  wire:key="exp-{{ $e->id }}">
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
                                <td class="text-end">{{ number_format($e->total, 2) }}</td>
                                <td>{{ $e->document_type }}</td>
                                <td>{{ $e->in_charge }}</td>

                            </tr>
                        @empty
                            <tr  wire:key="exp-1">
                                <td colspan="9">Sin resultados para los filtros seleccionados.</td>
                            </tr>
                        @endforelse
                        </tbody>

                        <tfoot class="bg-primary">
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

                        <div class="col-md-12">
                            <label class="form-label">Comprobante (imagen)</label>
                            <input type="file" class="form-control" wire:model="image_file" accept="image/*">
                            @error('image_file') <div class="title-modules">{{ $message }}</div> @enderror

                            <div class="mt-2">
                                @if ($image_file)
                                    {{-- Vista previa temporal si el usuario selecciona nueva imagen --}}
                                    <img src="{{ $image_file->temporaryUrl() }}" alt="Vista previa" class="img-fluid rounded border" style="max-height: 220px;">
                                @else
                                    @php
                                        $p = $image_path;
                                        $exists = $p && \Illuminate\Support\Facades\Storage::disk('public')->exists($p);
                                        $url = $exists ? asset('storage/'.$p) : asset('images/placeholder-income.png');
                                    @endphp
                                    <img src="{{ $url }}" alt="Comprobante" class="img-fluid rounded border" style="max-height: 220px;">
                                @endif
                            </div>
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

    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="export,applyDate,filterType">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>

</div>

@push('styles')
    <style>
        /* Un toque más compacto para listas largas */
        .table td, .table th { vertical-align: middle; }
    </style>
@endpush

@push('datepicker_js')
    <script>
        $( function() {
            $( "#date_start" ).datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: 'yy-mm-dd',
                onSelect: function (dateText, inst) {
                    @this.set('ui_date_start', dateText);
                }
            });

            $( "#date_end" ).datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: 'yy-mm-dd',
                onSelect: function (dateText, inst) {
                    @this.set('ui_date_end', dateText);
                }
            });
        });
    </script>
@endpush
