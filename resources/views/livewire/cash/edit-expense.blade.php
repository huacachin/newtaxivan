@push('styles')
<style>
    .dropzone-area {
        border: 2px dashed #b0bec5;
        border-radius: 6px;
        padding: 6px 14px;
        text-align: center;
        cursor: pointer;
        transition: border-color .2s, background .2s;
        background: #fafafa;
    }
    .dropzone-area:hover, .dropzone-active { border-color: #2874A6; background: #e3f2fd; }
    .dropzone-label { display: flex; flex-direction: row; align-items: center; justify-content: center; gap: 8px; cursor: pointer; color: #607d8b; }
    .img-thumb-clickable { height: 360px; width: 200px; object-fit: contain; background: #f5f5f5; cursor: pointer; border-radius: 6px; border: 1px solid #ddd; }
    .img-thumb-clickable:hover { opacity: .85; }
    .img-thumb-deleted { opacity: .35; filter: grayscale(80%); }
</style>
@endpush

<div class="container-fluid">

    {{-- Header --}}
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">EGRESOS : ACTUALIZAR</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-cash f-s-16"></i>
                    <a href="{{ route('cash.expenses') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Egresos</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <span class="f-s-14">Editar</span>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">

                    {{-- Errores de validación --}}
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <strong>Revisa los siguientes errores:</strong>
                            <ul class="mb-0 mt-2 ps-3">
                                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row">

                        {{-- Fecha --}}
                        <div class="col-md-auto col-sm-12">
                            <div class="mb-3">
                                <label class="form-label">Fecha (*)</label>
                                <input type="date" class="form-control form-control-sm @error('date') is-invalid @enderror"
                                       wire:model.defer="date">
                                @error('date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Tipo de egreso --}}
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">Tipo de egreso (*)</label>
                                <select class="form-select form-select-sm @error('expenseKind') is-invalid @enderror"
                                        wire:model.live="expenseKind">
                                    <option value="Otros">Otros</option>
                                    <option value="Fijos">Fijos</option>
                                    <option value="Planilla">Planilla</option>
                                </select>
                                @error('expenseKind') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- A: condicional Fijos/Otros --}}
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">A (*)</label>
                                @if($expenseKind === 'Fijos')
                                    <select class="form-select form-select-sm @error('concept_id') is-invalid @enderror"
                                            wire:model.live="concept_id">
                                        <option value="">-- Seleccionar concepto --</option>
                                        @foreach($concepts as $c)
                                            <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('concept_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                @elseif($expenseKind === 'Planilla')
                                    <select class="form-select form-select-sm @error('reason_text') is-invalid @enderror"
                                            wire:model.defer="reason_text">
                                        @foreach($controladores as $nombre)
                                            <option value="{{ $nombre }}">{{ $nombre }}</option>
                                        @endforeach
                                    </select>
                                    @error('reason_text') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                @else
                                    <input type="text" class="form-control form-control-sm @error('reason_text') is-invalid @enderror"
                                           placeholder="Ej: Combustible, Servicio, etc."
                                           wire:model.defer="reason_text">
                                    @error('reason_text') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                @endif
                            </div>
                        </div>

                        {{-- Motivo / Detalle --}}
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Motivo / Detalle (*)</label>
                                <input type="text" class="form-control form-control-sm @error('detail') is-invalid @enderror"
                                       placeholder="Descripción breve"
                                       wire:model.defer="detail">
                                @error('detail') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Monto --}}
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">Monto (S/) (*)</label>
                                <div x-data="numericChips({
                                    storageKey: 'expenses.amount',
                                    server: () => $wire.amountSuggestions,
                                    decimals: 2
                                })">
                                    <input type="text" inputmode="decimal"
                                           x-ref="input"
                                           name="expense_amount" autocomplete="on"
                                           class="form-control form-control-sm @error('total') is-invalid @enderror"
                                           wire:model.defer="total">
                                    <div class="num-chips" x-show="suggestions.length" x-cloak>
                                        <template x-for="(s, i) in suggestions" :key="s.value">
                                            <button type="button" :class="badgeClass(s.source)"
                                                    :title="`${s.hint} (Alt+${i+1})`"
                                                    @click="pick(s.value)" x-text="formatted(s.value)"></button>
                                        </template>
                                    </div>
                                </div>
                                @error('total') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- T. Comprobante --}}
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">T. Comprobante</label>
                                <input type="text" class="form-control form-control-sm @error('document_type') is-invalid @enderror"
                                       placeholder="Factura, Boleta, etc."
                                       wire:model.defer="document_type">
                                @error('document_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Responsable --}}
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">Responsable</label>
                                <select class="form-select form-select-sm" wire:model.defer="in_charge">
                                    <option value="">-- Seleccionar --</option>
                                    @foreach($users as $u)
                                        <option value="{{ $u }}">{{ $u }}</option>
                                    @endforeach
                                </select>
                                @error('in_charge') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Imágenes (drag & drop multi) --}}
                        <div class="w-100"></div>
                        <div class="col-md-10">
                            <div class="mb-3" x-data="{ dragging: false }">
                                <label class="form-label">
                                    Comprobantes (imágenes)
                                    @if(!empty($image_files))
                                        <span class="badge bg-primary ms-1">{{ count($image_files) }} nuevas</span>
                                    @endif
                                    @if(!empty($deleted_image_ids))
                                        <span class="badge bg-danger ms-1">{{ count($deleted_image_ids) }} a eliminar</span>
                                    @endif
                                </label>
                                <div class="dropzone-area"
                                     x-on:dragover.prevent="dragging = true"
                                     x-on:dragleave.prevent="dragging = false"
                                     x-on:drop.prevent="dragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }))"
                                     :class="{ 'dropzone-active': dragging }">
                                    <input type="file" multiple accept="image/*"
                                           wire:model="new_images"
                                           x-ref="fileInput"
                                           class="d-none">
                                    <div class="dropzone-label mb-0"
                                         x-on:click.prevent="$refs.fileInput.click()">
                                        <i class="ti ti-cloud-upload f-s-18"></i>
                                        <span class="f-s-12">Arrastra o haz clic (varias imágenes)</span>
                                    </div>
                                </div>
                                @error('new_images.*') <span class="title-modules f-s-12">{{ $message }}</span> @enderror

                                {{-- Imágenes actuales (eliminación diferida) --}}
                                @if(!empty($existing_images))
                                    @php $existingUrls = array_column($existing_images, 'url'); @endphp
                                    <div class="mt-3">
                                        <div class="small text-muted mb-1">Actuales:</div>
                                        <div class="d-flex flex-wrap gap-2" x-data="{ urls: {{ json_encode($existingUrls) }} }">
                                            @foreach($existing_images as $i => $img)
                                                @php $isDeleted = in_array($img['id'], $deleted_image_ids, true); @endphp
                                                <div class="position-relative d-inline-block">
                                                    <img src="{{ $img['url'] }}" alt="Imagen"
                                                         class="img-thumb-clickable {{ $isDeleted ? 'img-thumb-deleted' : '' }}"
                                                         x-on:click="$dispatch('open-lightbox', { images: urls, index: {{ $i }} })">
                                                    @if($isDeleted)
                                                        <button type="button"
                                                                title="Restaurar"
                                                                wire:click="restoreExistingImage({{ $img['id'] }})"
                                                                class="position-absolute top-0 end-0 border-0 bg-success text-white rounded-circle d-flex align-items-center justify-content-center"
                                                                style="width:18px;height:18px;font-size:11px;line-height:1;padding:0;transform:translate(6px,-6px);">&#x21bb;</button>
                                                    @else
                                                        <button type="button"
                                                                title="Marcar para eliminar"
                                                                wire:click="removeExistingImage({{ $img['id'] }})"
                                                                class="position-absolute top-0 end-0 border-0 bg-danger text-white rounded-circle d-flex align-items-center justify-content-center"
                                                                style="width:18px;height:18px;font-size:11px;line-height:1;padding:0;transform:translate(6px,-6px);">&times;</button>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Nuevas a subir --}}
                                @php
                                    $newPreviewables = [];
                                    foreach ($image_files as $idx => $f) {
                                        if ($f instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile && $f->isPreviewable()) {
                                            $newPreviewables[] = ['idx' => $idx, 'url' => $f->temporaryUrl()];
                                        }
                                    }
                                    $newPreviewUrls = array_column($newPreviewables, 'url');
                                @endphp
                                @if(!empty($newPreviewables))
                                    <div class="mt-3">
                                        <div class="small text-muted mb-1">Nuevas a subir:</div>
                                        <div class="d-flex flex-wrap gap-2" x-data="{ urls: {{ json_encode($newPreviewUrls) }} }">
                                            @foreach($newPreviewables as $i => $p)
                                                <div class="position-relative d-inline-block">
                                                    <img src="{{ $p['url'] }}" alt="Preview"
                                                         class="img-thumb-clickable"
                                                         x-on:click="$dispatch('open-lightbox', { images: urls, index: {{ $i }} })">
                                                    <button type="button"
                                                            wire:click="removeNewImage({{ $p['idx'] }})"
                                                            class="position-absolute top-0 end-0 border-0 bg-danger text-white rounded-circle d-flex align-items-center justify-content-center"
                                                            style="width:18px;height:18px;font-size:11px;line-height:1;padding:0;transform:translate(6px,-6px);">&times;</button>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary" wire:click="update" wire:loading.attr="disabled">
                            <span wire:loading wire:target="update" class="spinner-border spinner-border-sm"></span>
                            <span wire:loading.remove wire:target="update">Guardar cambios</span>
                        </button>
                        @can('delete', $expense)
                        <button type="button" class="btn btn-sm btn-danger" wire:click="questionDelete({{ $expenseId }})">
                            Eliminar
                        </button>
                        @endcan
                        <a href="{{ route('cash.expenses') }}" class="btn btn-sm btn-secondary">Volver</a>
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- Overlay de carga --}}

    @include('partials.image-lightbox')
</div>
