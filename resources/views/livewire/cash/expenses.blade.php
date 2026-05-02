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

                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input mg-e-4"
                                                       type="radio"
                                                       name="rbFilter"
                                                       id="rbRespons"
                                                       value="4"
                                                       wire:model="filterType">
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
                                    wire:model="search">
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
                               class="btn btn-sm btn-success flex-shrink-0">
                                <i class="ti ti-square-plus f-s-12"></i> Nuevo
                            </a>

                            <button class="btn btn-sm btn-export flex-shrink-0" wire:click="export">
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
                            <th></th>
                            <th>Nº</th>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>A</th>
                            <th>Motivo</th>
                            <th>S/</th>
                            <th>T.Comp.</th>
                            <th>Respons.</th>
                            <th class="text-center">Img</th>
                        </tr>
                        </thead>

                        <tbody>


                        @forelse($expenses as $e)
                            <tr  wire:key="exp-{{ $e->id }}">
                                <td data-label="Opciones">
                                    @can('update', $e)
                                        <a href="{{ route('cash.expenses.edit', $e->id) }}">
                                            <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"></i>
                                        </a>
                                    @endcan
                                </td>
                                <td>{{ $expenses->firstItem() + $loop->index }}</td>
                                <td>{{ \Carbon\Carbon::parse($e->date)->format('d/m/Y') }}</td>
                                <td>{{ $e->user->username ?? '-' }}</td>
                                <td>{{ $e->reason }}</td>
                                <td>{{ $e->detail }}</td>
                                <td class="text-end">{{ number_format($e->total, 2) }}</td>
                                <td>{{ $e->document_type }}</td>
                                <td>{{ $e->in_charge }}</td>
                                <td class="text-center">
                                    @if($e->images->isNotEmpty())
                                        @php
                                            $urls = $e->images->map(fn($i) => asset('storage/'.$i->image_path))->values();
                                        @endphp
                                        <span x-data="{ urls: {{ json_encode($urls) }} }"
                                              style="cursor:pointer;position:relative;display:inline-block;"
                                              x-on:click="$dispatch('open-lightbox', { images: urls, index: 0 })">
                                            <i class="fa-solid fa-camera f-s-18 text-dark"></i>
                                            @if($e->images->count() > 1)
                                                <span class="badge bg-primary" style="position:absolute;top:-8px;right:-12px;font-size:9px;">{{ $e->images->count() }}</span>
                                            @endif
                                        </span>
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
                            <td colspan="6" class="text-end fw-bold" style="padding: 4px 8px !important;">Total General</td>
                            <td class="text-end fw-bold" style="padding: 4px 8px !important;">{{ number_format($totalGeneral ?? 0, 2) }}</td>
                            <td colspan="3"></td>
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
                    <button class="btn btn-primary" type="submit">
                        Guardar
                    </button>
                    <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">
                        Cerrar
                    </button>
                </div>
            </form>
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
            ], wire);
        });
    </script>
@endpush
