{{-- resources/views/livewire/vehicles/_form.blade.php --}}
@php
    $isEdit = isset($vehicle) && ($vehicle?->exists ?? false);
    // En Edit, los controladores no pueden tocar Fecha cese, Orden y Condicion.
    $lockForController = $isEdit && auth()->user()?->hasRole('controlador');
@endphp
<div class="row g-3">
    {{-- Placa --}}
    <div class="col-6 col-md-2">
        <label for="plate" class="form-label {{ $isEdit ? 'text-muted' : '' }}">Placa</label>
        @if($isEdit)
            <input id="plate" type="text" class="form-control form-control-sm" placeholder="Ingresar placa" wire:model="plate" autocomplete="off" disabled>
        @else
            {{-- La lupa consulta la placa en Factiliza y llena marca, modelo y color --}}
            <div class="input-group input-group-sm">
                <input id="plate" type="text" class="form-control form-control-sm text-uppercase" placeholder="Ingresar placa" wire:model="plate" wire:keydown.enter="lookupPlate" autocomplete="off">
                <button type="button" class="btn btn-search" wire:click="lookupPlate" wire:loading.attr="disabled" wire:target="lookupPlate" title="Buscar datos de la placa">
                    <span wire:loading.remove wire:target="lookupPlate"><i class="ti ti-search"></i></span>
                    <span wire:loading wire:target="lookupPlate" class="spinner-border spinner-border-sm"></span>
                </button>
            </div>
        @endif
        @error('plate') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Sede --}}
    <div class="col-6 col-md-2">
        <label for="headquarter" class="form-label">Sede</label>
        <select id="headquarter" class="form-select form-select-sm" wire:model="headquarter">
            <option value="">Seleccione</option>
            <option value="LA MERCED (SELVA CENTRAL)">LA MERCED (SELVA CENTRAL)</option>
            <option value="LIMA">LIMA</option>
            <option value="MADRE DE DIOS">MADRE DE DIOS</option>
            <option value="MAYNAS">MAYNAS</option>
            <option value="MOQUEGUA)">MOQUEGUA)</option>
            <option value="MOYOBAMBA">MOYOBAMBA</option>
            <option value="NAZCA">NAZCA</option>
            <option value="OTUZCO">OTUZCO</option>
            <option value="PASCO">PASCO</option>
            <option value="PISCO">PISCO</option>
            <option value="PIURA">PIURA</option>
            <option value="PUCALLPA">PUCALLPA</option>
            <option value="PUNO">PUNO</option>
            <option value="QUILLABAMBA">QUILLABAMBA</option>
            <option value="SAN PEDRO">SAN PEDRO</option>
            <option value="SATIPO">SATIPO</option>
            <option value="SICUANI">SICUANI</option>
            <option value="SULLANA">SULLANA</option>
            <option value="TACNA">TACNA</option>
            <option value="TARAPOTO">TARAPOTO</option>
            <option value="TARMA">TARMA</option>
            <option value="TINGO MARIA">TINGO MARIA</option>
            <option value="TRUJILLO">TRUJILLO</option>
            <option value="TUMBES">TUMBES</option>
            <option value="YURIMAGUAS">YURIMAGUAS</option>
        </select>
        @error('headquarter') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- F. Ingreso --}}
    <div class="col-6 col-md-2">
        <label for="entry_date" class="form-label {{ $lockForController ? 'text-muted' : '' }}">F. Ingreso</label>
        <input id="entry_date" type="text" class="form-control form-control-sm" wire:model="entry_date" @if($lockForController) disabled @endif>
        @error('entry_date') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Fecha Cese --}}
    <div class="col-6 col-md-2">
        <label for="termination_date" class="form-label {{ $lockForController ? 'text-muted' : '' }}">Fecha Cese</label>
        <input id="termination_date" type="text" class="form-control form-control-sm" wire:model="termination_date" placeholder="0000-00-00" @if($lockForController) disabled @endif>
        @error('termination_date') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Categoría --}}
    @include('livewire.vehicles._catalog-select', ['field' => 'class', 'label' => 'Categoría', 'value' => $class])

    {{-- Marca --}}
    @include('livewire.vehicles._catalog-select', ['field' => 'brand', 'label' => 'Marca', 'value' => $brand])

    {{-- Año --}}
    <div class="col-6 col-md-2">
        <label for="year" class="form-label">Año Fabricación</label>
        <input id="year" type="number" inputmode="numeric" class="form-control form-control-sm" placeholder="Ej. 2020" wire:model="year" min="1900" max="2100" step="1">
        @error('year') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Modelo --}}
    @include('livewire.vehicles._catalog-select', ['field' => 'model', 'label' => 'Modelo', 'value' => $model])

    {{-- Carrocería --}}
    @include('livewire.vehicles._catalog-select', ['field' => 'bodywork', 'label' => 'Carrocería', 'value' => $bodywork])

    {{-- Color --}}
    @include('livewire.vehicles._catalog-select', ['field' => 'color', 'label' => 'Color', 'value' => $color])

    {{-- Modalidad --}}
    @include('livewire.vehicles._catalog-select', ['field' => 'type', 'label' => 'Modalidad', 'value' => $type])

    {{-- Condición --}}
    <div class="col-6 col-md-2">
        <label for="condition" class="form-label {{ $lockForController ? 'text-muted' : '' }}">Condición</label>
        <select id="condition" class="form-select form-select-sm" wire:model="condition" @if($lockForController) disabled @endif>
            <option value="">Seleccione</option>
            <option value="DT">DT</option>
            <option value="GN">GN</option>
            <option value="EX">EX</option>
            <option value="EX5">EX5</option>
        </select>
        @error('condition') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Empresa Afiliada --}}
    <div class="col-6 col-md-3">
        <label for="affiliated_company" class="form-label">Empresa Afiliada</label>
        <input id="affiliated_company" type="text" class="form-control form-control-sm" placeholder="Ingresar empresa afiliada" wire:model="affiliated_company">
        @error('affiliated_company') <span class="title-modules">{{ $message }}</span> @enderror
    </div>



    {{-- Propietario --}}
    <div class="col-6 col-md-3">
        <label for="owner_id" class="form-label">Propietario</label>
        {{-- Con buscador (Select2); mismo envoltorio que _catalog-select --}}
        <div wire:ignore wire:key="vsel-owner-{{ $owner_id }}">
            <select id="owner_id" class="form-select form-select-sm select2-simple" wire:model="owner_id" data-placeholder="Seleccionar">
                <option value=""></option>
                @foreach($listOwners as $owner)
                    <option value="{{ $owner->id }}">{{ $loop->iteration }}.- {{ $owner->name }}</option>
                @endforeach
            </select>
        </div>
        @error('owner_id') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Conductor --}}
    <div class="col-6 col-md-3">
        <label for="driver_id" class="form-label">Conductor</label>
        {{-- La clave incluye "No trabaja" para que el disabled llegue al cuadro de Select2 --}}
        <div wire:ignore wire:key="vsel-driver-{{ $driver_id }}-{{ $not_working ? 'off' : 'on' }}">
            <select id="driver_id" class="form-select form-select-sm select2-simple" wire:model.live="driver_id" data-placeholder="Seleccionar" @if($not_working) disabled @endif>
                <option value=""></option>
                @foreach($listDrivers as $driver)
                    <option value="{{ $driver->id }}">{{$loop->iteration}}.- {{ $driver->name }}</option>
                @endforeach
            </select>
        </div>
        @error('driver_id') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- No trabaja --}}
    <div class="col-6 col-md-2 d-flex align-items-end">
        <div class="form-check mb-1">
            <input id="not_working" type="checkbox" class="form-check-input" wire:model.live="not_working">
            <label for="not_working" class="form-check-label ms-2" title="Marca el vehículo como fuera de operación; libera al conductor y muestra un recordatorio con los días transcurridos (a los 60 se sugiere la baja).">
                No trabaja
            </label>
        </div>
    </div>

    {{-- Combustible --}}
    @include('livewire.vehicles._catalog-select', ['field' => 'fuel', 'label' => 'Combustible', 'value' => $fuel])

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







    {{-- Soat F.V --}}
    <div class="col-6 col-md-2">
        <label for="soat_date" class="form-label">Soat F.V</label>
        <input id="soat_date" type="text" class="form-control form-control-sm" wire:model="soat_date">
        @error('soat_date') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Certificado F.V --}}
    <div class="col-6 col-md-2">
        <label for="certificate_date" class="form-label">Certificado Op.F.V</label>
        <input id="certificate_date" type="text" class="form-control form-control-sm" wire:model="certificate_date">
        @error('certificate_date') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Revisión Técnica F.V --}}
    <div class="col-6 col-md-2">
        <label for="technical_review" class="form-label">Revisión Técnica F.V</label>
        <input id="technical_review" type="text" class="form-control form-control-sm" wire:model="technical_review">
        @error('technical_review') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    {{-- Sort Order --}}
    <div class="col-6 col-md-1">
        <label for="sort_order" class="form-label {{ $lockForController ? 'text-muted' : '' }}">Orden</label>
        <input id="sort_order" type="number" inputmode="numeric" class="form-control form-control-sm"  wire:model="sort_order" min="1" step="1" @if($lockForController) disabled @endif>
        @error('sort_order') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    <div class="col-12"><div class="app-divider-v justify-content-center"><p>DETALLES Y FOTOS</p></div></div>

    {{-- Detalle --}}
    <div class="col-12">
        <label for="detail" class="form-label">Detalle</label>
        <textarea id="detail" rows="2" class="form-control form-control-sm" placeholder="Observaciones..." wire:model="detail"></textarea>
        @error('detail') <span class="title-modules">{{ $message }}</span> @enderror
    </div>

    @include('livewire.partials._multi-photos', ['entityLabel' => 'vehículo'])
</div>

@include('partials.image-lightbox')
