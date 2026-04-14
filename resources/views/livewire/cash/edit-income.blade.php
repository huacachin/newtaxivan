@push('styles')
<style>
    .dropzone-area {
        border: 2px dashed #b0bec5;
        border-radius: 6px;
        padding: 12px;
        text-align: center;
        cursor: pointer;
        transition: border-color .2s, background .2s;
        background: #fafafa;
    }
    .dropzone-area:hover, .dropzone-active { border-color: #2874A6; background: #e3f2fd; }
    .dropzone-label { display: flex; flex-direction: column; align-items: center; gap: 2px; cursor: pointer; color: #607d8b; }
    .img-thumb-clickable { height: 220px; width: 220px; object-fit: cover; cursor: pointer; border-radius: 6px; border: 1px solid #ddd; }
    .img-thumb-clickable:hover { opacity: .85; }
    .img-thumb-deleted { opacity: .35; filter: grayscale(80%); }
</style>
@endpush

<div class="container-fluid">

    {{-- Header --}}
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">INGRESOS : ACTUALIZAR</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-cash f-s-16"></i>
                    <a href="{{ route('cash.incomes') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Ingresos</span>
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
                            <strong>Corrige los siguientes errores:</strong>
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

                        {{-- Moneda --}}
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">Moneda (*)</label>
                                <select class="form-select form-select-sm @error('currency') is-invalid @enderror"
                                        wire:model.live="currency">
                                    <option value="Soles">Soles</option>
                                    <option value="Dolares">Dólares</option>
                                </select>
                                @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Monto --}}
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">Monto (*)</label>
                                <input type="number" step="0.01" min="0.01"
                                       class="form-control form-control-sm @error('amount_input') is-invalid @enderror"
                                       placeholder="0.00"
                                       wire:model.live="amount_input">
                                @error('amount_input') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                @if(!is_null($converted_total))
                                    <small class="text-muted">Total en S/: {{ number_format($converted_total, 2) }}</small>
                                @endif
                            </div>
                        </div>

                        {{-- A --}}
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">A (*)</label>
                                <input type="text" class="form-control form-control-sm @error('reason') is-invalid @enderror"
                                       placeholder="A quién / Área"
                                       wire:model.defer="reason">
                                @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Motivo --}}
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Motivo (*)</label>
                                <input type="text" class="form-control form-control-sm @error('detail') is-invalid @enderror"
                                       placeholder="Detalle"
                                       wire:model.defer="detail">
                                @error('detail') <div class="invalid-feedback">{{ $message }}</div> @enderror
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

                                @if(!empty($existing_images))
                                    <div class="mt-3">
                                        <div class="small text-muted mb-1">Actuales:</div>
                                        <div class="d-flex flex-wrap gap-2">
                                            @foreach($existing_images as $img)
                                                @php $isDeleted = in_array($img['id'], $deleted_image_ids, true); @endphp
                                                <div class="position-relative d-inline-block">
                                                    <img src="{{ $img['url'] }}" alt="Imagen"
                                                         class="img-thumb-clickable {{ $isDeleted ? 'img-thumb-deleted' : '' }}"
                                                         onclick="window.open(this.src, '_blank')">
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

                                @if(!empty($image_files))
                                    <div class="mt-3">
                                        <div class="small text-muted mb-1">Nuevas a subir:</div>
                                        <div class="d-flex flex-wrap gap-2">
                                            @foreach($image_files as $idx => $file)
                                                @if($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile && $file->isPreviewable())
                                                    <div class="position-relative d-inline-block">
                                                        <img src="{{ $file->temporaryUrl() }}" alt="Preview"
                                                             class="img-thumb-clickable"
                                                             onclick="window.open(this.src, '_blank')">
                                                        <button type="button"
                                                                wire:click="removeNewImage({{ $idx }})"
                                                                class="position-absolute top-0 end-0 border-0 bg-danger text-white rounded-circle d-flex align-items-center justify-content-center"
                                                                style="width:18px;height:18px;font-size:11px;line-height:1;padding:0;transform:translate(6px,-6px);">&times;</button>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                <small class="text-muted d-block mt-1">TC usado (MVP): 3.80</small>
                            </div>
                        </div>

                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary" wire:click="update" wire:loading.attr="disabled">
                            <span wire:loading wire:target="update" class="spinner-border spinner-border-sm"></span>
                            <span wire:loading.remove wire:target="update">Guardar cambios</span>
                        </button>
                        @can('delete', $income)
                        <button type="button" class="btn btn-sm btn-danger" wire:click="questionDelete({{ $incomeId }})">
                            Eliminar
                        </button>
                        @endcan
                        <a href="{{ route('cash.incomes') }}" class="btn btn-sm btn-secondary">Volver</a>
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- Overlay de carga --}}

</div>
