{{-- resources/views/livewire/vehicles/expiration-edit.blade.php --}}
@push('datepicker_css')
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>
@endpush
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">VEHICULO: ACTUALIZAR VENCIMIENTO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="{{ route('settings.vehicles.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Vehículos</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <span class="f-s-14">{{ $meta['label'] }}</span>
                </li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row g-3">
                {{-- Placa --}}
                <div class="col-6 col-md-2">
                    <label class="form-label text-muted">Placa</label>
                    <input type="text" class="form-control form-control-sm" value="{{ $vehicle->plate }}" disabled>
                </div>

                {{-- Documento --}}
                <div class="col-6 col-md-3">
                    <label class="form-label text-muted">Documento</label>
                    <input type="text" class="form-control form-control-sm" value="{{ $meta['label'] }}" disabled>
                </div>

                {{-- Vencimiento actual --}}
                <div class="col-6 col-md-4">
                    <label class="form-label text-muted">
                        Vencimiento actual
                        @switch($meta['status'])
                            @case('expired')
                                <span class="badge bg-danger ms-1">Vencido hace {{ $meta['days'] }} día(s)</span>
                                @break
                            @case('today')
                                <span class="badge bg-danger ms-1">Vence hoy</span>
                                @break
                            @case('upcoming')
                                <span class="badge bg-{{ $meta['color'] }} ms-1">Vence en {{ $meta['days'] }} día(s)</span>
                                @break
                            @case('ok')
                                <span class="badge bg-secondary ms-1">Faltan {{ $meta['days'] }} día(s)</span>
                                @break
                            @default
                                <span class="badge bg-secondary ms-1">Sin registrar</span>
                        @endswitch
                    </label>
                    <input type="text" class="form-control form-control-sm" value="{{ $meta['current'] ?? '—' }}" disabled>
                </div>

                {{-- Nueva fecha --}}
                <div class="col-6 col-md-3">
                    <label for="expiration_value" class="form-label">Nueva fecha de vencimiento <span class="text-danger">*</span></label>
                    <input id="expiration_value" type="text" class="form-control form-control-sm" style="background-color: yellow;" wire:model="value" autocomplete="off" placeholder="aaaa-mm-dd">
                    @error('value') <span class="title-modules">{{ $message }}</span> @enderror
                </div>

                {{-- Fotos a agregar (no muestra las existentes) --}}
                @include('livewire.partials._multi-photos', ['entityLabel' => 'vehículo'])

                @error('image_files')
                    <div class="col-12">
                        <span class="title-modules d-block">{{ $message }}</span>
                    </div>
                @enderror
            </div>

            @include('partials.image-lightbox')

            <div class="mt-3 d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-sm btn-primary" wire:click="save" wire:loading.attr="disabled">
                    <span wire:loading wire:target="save" class="spinner-border spinner-border-sm"></span>
                    <span wire:loading.remove wire:target="save">Guardar</span>
                </button>
                <a href="{{ route('settings.vehicles.index') }}" class="btn btn-sm btn-secondary">
                    <i class="ti ti-arrow-back-up"></i> Regresar
                </a>
            </div>
        </div>
    </div>
</div>

@push('datepicker_js')
    <script>
        $(function () {
            var wire = @this;
            initLivewireDatepicker([
                ['#expiration_value', 'value'],
            ], wire);
        });
    </script>
@endpush
