{{-- resources/views/livewire/vehicles/edit.blade.php --}}
@push('datepicker_css')
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>
@endpush
@push('styles')
    <style>
        #birthdate,#document_expiration_date,#license_issue_date,#license_revalidation_date,#contract_start,#contract_end,
        #credential,#credential_expiration_date,#road_education,#road_education_expiration_date{
            background: url({{asset('images/calen.png')}}) #fff no-repeat right;
            background-size: 21px 16px;
            padding-right: 2rem;
        }
    </style>
@endpush
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">CONDUCTOR: ACTUALIZACIÓN</h4>
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
                    <a href="#" class="f-s-14">Editar</a>
                </li>
            </ul>
        </div>
    </div>
    <div class="card">
        <div class="card-body">

            @include('livewire.drivers._form')

            <div class="mt-3 d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-sm btn-primary" wire:click="update">Guardar</button>
                <a href="{{ route('settings.drivers.index') }}" class="btn btn-sm btn-secondary">Cancelar</a>
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

            $( "#license_issue_date" ).datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: 'yy-mm-dd',
                onSelect: function (dateText, inst) {
                    @this.set('license_issue_date', dateText);
                }
            });

            $( "#license_revalidation_date" ).datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: 'yy-mm-dd',
                onSelect: function (dateText, inst) {
                    @this.set('license_revalidation_date', dateText);
                }
            });

            $( "#contract_start" ).datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: 'yy-mm-dd',
                onSelect: function (dateText, inst) {
                    @this.set('contract_start', dateText);
                }
            });

            $( "#contract_end" ).datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: 'yy-mm-dd',
                onSelect: function (dateText, inst) {
                    @this.set('contract_end', dateText);
                }
            });

            $( "#road_education" ).datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: 'yy-mm-dd',
                onSelect: function (dateText, inst) {
                    @this.set('road_education', dateText);
                }
            });

            $( "#road_education_expiration_date" ).datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: 'yy-mm-dd',
                onSelect: function (dateText, inst) {
                    @this.set('road_education_expiration_date', dateText);
                }
            });


            $( "#credential" ).datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: 'yy-mm-dd',
                onSelect: function (dateText, inst) {
                    @this.set('credential', dateText);
                }
            });


            $( "#credential_expiration_date" ).datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: 'yy-mm-dd',
                onSelect: function (dateText, inst) {
                    @this.set('credential_expiration_date', dateText);
                }
            });

        });
    </script>
@endpush

