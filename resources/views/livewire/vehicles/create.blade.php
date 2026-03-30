{{-- resources/views/livewire/vehicles/edit.blade.php --}}
@push('datepicker_css')
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>
@endpush
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">VEHICULO: AGREGAR NUEVO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="{{route('settings.vehicles.index')}}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Vehículos</span>
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

           @include('livewire.vehicles._form')

           <div class="mt-3 d-flex gap-2 justify-content-end">
               <button type="button" class="btn btn-sm btn-primary" wire:click="save">Guardar</button>
               <button type="button" class="btn btn-sm btn-danger" wire:click="clean">Limpiar</button>
               <a href="{{ route('settings.vehicles.index') }}" class="btn btn-sm btn-secondary"><i class="ti ti-arrow-back-up"></i> Regresar</a>
           </div>

       </div>

   </div>
</div>

@push('datepicker_js')
    <script>
        $( function() {
            var wire = @this;
            initLivewireDatepicker([
                ['#entry_date',       'entry_date'],
                ['#termination_date', 'termination_date'],
                ['#soat_date',        'soat_date'],
                ['#certificate_date', 'certificate_date'],
                ['#technical_review', 'technical_review'],
            ], wire);
        });
    </script>
@endpush


