{{-- resources/views/livewire/vehicles/_catalog-select.blade.php --}}
{{--
    Select de catálogo del vehículo (Select2 en modo tags): busca entre las opciones o
    deja escribir una que no está; lo nuevo se guarda al guardar el vehículo
    (App\Services\VehicleCatalogs). Recibe $field (propiedad y columna), $label y $value.

    El envoltorio lleva wire:ignore para que el morph de Livewire no desarme el cuadro de
    Select2, y wire:key con el valor para que se rehaga cuando el servidor lo cambia
    (consulta de placa, Limpiar). El error va fuera del envoltorio.
--}}
<div class="col-6 col-md-2 {{ in_array($field, $autoFilled ?? [], true) ? 'field-from-api' : '' }}">
    <label for="{{ $field }}" class="form-label">{{ $label }}</label>
    <div wire:ignore wire:key="vsel-{{ $field }}-{{ md5((string) $value) }}">
        <select id="{{ $field }}" class="form-select form-select-sm select2-tags" wire:model="{{ $field }}" data-placeholder="Seleccione">
            <option value=""></option>
            @foreach($catalogOptions[$field] as $option)
                <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
        </select>
    </div>
    @error($field) <span class="title-modules">{{ $message }}</span> @enderror
</div>
