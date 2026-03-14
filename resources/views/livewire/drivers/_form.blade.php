{{-- resources/views/livewire/drivers/_form.blade.php --}}
@push('styles')
    <style>
        .age-badge-label {
            display: inline-flex;
            align-items: center;
            padding: 0 6px;
            border-radius: 999px;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .04em;
            background: #e3f2fd;
            color: #0d47a1;
            border: 1px solid rgba(13,71,161,.15);
        }
        .age-pill {
            min-width: 64px;
            padding: 4px 10px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 11px;
            background: linear-gradient(135deg, #e8f5e9, #f1f8e9);
            color: #1b5e20;
            border: 1px solid rgba(27,94,32,.2);
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
        }
        .age-pill-number {
            font-variant-numeric: tabular-nums;
        }
    </style>
@endpush

@php
    $highlightExpiration = $highlightExpiration ?? false;
@endphp

<div class="row g-3">
    <div class="col-12 col-md-4">
        <label for="drv_name" class="form-label">Nombres</label>
        <input id="drv_name" type="text" class="form-control form-control-sm" placeholder="Nombres y apellidos" wire:model="name" autocomplete="off">
        @error('name') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-auto">
        <label class="form-label">N° Documento</label>
        <input type="text" class="form-control form-control-sm" placeholder="DNI" wire:model="document_number" autocomplete="off">
        @error('document_number') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-auto">
        <label class="form-label {{ $highlightExpiration && $this->documentExpirationExpired ? 'label-expired' : '' }}">
            Doc F.Vencimiento
        </label>
        <input id="document_expiration_date" type="text"
               class="form-control form-control-sm"
               wire:model="document_expiration_date">
        @error('document_expiration_date') <span class="title-modules">{{ $message }}</span> @enderror
    </div>


    <div class="col-auto">
        <label class="form-label">Fecha Nacimiento</label>
        <input id="birthdate" type="text" class="form-control form-control-sm" wire:model="birthdate" autocomplete="off">
        @error('birthdate') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-auto">
        <label class="form-label d-flex align-items-center gap-1">
            Edad
            <span class="age-badge-label">Años</span>
        </label>

        <div class="age-pill">
        <span class="age-pill-number">
            {{ $age !== null && $age !== '' ? $age : '—' }}
        </span>
        </div>
    </div>

    <div class="col-auto">
        <label class="form-label">Email</label>
        <input type="email" class="form-control form-control-sm" placeholder="correo@dominio.com" wire:model="email" autocomplete="off">
        @error('email') <span class="title-modules">{{ $message }}</span> @enderror
    </div>
    <div class="col-auto">
        <label class="form-label">Distrito</label>
        <select class="form-select form-select-sm" wire:model="district">
            <option value="">Seleccionar</option><option value="Ancon">Ancon</option><option value="Ate">Ate</option><option value="Barranco">Barranco</option><option value="Breña">Breña</option><option value="Carabayllo">Carabayllo</option><option value="Chaclacayo">Chaclacayo</option><option value="Chorrillos">Chorrillos</option><option value="Cieneguilla">Cieneguilla</option><option value="Comas">Comas</option><option value="El Agustino">El Agustino</option><option value="Independencia">Independencia</option><option value="Jesus Maria">Jesus Maria</option><option value="La Molina">La Molina</option><option value="La Victoria">La Victoria</option><option value="Lima">Lima</option><option value="Lince">Lince</option><option value="Los Olivos">Los Olivos</option><option value="Lurigancho">Lurigancho</option><option value="Lurin">Lurin</option><option value="Magdalena del Mar">Magdalena del Mar</option><option value="Magdalena Vieja">Magdalena Vieja</option><option value="Miraflores">Miraflores</option><option value="Pachacamac">Pachacamac</option><option value="Pucusana">Pucusana</option><option value="Puente Piedra">Puente Piedra</option><option value="Punta Hermosa">Punta Hermosa</option><option value="Punta Negra">Punta Negra</option><option value="Rimac">Rimac</option><option value="San Bartolo">San Bartolo</option><option value="San Borja">San Borja</option><option value="San Isidro">San Isidro</option><option value="San Juan de Lurigancho">San Juan de Lurigancho</option><option value="San Juan de Miraflores">San Juan de Miraflores</option><option value="San Luis">San Luis</option><option value="San Martin de Porres">San Martin de Porres</option><option value="San Miguel">San Miguel</option><option value="Santa Anita">Santa Anita</option><option value="Santa Maria del Mar">Santa Maria del Mar</option><option value="Santa Rosa">Santa Rosa</option><option value="Santiago de Surco">Santiago de Surco</option><option value="Surquillo">Surquillo</option><option value="Villa el Salvador">Villa el Salvador</option><option value="Villa Maria del Triunfo">Villa Maria del Triunfo</option>
        </select>
        @error('district') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-12 col-md-4">
        <label class="form-label">Dirección</label>
        <input type="text" class="form-control form-control-sm" placeholder="Dirección" wire:model="address">
        @error('address') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-auto">
        <label class="form-label">Teléfono</label>
        <input type="text" class="form-control form-control-sm" placeholder="Teléfono" wire:model="phone" inputmode="tel" autocomplete="off">
        @error('phone') <span class="title-modules">{{ $message }}</span> @enderror
    </div>



    <div class="col-auto">
        <label class="form-label">Licencia</label>
        <input type="text" class="form-control form-control-sm" placeholder="N° licencia" wire:model="license">
        @error('license') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-auto">
        <label class="form-label">Fecha Expedición</label>
        <input type="text" id="license_issue_date" class="form-control form-control-sm" wire:model="license_issue_date">
        @error('license_issue_date') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-auto">
        <label class="form-label {{ $highlightExpiration && $this->licenseRevalidationExpired ? 'label-expired' : '' }}">
            Fecha Revalidación
        </label>
        <input id="license_revalidation_date" type="text"
               class="form-control form-control-sm"
               wire:model="license_revalidation_date">
        @error('license_revalidation_date') <span class="title-modules">{{ $message }}</span> @enderror
    </div>


    <div class="col-auto">
        <label class="form-label">Clase</label>
        <input type="text" class="form-control form-control-sm" placeholder="Clase" wire:model="class">
        @error('class') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-auto">
        <label class="form-label">Categoría</label>
        <select class="form-select form-select-sm" wire:model="category">
            <option value="">Seleccionar</option>
            <option value="A A1">A1</option>
            <option value="A 2A">A 2A</option>
            <option value="A 2B">A 2B</option>
            <option value="A 3A">A 3A</option>
            <option value="A 3B">A 3B</option>
            <option value="A 3C">A 3C</option>
        </select>
        @error('category') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-auto">
        <label class="form-label">Puntos Acumulados</label>
        <input type="number" step="1" min="0" max="100" class="form-control form-control-sm" placeholder="0–100" wire:model="score" inputmode="numeric">
        @error('score') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-auto">
        <label class="form-label">F.Inicio Contrato</label>
        <input type="text" id="contract_start" class="form-control form-control-sm" wire:model="contract_start">
        @error('contract_start') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-auto">
        <label class="form-label">F.Fin Contrato</label>
        <input type="text" id="contract_end" class="form-control form-control-sm" wire:model="contract_end">
        @error('contract_end') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-auto">
        <label class="form-label">Condición</label>
        <select class="form-select form-select-sm" wire:model="condition">
            <option value="">Seleccionar</option>
            <option value="Propietario">Propietario</option>
            <option value="Alquilado">Alquilado</option>
        </select>
        @error('condition') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-12"><div class="app-divider-v justify-content-center"><p>CONSTANCIA DE EDUCACION DE VIAL</p></div></div>

    <div class="col-auto">
        <label class="form-label">Fecha Expedición</label>
        <input type="text" id="road_education" class="form-control form-control-sm" wire:model="road_education">
        @error('road_education') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-auto">
        <label class="form-label {{ $highlightExpiration && $this->roadEducationExpirationExpired ? 'label-expired' : '' }}">
            Fecha Vencimiento
        </label>
        <input type="text" id="road_education_expiration_date"
               class="form-control form-control-sm"
               wire:model="road_education_expiration_date">
        @error('road_education_expiration_date') <span class="title-modules">{{ $message }}</span> @enderror
    </div>


    <div class="col-12 col-md-4">
        <label class="form-label">Municipalidad</label>
        <select class="form-select form-select-sm" wire:model="road_education_municipality">
            <option value="">Seleccionar</option>
            <option value="Lima">Lima</option>
            <option value="Callao">Callao</option>
        </select>
        @error('road_education_municipality') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-12"><div class="app-divider-v justify-content-center"><p>CREDENCIAL</p></div></div>

    <div class="col-auto">
        <label class="form-label">Fecha Expedición</label>
        <input type="text" id="credential" class="form-control form-control-sm" wire:model="credential">
        @error('credential') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-auto">
        <label class="form-label {{ $highlightExpiration && $this->credentialExpirationExpired ? 'label-expired' : '' }}">
            Fecha Vencimiento
        </label>
        <input type="text" id="credential_expiration_date"
               class="form-control form-control-sm"
               wire:model="credential_expiration_date">
        @error('credential_expiration_date') <span class="title-modules">{{ $message }}</span> @enderror
    </div>


    <div class="col-12 col-md-4">
        <label class="form-label">Municipalidad</label>
        <select class="form-select form-select-sm" wire:model="credential_municipality">
            <option value="">Seleccionar</option>
            <option value="Lima">Lima</option>
            <option value="Callao">Callao</option>
        </select>
        @error('credential_municipality') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-12"><div class="app-divider-v justify-content-center"><p>DETALLES Y FOTO</p></div></div>

    <div class="col-12 col-md-6">
        <label class="form-label">Detalles</label>
        <textarea class="form-control form-control-sm" rows="3" placeholder="Detalles adicionales del conductor..." wire:model="details"></textarea>
        @error('details') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-12 col-md-6">
        <label class="form-label">Foto</label>
        <input type="file" class="form-control form-control-sm" wire:model="image_file" accept="image/*">
        @error('image_file') <span class="title-modules">{{ $message }}</span> @enderror

        {{-- Preview de imagen nueva --}}
        @if($image_file)
            <img src="{{ $image_file->temporaryUrl() }}" alt="Preview" class="mt-2 rounded" style="max-height:80px; cursor:pointer"
                 onclick="window.open(this.src, '_blank')">
        @elseif(isset($existing_image) && $existing_image)
            <img src="{{ asset('storage/' . $existing_image) }}" alt="Foto conductor" class="mt-2 rounded" style="max-height:80px; cursor:pointer"
                 onclick="window.open(this.src, '_blank')">
        @endif
    </div>
</div>
