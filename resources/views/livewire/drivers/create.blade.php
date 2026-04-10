{{-- resources/views/livewire/vehicles/edit.blade.php --}}
@push('datepicker_css')
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>
@endpush
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">AGREGAR NUEVO CONDUCTOR</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="{{route('settings.drivers.index')}}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Conductores</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Agregar</a>
                </li>
            </ul>
        </div>
    </div>
    <div class="card">
        <div class="card-body">

            @include('livewire.drivers._form')

            <div class="mt-3 d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-sm btn-primary" wire:click="save" wire:loading.attr="disabled"><span wire:loading wire:target="save" class="spinner-border spinner-border-sm"></span><span wire:loading.remove wire:target="save">Guardar</span></button>
                <button type="button" class="btn btn-sm btn-danger" wire:click="clean">Limpiar</button>
                <a href="{{ route('settings.drivers.index') }}" class="btn btn-sm btn-secondary"><i class="ti ti-arrow-back-up"></i> Regresar</a>
            </div>

        </div>

    </div>
</div>

@push('datepicker_js')
    <script>
        $( function() {
            var wire = @this;
            initLivewireDatepicker([
                ['#birthdate',                     'birthdate'],
                ['#document_expiration_date',      'document_expiration_date'],
                ['#license_issue_date',            'license_issue_date'],
                ['#license_revalidation_date',     'license_revalidation_date'],
                ['#contract_start',                'contract_start'],
                ['#contract_end',                  'contract_end'],
                ['#road_education',                'road_education'],
                ['#road_education_expiration_date','road_education_expiration_date'],
                ['#credential',                    'credential'],
                ['#credential_expiration_date',    'credential_expiration_date'],
            ], wire);
        });
    </script>
@endpush
