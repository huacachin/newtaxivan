@once
    @push('styles')
    <style>
        .multi-photos .dropzone-area {
            border: 2px dashed #b0bec5;
            border-radius: 8px;
            padding: 18px 12px;
            text-align: center;
            background: #fafafa;
            cursor: pointer;
            transition: background .15s, border-color .15s;
        }
        .multi-photos .dropzone-area:hover,
        .multi-photos .dropzone-active { border-color: #2874A6; background: #e3f2fd; }
        .multi-photos .dropzone-label { display: flex; flex-direction: row; align-items: center; justify-content: center; gap: 8px; cursor: pointer; color: #607d8b; }
        .multi-photos .img-thumb-clickable {
            height: 110px; width: 150px; object-fit: cover;
            background: #f5f5f5; cursor: pointer;
            border-radius: 6px; border: 1px solid #ddd;
        }
        .multi-photos .img-thumb-clickable:hover { opacity: .85; }
        .multi-photos .img-thumb-deleted { opacity: .35; filter: grayscale(80%); }
    </style>
    @endpush
@endonce

{{--
    Bloque multi-foto reutilizable para vehiculos/propietarios/conductores.
    Requiere en el componente Livewire host:
      - $new_images (array, wire:model)
      - $image_files (array de TemporaryUploadedFile)
      - $existing_images (array opcional con id,url)
      - $deleted_image_ids (array opcional)
      - métodos: updatedNewImages, removeNewImage, removeExistingImage, restoreExistingImage
    Variables del include:
      - $entityLabel (string) — "vehículo", "propietario", "conductor"
--}}
@php
    $entityLabel       = $entityLabel ?? 'registro';
    $hasExisting       = !empty($existing_images ?? []);
    $deletedIds        = $deleted_image_ids ?? [];
@endphp

<div class="col-12 multi-photos" data-audit-field="image_path">
    <div class="mb-3" x-data="{ dragging: false }">
        <label class="form-label">
            Fotos
            @if(!empty($image_files))
                <span class="badge bg-primary ms-1">{{ count($image_files) }} nuevas</span>
            @endif
            @if(!empty($deletedIds))
                <span class="badge bg-danger ms-1">{{ count($deletedIds) }} a eliminar</span>
            @endif
        </label>

        {{-- Dropzone desktop (drag & drop / click) --}}
        <div class="dropzone-area d-none d-md-flex"
             x-on:dragover.prevent="dragging = true"
             x-on:dragleave.prevent="dragging = false"
             x-on:drop.prevent="dragging = false; $refs.fileInputDesktop.files = $event.dataTransfer.files; $refs.fileInputDesktop.dispatchEvent(new Event('change', { bubbles: true }))"
             :class="{ 'dropzone-active': dragging }">
            <input type="file" multiple accept="image/*"
                   wire:model="new_images"
                   x-ref="fileInputDesktop"
                   class="d-none"
                   data-compress-image>
            <div class="dropzone-label mb-0"
                 x-on:click.prevent="$refs.fileInputDesktop.click()">
                <i class="ti ti-cloud-upload f-s-18"></i>
                <span class="f-s-12">Arrastra o haz clic (varias imágenes)</span>
            </div>
        </div>

        {{-- Mobile: tomar foto (camara) + agregar archivos (galeria) --}}
        <div class="d-flex d-md-none gap-2 flex-wrap">
            <input id="multi_photos_camera" type="file" class="visually-hidden"
                   wire:model="new_images"
                   accept="image/*" capture="environment" data-compress-image>
            <input id="multi_photos_gallery" type="file" class="visually-hidden"
                   wire:model="new_images" multiple
                   accept="image/*" data-compress-image>

            <label for="multi_photos_camera"
                   class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2"
                   style="cursor:pointer">
                <i class="ti ti-camera" style="font-size:18px"></i>
                <span>Tomar foto</span>
            </label>
            <label for="multi_photos_gallery"
                   class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2"
                   style="cursor:pointer">
                <i class="ti ti-photo" style="font-size:18px"></i>
                <span>Agregar archivos</span>
            </label>
        </div>

        @error('new_images.*') <span class="title-modules f-s-12 d-block mt-1">{{ $message }}</span> @enderror

        {{-- Estado de compresión (JS, antes de Livewire) --}}
        <small data-photo-compress-status class="text-muted align-items-center gap-2 mt-1 d-none">
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            <span>Procesando imagen...</span>
        </small>
        {{-- Estado de upload (Livewire) --}}
        <small wire:loading.flex wire:target="new_images" class="text-primary align-items-center gap-2 mt-1">
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            <span>Subiendo fotos...</span>
        </small>

        {{-- Existentes --}}
        @if($hasExisting)
            @php $existingUrls = array_column($existing_images, 'url'); @endphp
            <div class="mt-3">
                <div class="small text-muted mb-1">Actuales:</div>
                <div class="d-flex flex-wrap gap-2" x-data="{ urls: {{ json_encode($existingUrls) }} }">
                    @foreach($existing_images as $i => $img)
                        @php $isDeleted = in_array($img['id'], $deletedIds, true); @endphp
                        <div class="position-relative d-inline-block">
                            <img src="{{ $img['url'] }}" alt="Foto {{ $entityLabel }}"
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

        {{-- Nuevas (no guardadas aun) --}}
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
