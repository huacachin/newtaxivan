{{-- resources/views/livewire/owners/_form.blade.php --}}

@php
    // Si no viene inyectada, por defecto no resaltamos expiraciones (Create)
    $highlightExpiration = $highlightExpiration ?? false;
    $isEdit = isset($owner) && ($owner?->exists ?? false);
@endphp

<div class="row g-3">
    <div class="col-auto">
        <label for="document_type" class="form-label">Tipo de documento</label>
        <select id="document_type" class="form-select form-select-sm" wire:model="document_type">
            <option value="">Seleccionar</option>
            <option value="dni">DNI</option>
            <option value="ruc">RUC</option>
        </select>
        @error('document_type') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-auto">
        <label for="document_number" class="form-label {{ $isEdit ? 'text-muted' : '' }}">Número de documento</label>
        <input id="document_number" type="text" class="form-control form-control-sm" placeholder="Documento" wire:model="document_number" @if(!($highlightExpiration ?? false) && !$isEdit) wire:change="checkDocumentNumber" @endif autocomplete="off" @if($isEdit) disabled @endif>
        @error('document_number') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-12 col-md-6">
        <label for="name" class="form-label">Empresa/Nombre</label>
        <input id="name" type="text" class="form-control form-control-sm" placeholder="Ingresar nombres y apellidos" wire:model="name" autocomplete="off">
        @error('name') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-auto">
        <label for="document_expiration_date"
               class="form-label {{ $highlightExpiration && $this->documentExpirationExpired ? 'label-expired' : '' }}">
            Doc F.Vencimiento
        </label>
        <input id="document_expiration_date" type="text"
               class="form-control form-control-sm"
               wire:model="document_expiration_date">
        @error('document_expiration_date') <span class="title-modules">{{ $message }}</span> @enderror
    </div>


    <div class="col-auto">
        <label for="birthdate" class="form-label">Fecha Nacimiento</label>
        <input id="birthdate" type="text" class="form-control form-control-sm" wire:model="birthdate">
        @error('birthdate') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-auto">
        <label for="district" class="form-label">Distrito</label>
        <input id="district" type="text" class="form-control form-control-sm" placeholder="Distrito" wire:model="district">
        @error('district') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-auto">
        <label for="address" class="form-label">Dirección</label>
        <input id="address" type="text" class="form-control form-control-sm" placeholder="Ingresar dirección" wire:model="address">
        @error('address') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-auto">
        <label for="phone" class="form-label">Teléfono</label>
        <input id="phone" type="text" class="form-control form-control-sm" placeholder="Teléfono" wire:model="phone" inputmode="tel" autocomplete="off">
        @error('phone') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-auto">
        <label for="email" class="form-label">Email</label>
        <input id="email" type="email" class="form-control form-control-sm" placeholder="correo@dominio.com" wire:model="email" autocomplete="off">
        @error('email') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-12"><div class="app-divider-v justify-content-center"><p>FOTO</p></div></div>

    <div class="col-12 d-md-flex flex-md-column justify-content-md-center align-items-md-start">
        {{-- Input camara (movil): capture=environment dispara la camara trasera --}}
        <input id="image_file_camera" type="file" class="visually-hidden" wire:model="image_file"
               accept="image/*" capture="environment" data-compress-image>

        {{-- Input archivo (galeria/desktop): sin capture, abre selector estandar --}}
        <input id="image_file_gallery" type="file" class="visually-hidden" wire:model="image_file"
               accept="image/*" data-compress-image>

        @php $hasPhoto = $image_file || (isset($existing_image) && $existing_image); @endphp

        {{-- Desktop (>=md): solo cargar archivo --}}
        <label for="image_file_gallery"
               class="btn btn-outline-primary btn-sm d-none d-md-inline-flex align-items-center gap-2"
               style="cursor:pointer">
            <i class="ti ti-upload" style="font-size:18px"></i>
            <span>{{ $hasPhoto ? 'Cambiar archivo' : 'Cargar archivo' }}</span>
        </label>

        {{-- Mobile (<md): tomar foto + agregar archivo --}}
        <div class="d-flex d-md-none gap-2 flex-wrap">
            <label for="image_file_camera"
                   class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2"
                   style="cursor:pointer">
                <i class="ti ti-camera" style="font-size:18px"></i>
                <span>{{ $hasPhoto ? 'Cambiar foto' : 'Tomar foto' }}</span>
            </label>
            <label for="image_file_gallery"
                   class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2"
                   style="cursor:pointer">
                <i class="ti ti-photo" style="font-size:18px"></i>
                <span>Agregar archivo</span>
            </label>
        </div>

        @error('image_file') <span class="title-modules d-block mt-1">{{ $message }}</span> @enderror

        {{-- Estado de compresión (JS, antes de Livewire) --}}
        <small data-photo-compress-status class="text-muted align-items-center gap-2 mt-1 d-none">
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            <span>Procesando imagen...</span>
        </small>
        {{-- Estado de upload (Livewire) --}}
        <small wire:loading.flex wire:target="image_file" class="text-primary align-items-center gap-2 mt-1">
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            <span>Subiendo foto...</span>
        </small>

        {{-- Preview de imagen nueva (no guardada) --}}
        @if($image_file)
            <div class="position-relative d-inline-block mt-2" style="max-height:80px">
                <img src="{{ $image_file->temporaryUrl() }}" alt="Preview" class="rounded"
                     style="max-height:80px; cursor:pointer"
                     onclick="openImagePreview(this.src)"
                     title="Click para ampliar">
                <button type="button"
                        class="btn btn-sm btn-danger rounded-circle position-absolute top-0 end-0 p-0 d-flex align-items-center justify-content-center"
                        style="width:22px; height:22px; transform: translate(50%, -50%);"
                        wire:click="removeNewImage"
                        title="Quitar foto">
                    <i class="ti ti-x" style="font-size:14px"></i>
                </button>
            </div>
        @elseif(isset($existing_image) && $existing_image)
            <div class="position-relative d-inline-block mt-2" style="max-height:80px">
                <img src="{{ asset('storage/' . $existing_image) }}" alt="Foto propietario" class="rounded"
                     style="max-height:80px; cursor:pointer"
                     onclick="openImagePreview(this.src)"
                     title="Click para ampliar">
                <button type="button"
                        class="btn btn-sm btn-danger rounded-circle position-absolute top-0 end-0 p-0 d-flex align-items-center justify-content-center"
                        style="width:22px; height:22px; transform: translate(50%, -50%);"
                        onclick="confirmRemoveExistingImage()"
                        title="Eliminar foto guardada">
                    <i class="ti ti-x" style="font-size:14px"></i>
                </button>
            </div>
        @endif
    </div>
</div>

{{-- Lightbox compartido para preview ampliado --}}
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true" wire:ignore>
    <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content bg-transparent border-0 shadow-none">
            <button type="button"
                    class="position-absolute d-flex align-items-center justify-content-center"
                    data-bs-dismiss="modal"
                    aria-label="Cerrar"
                    style="top:14px; right:14px; width:44px; height:44px; border:0; border-radius:50%; background:#fff; color:#0f172a; box-shadow:0 8px 20px rgba(0,0,0,.4); z-index:10; cursor:pointer;">
                <i class="ti ti-x" style="font-size:24px; font-weight:bold;"></i>
            </button>
            <div class="modal-body text-center p-0 d-flex align-items-center justify-content-center">
                <img id="imagePreviewModalImg" src="" alt="" class="img-fluid rounded shadow-lg" style="max-height:90vh">
            </div>
        </div>
    </div>
</div>
