@push('datepicker_css')
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>
@endpush
@push('styles')
    <style>
        #birthdate,#document_expiration_date{
            background: url({{asset('images/calen.png')}}) #fff no-repeat right;
            background-size: 21px 16px;
            padding-right: 2rem;
        }
    </style>
@endpush
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">AGREGAR NUEVO PROPIETARIO</h4>
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
                    <a href="#" class="f-s-14">Agregar</a>
                </li>
            </ul>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
                @include('livewire.owners._form')
            <div class="mt-3 d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-sm btn-primary" wire:click="save">Guardar</button>
                <a href="{{ route('settings.owners.index') }}" class="btn btn-sm btn-secondary">Cancelar</a>
            </div>

        </div>

    </div>
</div>

@push('datepicker_js')
    <script>
        $( function() {
            $( "#birthdate" ).datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: 'yy-mm-dd',
                onSelect: function (dateText, inst) {
                    @this.set('birthdate', dateText);
                }
            });

            $( "#document_expiration_date" ).datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: 'yy-mm-dd',
                onSelect: function (dateText, inst) {
                    @this.set('document_expiration_date', dateText);
                }
            });

        });
    </script>
@endpush

