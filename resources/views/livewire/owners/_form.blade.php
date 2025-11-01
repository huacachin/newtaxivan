{{-- resources/views/livewire/owners/_form.blade.php --}}
<div class="row g-3">
    <div class="col-12 col-md-6">
        <label for="name" class="form-label">Nombres</label>
        <input id="name" type="text" class="form-control form-control-sm" placeholder="Ingresar nombres y apellidos" wire:model="name" autocomplete="off">
        @error('name') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-6 col-md-3">
        <label for="document_type" class="form-label">Tipo de documento</label>
        <select id="document_type" class="form-control form-control-sm" wire:model="document_type">
            <option value="">Seleccionar</option>
            <option value="dni">DNI</option>
            <option value="ruc">RUC</option>
        </select>
        @error('document_type') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-6 col-md-3">
        <label for="document_number" class="form-label">Número de documento</label>
        <input id="document_number" type="text" class="form-control form-control-sm" placeholder="Documento" wire:model="document_number" autocomplete="off">
        @error('document_number') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-6 col-md-4">
        <label for="document_expiration_date" class="form-label">Doc F.Vencimiento</label>
        <input id="document_expiration_date" type="date" class="form-control form-control-sm" wire:model="document_expiration_date">
        @error('document_expiration_date') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-6 col-md-4">
        <label for="birthdate" class="form-label">Fecha Nacimiento</label>
        <input id="birthdate" type="date" class="form-control form-control-sm" wire:model="birthdate">
        @error('birthdate') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-6 col-md-4">
        <label for="district" class="form-label">Distrito</label>
        <input id="district" type="text" class="form-control form-control-sm" placeholder="Distrito" wire:model="district">
        @error('district') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-12 col-md-12">
        <label for="address" class="form-label">Dirección</label>
        <input id="address" type="text" class="form-control form-control-sm" placeholder="Ingresar dirección" wire:model="address">
        @error('address') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-6 col-md-4">
        <label for="phone" class="form-label">Teléfono</label>
        <input id="phone" type="text" class="form-control form-control-sm" placeholder="Teléfono" wire:model="phone" inputmode="tel" autocomplete="off">
        @error('phone') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-6 col-md-4">
        <label for="email" class="form-label">Email</label>
        <input id="email" type="email" class="form-control form-control-sm" placeholder="correo@dominio.com" wire:model="email" autocomplete="off">
        @error('email') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
</div>
