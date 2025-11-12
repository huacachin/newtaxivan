{{-- resources/views/livewire/vehicles/_form.blade.php --}}
<div class="row g-3">
    {{-- Placa --}}
    <div class="col-6 col-md-2">
        <label for="plate" class="form-label">Placa</label>
        <input id="plate" type="text" class="form-control form-control-sm" placeholder="Ingresar placa" wire:model="plate" autocomplete="off">
        @error('plate') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Sede --}}
    <div class="col-6 col-md-2">
        <label for="headquarter" class="form-label">Sede</label>
        <select id="headquarter" class="form-select form-select-sm" wire:model="headquarter">
            <option value="">Seleccione</option>
            @foreach($listHeadquarters as $hq)
                <option value="{{ $hq->name }}">{{ $hq->name }}</option>
            @endforeach
        </select>
        @error('headquarter') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- F. Ingreso --}}
    <div class="col-6 col-md-2">
        <label for="entry_date" class="form-label">F. Ingreso</label>
        <input id="entry_date" type="date" class="form-control form-control-sm" wire:model="entry_date">
        @error('entry_date') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Fecha Cese --}}
    <div class="col-6 col-md-2">
        <label for="termination_date" class="form-label">Fecha Cese</label>
        <input id="termination_date" type="date" class="form-control form-control-sm" wire:model="termination_date">
        @error('termination_date') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Categoría --}}
    <div class="col-6 col-md-2">
        <label for="class" class="form-label">Categoría</label>
        <select id="class" class="form-select form-select-sm" wire:model="class">
            <option value="">Seleccione</option>
            <option value="M1">M1</option>
            <option value="M1-C3">M1-C3</option>
            <option value="M2">M2</option>
            <option value="MICROBUS">MICROBUS</option>
            <option value="M3.C3">M3.C3</option>
            <option value="M3.C1 OMNIBUS">M3.C1 OMNIBUS</option>
            <option value="M3-C3">M3-C3</option>
            <option value="M2-C3">M2-C3</option>
        </select>
        @error('class') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Marca --}}
    <div class="col-6 col-md-2">
        <label for="brand" class="form-label">Marca</label>
        <select id="brand" class="form-select form-select-sm" wire:model="brand">
            <option value="">Seleccione</option>
            <option value="Hyundai">Hyundai</option>
            <option value="Jac">Jac</option>
            <option value="Changan">Changan</option>
            <option value="DFSK">DFSK</option>
            <option value="Change">Change</option>
            <option value="Mitsubishi">Mitsubishi</option>
            <option value="Faw">Faw</option>
            <option value="Volkswagen">Volkswagen</option>
        </select>
        @error('brand') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Año --}}
    <div class="col-6 col-md-2">
        <label for="year" class="form-label">Año Fabricación</label>
        <input id="year" type="number" inputmode="numeric" class="form-control form-control-sm" placeholder="Ej. 2020" wire:model="year" min="1900" max="2100" step="1">
        @error('year') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Modelo --}}
    <div class="col-6 col-md-2">
        <label for="model" class="form-label">Modelo</label>
        <input id="model" type="text" class="form-control form-control-sm" placeholder="Ingresar modelo" wire:model="model">
        @error('model') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Carrocería --}}
    <div class="col-6 col-md-2">
        <label for="bodywork" class="form-label">Carrocería</label>
        <select id="bodywork" class="form-select form-select-sm" wire:model="bodywork">
            <option value="">Seleccione</option>
            <option value="MULTIPROPOSITO">MULTIPROPOSITO</option>
            <option value="MICROBUS">MICROBUS</option>
            <option value="Minibus">Minibus</option>
        </select>
        @error('bodywork') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Color --}}
    <div class="col-6 col-md-2">
        <label for="color" class="form-label">Color</label>
        <select id="color" class="form-select form-select-sm" wire:model="color">
            <option value="">Seleccionar</option>
            <option value="Azul">Azul</option>
            <option value="Azul Acero">Azul Acero</option>
            <option value="Azul Oscuro">Azul Oscuro</option>
            <option value="Beige">Beige</option>
            <option value="Blanco">Blanco</option>
            <option value="Blanco Perla">Blanco Perla</option>
            <option value="Dorado">Dorado</option>
            <option value="Gris">Gris</option>
            <option value="Gris Oscuro">Gris Oscuro</option>
            <option value="Marron">Marron</option>
            <option value="Moca Arabe">Moca Arabe</option>
            <option value="Negro">Negro</option>
            <option value="Negro Atemporal">Negro Atemporal</option>
            <option value="Oceanico">Oceanico</option>
            <option value="Plata">Plata</option>
            <option value="Plata Diamond">Plata Diamond</option>
            <option value="Plata Metalizado">Plata Metalizado</option>
            <option value="Plomo">Plomo</option>
        </select>
        @error('color') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Modalidad --}}
    <div class="col-6 col-md-2">
        <label for="type" class="form-label">Modalidad</label>
        <select id="type" class="form-select form-select-sm" wire:model="type">
            <option value="">Seleccionar</option>
            <option value="Particular">Particular</option>
            <option value="Taxi Ejecutivo">Taxi Ejecutivo</option>
            <option value="Taxi Independiente">Taxi Independiente</option>
            <option value="Transporte Personal">Transporte Personal</option>
            <option value="Transporte Turismo">Transporte Turismo</option>
        </select>
        @error('type') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Condición --}}
    <div class="col-6 col-md-2">
        <label for="condition" class="form-label">Condición</label>
        <select id="condition" class="form-select form-select-sm" wire:model="condition">
            <option value="">Seleccione</option>
            <option value="DT">DT</option>
            <option value="GN">GN</option>
            <option value="EX">EX</option>
            <option value="EX5">EX5</option>
        </select>
        @error('condition') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Empresa Afiliada --}}
    <div class="col-6 col-md-2">
        <label for="affiliated_company" class="form-label">Empresa Afiliada</label>
        <input id="affiliated_company" type="text" class="form-control form-control-sm" placeholder="Ingresar empresa afiliada" wire:model="affiliated_company">
        @error('affiliated_company') <span class="title-modules">{{ $message }}</span> @enderror
    </div>



    {{-- Propietario --}}
    <div class="col-6 col-md-2">
        <label for="owner_id" class="form-label">Propietario</label>
        <select id="owner_id" class="form-select form-select-sm" wire:model="owner_id">
            <option value="">Seleccionar</option>
            @foreach($listOwners as $owner)
                <option value="{{ $owner->id }}">{{ $loop->iteration }}.- {{ $owner->name }}</option>
            @endforeach
        </select>
        @error('owner_id') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Conductor --}}
    <div class="col-6 col-md-2">
        <label for="driver_id" class="form-label">Conductor</label>
        <select id="driver_id" class="form-select form-select-sm" wire:model="driver_id">
            <option value="">Seleccionar</option>
            @foreach($listDrivers as $driver)
                <option value="{{ $driver->id }}">{{$loop->iteration}}.- {{ $driver->name }}</option>
            @endforeach
        </select>
        @error('driver_id') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Combustible --}}
    <div class="col-6 col-md-2">
        <label for="fuel" class="form-label">Combustible</label>
        <select id="fuel" class="form-select form-select-sm" wire:model="fuel">
            <option value="">Seleccionar</option>
            <option value="D2">D2</option>
            <option value="GAS">GAS</option>
        </select>
        @error('fuel') <span class="title-modules">{{ $message }}</span> @enderror
    </div>



    <div class="col-6 col-md-1">
        <label for="seats" class="form-label">Asientos</label>
        <input id="seats" type="number" inputmode="numeric" class="form-control form-control-sm"  wire:model="seats" min="1" step="1">
        @error('seats') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-6 col-md-1">
        <label for="passengers" class="form-label">Pasajeros</label>
        <input id="passengers" type="number" inputmode="numeric" class="form-control form-control-sm"  wire:model="passengers" min="1" step="1">
        @error('passengers') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Sort Order --}}
    <div class="col-6 col-md-2">
        <label for="sort_order" class="form-label">Orden</label>
        <input id="sort_order" type="number" inputmode="numeric" class="form-control form-control-sm"  wire:model="sort_order" min="1" step="1">
        @error('sort_order') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Detalle (ocupa ancho completo en md) --}}
    <div class="col-12">
        <label for="detail" class="form-label">Detalle</label>
        <textarea id="detail" rows="2" class="form-select form-select-sm" placeholder="Observaciones..." wire:model="detail"></textarea>
        @error('detail') <span class="title-modules">{{ $message }}</span> @enderror
    </div>



    {{-- Soat F.V --}}
    <div class="col-6 col-md-2">
        <label for="soat_date" class="form-label">Soat F.V</label>
        <input id="soat_date" type="date" class="form-control form-control-sm" wire:model="soat_date">
        @error('soat_date') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Certificado F.V --}}
    <div class="col-6 col-md-2">
        <label for="certificate_date" class="form-label">Certificado F.V</label>
        <input id="certificate_date" type="date" class="form-control form-control-sm" wire:model="certificate_date">
        @error('certificate_date') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Revisión Técnica F.V --}}
    <div class="col-6 col-md-2">
        <label for="technical_review" class="form-label">Revisión Técnica F.V</label>
        <input id="technical_review" type="date" class="form-control form-control-sm" wire:model="technical_review">
        @error('technical_review') <span class="title-modules">{{ $message }}</span> @enderror
    </div>


</div>
