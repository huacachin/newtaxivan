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
                                                                   wire:model="filterType">  {{-- sin .live ni wire:click --}}
                                                            <label class="form-check-label" for="rbA">A</label>
                                                        </div>

                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input mg-e-4"
                                                                   type="radio"
                                                                   name="rbFilter"
                                                                   id="rbMotive"
                                                                   value="2"
                                                                   wire:model="filterType">
                                                            <label class="form-check-label" for="rbMotive">Motivo</label>
                                                        </div>

                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input mg-e-4"
                                                                   type="radio"
                                                                   name="rbFilter"
                                                                   id="rbUser"
                                                                   value="3"
                                                                   wire:model="filterType">
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
                                                wire:model="search">
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
                                        @hasanyrole('director|gerente|administrador')
                                        <a href="{{ route('cash.incomes.create') }}"
                                           class="btn btn-sm btn-success flex-shrink-0">
                                            <i class="ti ti-square-plus f-s-12"></i> Nuevo
                                        </a>
                                        @endhasanyrole

                                        <!-- Exportar -->
                                        <button class="btn btn-sm btn-export flex-shrink-0"
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
                                <th></th>
                                <th>Nº</th>
                                <th>Fecha</th>
                                <th>Usuario</th>
                                <th>A</th>
                                <th>Motivo</th>
                                <th>S/</th>
                                <th class="text-center">Img</th>
                            </tr>
                            </thead>
                            <tbody>

                            @forelse($incomes as $i)
                                <tr wire:key="exp-{{ $i->id }}">
                                    <td data-label="Opciones">
                                        @can('update', $i)
                                            <a href="{{ route('cash.incomes.edit', $i->id) }}">
                                                <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"></i>
                                            </a>
                                        @endcan
                                    </td>
                                    <td>{{ $incomes->firstItem() + $loop->index }}</td>
                                    <td data-label="Fecha">{{ \Carbon\Carbon::parse($i->date)->format('d/m/Y') }}</td>
                                    <td data-label="Usuario">{{ $i->user->username ?? '-' }}</td>
                                    <td data-label="A">{{ $i->reason }}</td>
                                    <td data-label="Motivo">{{ $i->detail }}</td>
                                    <td class="text-end" data-label="S/">{{ number_format($i->total, 2) }}</td>
                                    <td class="text-center">
                                        @if($i->images->isNotEmpty())
                                            @php
                                                $urls = $i->images->map(fn($im) => asset('storage/'.$im->image_path))->values();
                                            @endphp
                                            <span x-data="{ urls: {{ json_encode($urls) }} }"
                                                  style="cursor:pointer;position:relative;display:inline-block;"
                                                  x-on:click="$dispatch('open-lightbox', { images: urls, index: 0 })">
                                                <i class="fa-solid fa-camera f-s-18 text-dark"></i>
                                                @if($i->images->count() > 1)
                                                    <span class="badge bg-primary" style="position:absolute;top:-8px;right:-12px;font-size:9px;">{{ $i->images->count() }}</span>
                                                @endif
                                            </span>
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
                                <td colspan="6" class="fw-bold text-end" style="padding: 4px 8px !important;">Total General</td>
                                <td class="fw-bold text-end" style="padding: 4px 8px !important;">{{ number_format($totalGeneral, 2) }}</td>
                                <td></td>
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
                    <button type="button" class="btn btn-sm btn-light-primary" wire:click="save" wire:loading.attr="disabled">
                        <span wire:loading wire:target="save" class="spinner-border spinner-border-sm"></span>
                        <span wire:loading.remove wire:target="save">Guardar</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Lightbox Alpine --}}
    <div x-data="{
            open: false,
            images: [],
            current: 0,
            show(imgs, idx) { this.images = imgs; this.current = idx; this.open = true; },
            close() { this.open = false; this.images = []; this.current = 0; },
            prev() { this.current = (this.current - 1 + this.images.length) % this.images.length; },
            next() { this.current = (this.current + 1) % this.images.length; },
         }"
         x-on:open-lightbox.window="show($event.detail.images, $event.detail.index)"
         x-on:keydown.escape.window="close()"
         x-on:keydown.left.window="if(open) prev()"
         x-on:keydown.right.window="if(open) next()"
         x-show="open"
         x-cloak
         class="lightbox-overlay"
         x-on:click.self="close()">
        <template x-if="open && images.length">
            <div>
                <img :src="images[current]" class="lightbox-img" alt="Imagen">
                <button class="lightbox-close" x-on:click="close()" title="Cerrar">&times;</button>
                <template x-if="images.length > 1">
                    <div>
                        <button class="lightbox-btn lightbox-prev" x-on:click.stop="prev()">&#8249;</button>
                        <button class="lightbox-btn lightbox-next" x-on:click.stop="next()">&#8250;</button>
                        <div class="lightbox-counter" x-text="(current + 1) + ' / ' + images.length"></div>
                    </div>
                </template>
            </div>
        </template>
    </div>

</div>

@push('styles')
<style>
    .lightbox-overlay { position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,.85); display: flex; align-items: center; justify-content: center; }
    .lightbox-img { max-width: 90vw; max-height: 85vh; border-radius: 6px; box-shadow: 0 4px 24px rgba(0,0,0,.5); }
    .lightbox-btn { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,.15); border: none; color: #fff; font-size: 28px; padding: 8px 14px; border-radius: 50%; cursor: pointer; transition: background .2s; }
    .lightbox-btn:hover { background: rgba(255,255,255,.3); }
    .lightbox-prev { left: 16px; }
    .lightbox-next { right: 16px; }
    .lightbox-close { position: absolute; top: 16px; right: 20px; background: none; border: none; color: #fff; font-size: 32px; cursor: pointer; line-height: 1; }
    .lightbox-counter { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); color: #fff; font-size: 14px; background: rgba(0,0,0,.5); padding: 4px 12px; border-radius: 12px; }
    [x-cloak] { display: none !important; }
</style>
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


