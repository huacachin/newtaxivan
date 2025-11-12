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

                @include('livewire.owners._form')

            <div class="mt-3 d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-sm btn-primary" wire:click="update">Guardar</button>
                <a href="{{ route('settings.owners.index') }}" class="btn btn-sm btn-secondary">Cancelar</a>
            </div>

        </div>

    </div>
</div>

