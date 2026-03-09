@push('datepicker_css')
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>
@endpush
<div class="container-fluid">

    {{-- ===== Header & Breadcrumb ===== --}}
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">LISTA GENERAL DE INGRESO</h4>
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

    {{-- Flash alerts --}}
    @if(session('income_success'))
        <div class="alert alert-success alert-dismissible fade show py-2 mb-2" role="alert">
            {{ session('income_success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('income_error'))
        <div class="alert alert-danger alert-dismissible fade show py-2 mb-2" role="alert">
            {{ session('income_error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">

        {{-- ===== Table ===== --}}
        <div class="col-xl-12">
            <div class="card">

                <div class="card-body">
                    <div class="row my-2">
                        <div class="col-12">
                            <div class="row g-2">
                                <!-- Fila 1: Inputs -->
                                <div class="col-12">
                                    <div class="d-flex flex-wrap align-items-end gap-2 py-1">

                                        <!-- Buscar -->
                                        <div class="flex-shrink-0" style="min-width: 220px;">
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
                                        <div class="flex-shrink-0" style="min-width: 160px;">
                                            <label class="form-label mb-1">Fecha Inicio</label>
                                            <input type="text" wire:ignore id="date_start" class="form-control form-control-sm" wire:model="ui_date_start">
                                        </div>

                                        <!-- Fecha Fin -->
                                        <div class="flex-shrink-0" style="min-width: 160px;">
                                            <label class="form-label mb-1">Fecha Fin</label>
                                            <input type="text" wire:ignore id="date_end" class="form-control form-control-sm" wire:model="ui_date_end">
                                        </div>

                                    </div>
                                </div>

                                <!-- Fila 2: Botones -->
                                <div class="col-12">
                                    <div class="d-flex flex-wrap gap-2 justify-content-start py-1">
                                        <!-- Buscar -->
                                        <button class="btn btn-sm btn-search flex-shrink-0"
                                                wire:click="applyDate">
                                            <i class="ti ti-search f-s-12"></i> Buscar
                                        </button>

                                        <!-- Nuevo -->
                                        <a href="{{ route('cash.incomes.create') }}"
                                           class="btn btn-sm btn-success flex-shrink-0">
                                            <i class="ti ti-square-plus f-s-12"></i> Nuevo
                                        </a>

                                        <!-- Exportar -->
                                        <button class="btn btn-sm btn-primary flex-shrink-0"
                                                wire:click="export">
                                            <i class="ti ti-file-analytics f-s-12"></i> Excel
                                        </button>



                                        <!-- Descargar último -->
                                        <button id="down"
                                                class="btn btn-sm btn-primary flex-shrink-0">
                                            <i class="fa-solid fa-angle-down"></i>
                                        </button>

                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
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
                                <th class="text-center">Img</th>
                            </tr>
                            </thead>
                            <tbody>

                            @forelse($incomes as $i)
                                <tr wire:key="exp-{{ $i->id }}">
                                    <td data-label="Opciones">
                                        @role('admin')
                                        <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"
                                           wire:click="openEditWindow({{ $i->id }})"></i>
                                        @endrole
                                    </td>
                                    <td>{{ $incomes->firstItem() + $loop->index }}</td>
                                    <td data-label="Fecha">{{ \Carbon\Carbon::parse($i->date)->format('d/m/Y') }}</td>
                                    <td data-label="Usuario">{{ $i->user->name ?? '-' }}</td>
                                    <td data-label="A">{{ $i->reason }}</td>
                                    <td data-label="Motivo">{{ $i->detail }}</td>
                                    <td class="text-end" data-label="S/">{{ number_format($i->total, 2) }}</td>
                                    <td class="text-center">
                                        @if($i->image_path)
                                            <i class="fa-solid fa-camera f-s-18 text-dark" style="cursor:pointer;"
                                               onclick="showLightbox('{{ asset('storage/'.$i->image_path) }}')"></i>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr wire:key="inc-1">
                                    <td colspan="8">Sin resultados para los filtros seleccionados.</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                            <tr style="font-size: 1.3rem; color: #000 !important;">
                                <td colspan="7" class="fw-bold text-end" style="padding: 4px 8px !important;">Total General</td>
                                <td class="fw-bold" style="padding: 4px 8px !important;">{{ number_format($totalGeneral, 2) }}</td>
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
                            <input id="dateAdd" type="text" class="form-control" wire:model.live="date">
                            @error('date') <span class="title-modules">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Moneda</label>
                            <select class="form-select" wire:model.live="currency">
                                <option value="Soles">Soles</option>
                                <option value="Dolares">Dólares</option>
                            </select>
                            @error('currency') <span class="title-modules">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Monto</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" placeholder="0.00"
                                   wire:model.live="amount_input">
                            @error('amount_input') <span class="title-modules">{{ $message }}</span> @enderror
                            @if(!is_null($converted_total))
                                <small class="text-muted">Total en S/: {{ number_format($converted_total, 2) }}</small>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">A</label>
                            <input type="text" class="form-control" placeholder="A quién / Área"
                                   wire:model.defer="reason">
                            @error('reason') <span class="title-modules">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Motivo</label>
                            <input type="text" class="form-control" placeholder="Detalle" wire:model.defer="detail">
                            @error('detail') <span class="title-modules">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Comprobante (imagen)</label>
                            <input type="file" class="form-control" wire:model="image_file" accept="image/*">
                            @error('image_file') <span class="title-modules">{{ $message }}</span> @enderror

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
                ['#dateAdd',    'date'],
            ], wire);
        });
    </script>
@endpush


