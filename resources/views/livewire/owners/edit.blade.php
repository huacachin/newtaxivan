@push('datepicker_css')
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>
@endpush
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">MODIFICAR DATOS DEL PROPIETARIO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="{{route('settings.owners.index')}}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Propietarios</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Editar</a>
                </li>
            </ul>
        </div>
    </div>
    <div class="card">
        <div class="card-body">

            @include('livewire.owners._form', ['highlightExpiration' => true])

            <div class="mt-3 d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-sm btn-primary" wire:click="update">Guardar</button>
                @role('admin')
                <button type="button" class="btn btn-sm btn-danger" wire:click="questionDelete({{ $owner->id }})">Eliminar</button>
                @endrole
                <a href="{{ route('settings.owners.index') }}" class="btn btn-sm btn-secondary">Cancelar</a>
            </div>

        </div>

    </div>
</div>

@push('datepicker_js')
    <script>
        $( function() {
            var wire = @this;
            initLivewireDatepicker([
                ['#birthdate',                'birthdate'],
                ['#document_expiration_date', 'document_expiration_date'],
            ], wire);
        });
    </script>
@endpush


