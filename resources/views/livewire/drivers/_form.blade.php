{{-- resources/views/livewire/drivers/_form.blade.php --}}
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
        <label class="form-label">Doc F.Vencimiento</label>
        <input id="document_expiration_date" type="text" class="form-control form-control-sm" wire:model="document_expiration_date">
        @error('document_expiration_date') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-auto">
        <label class="form-label">Fecha Nacimiento</label>
        <input id="birthdate" type="text" class="form-control form-control-sm" wire:model="birthdate">
        @error('birthdate') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-auto">
        <label class="form-label">Distrito</label>
        <input type="text" class="form-control form-control-sm" placeholder="Distrito" wire:model="district">
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
        <label class="form-label">Email</label>
        <input type="email" class="form-control form-control-sm" placeholder="correo@dominio.com" wire:model="email" autocomplete="off">
        @error('email') <span class="title-modules">{{ $message }}</span> @enderror
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
        <label class="form-label">Fecha Revalidación</label>
        <input id="license_revalidation_date" type="text" class="form-control form-control-sm" wire:model="license_revalidation_date">
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

    <div class="col-12"><div class="app-divider-v justify-content-center"><p>Credencial de Educación y Seguridad Vial</p></div></div>

    <div class="col-auto">
        <label class="form-label">Fecha Expedición (Cred.)</label>
        <input type="text" id="credential" class="form-control form-control-sm" wire:model="credential">
        @error('credential') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-auto">
        <label class="form-label">Fecha Vencimiento (Cred.)</label>
        <input type="text" id="credential_expiration_date" class="form-control form-control-sm" wire:model="credential_expiration_date">
        @error('credential_expiration_date') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-12 col-md-4">
        <label class="form-label">Municipalidad</label>
        <select class="form-select form-select-sm" wire:model="credential_municipality">
            <option value="">Seleccionar</option>
            <option value="lima">Lima</option>
            <option value="callao">Callao</option>
        </select>
        @error('credential_municipality') <span class="title-modules">{{ $message }}</span> @enderror
    </div>
</div>
