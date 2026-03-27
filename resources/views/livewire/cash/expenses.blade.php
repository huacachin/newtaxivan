{{-- resources/views/livewire/cash/expenses.blade.php --}}
@push('datepicker_css')
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>
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

    {{-- Flash alerts --}}
    @if(session('expense_success'))
        <div class="alert alert-success alert-dismissible fade show py-2 mb-2" role="alert">
            {{ session('expense_success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('expense_error'))
        <div class="alert alert-danger alert-dismissible fade show py-2 mb-2" role="alert">
            {{ session('expense_error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

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

                            <a href="{{ route('cash.expenses.create') }}"
                               class="btn btn-sm btn-success flex-shrink-0" target="_blank">
                                <i class="ti ti-square-plus f-s-12"></i> Nuevo
                            </a>

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
                            <th class="text-center">Img</th>
                        </tr>
                        </thead>

                        <tbody>


                        @forelse($expenses as $e)
                            <tr  wire:key="exp-{{ $e->id }}">
                                <td data-label="Opciones">
                                    @hasanyrole('director|gerente|administrador')
                                    <a href="{{ route('cash.expenses.edit', $e->id) }}">
                                        <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"></i>
                                    </a>
                                    @endhasanyrole
                                </td>
                                <td>{{ $expenses->firstItem() + $loop->index }}</td>
                                <td>{{ \Carbon\Carbon::parse($e->date)->format('d/m/Y') }}</td>
                                <td>{{ $e->user->name ?? '-' }}</td>
                                <td>{{ $e->reason }}</td>
                                <td>{{ $e->detail }}</td>
                                <td class="text-end">{{ number_format($e->total, 2) }}</td>
                                <td>{{ $e->document_type }}</td>
                                <td>{{ $e->in_charge }}</td>
                                <td class="text-center">
                                    @if($e->image_path)
                                        <i class="fa-solid fa-camera f-s-18 text-dark" style="cursor:pointer;"
                                           onclick="showLightbox('{{ asset('storage/'.$e->image_path) }}')"></i>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr  wire:key="exp-1">
                                <td colspan="10">Sin resultados para los filtros seleccionados.</td>
                            </tr>
                        @endforelse
                        </tbody>

                        <tfoot class="bg-primary">
                        <tr style="font-size: 1.3rem; color: #000 !important;">
                            <td colspan="9" class="text-end fw-bold" style="padding: 4px 8px !important;">Total General</td>
                            <td class="text-end fw-bold" style="padding: 4px 8px !important;">{{ number_format($totalGeneral ?? 0, 2) }}</td>
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

    {{-- Lightbox --}}
    <div class="modal fade" id="modalImageLightbox" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="background:rgba(0,0,0,0.85);">
                <div class="modal-header border-0 pb-0">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-2">
                    <img id="lightboxImg" src="" alt="Comprobante" class="img-fluid rounded" style="max-height:80vh;">
                </div>
            </div>
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

@push('scripts')
<script>
function showLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    new bootstrap.Modal(document.getElementById('modalImageLightbox')).show();
}
</script>
@endpush

@push('datepicker_js')
    <script>
        $( function() {
            var wire = @this;
            initLivewireDatepicker([
                ['#date_start', 'ui_date_start'],
                ['#date_end',   'ui_date_end'],
            ], wire);
        });
    </script>
@endpush
