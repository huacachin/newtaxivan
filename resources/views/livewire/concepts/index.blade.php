{{-- resources/views/livewire/concepts/index.blade.php --}}
<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">LISTADO GENERAL DE CONCEPTOS</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Configuración</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Conceptos</a>
                </li>
            </ul>
        </div>
    </div>

    {{-- Flash alerts --}}
    @if(session('concept_success'))
        <div class="alert alert-success alert-dismissible fade show py-2 mb-2" role="alert">
            {{ session('concept_success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('concept_error'))
        <div class="alert alert-danger alert-dismissible fade show py-2 mb-2" role="alert">
            {{ session('concept_error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row table-section">

        <!-- Tabla -->
        <div class="col-xl-12">
            <div class="card shadow-sm">

                <div class="card-body pb-2">

                    <div class="row my-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-2 overflow-auto py-1">

                                <!-- Input con ancho fijo (ajusta a gusto) -->
                                <div class="flex-shrink-0" style="width: 260px;">
                                    <input type="search"
                                           class="form-control form-control-sm"
                                           placeholder="Buscar por nombre"
                                           aria-label="Buscar"
                                           wire:model.live="search">
                                </div>

                                <!-- Botón a la derecha (ancho intrínseco) -->
                                <a class="btn btn-sm btn-primary flex-shrink-0"
                                   href="{{ route('settings.concepts.create') }}">
                                    <i class="ti ti-square-plus f-s-12"></i> Nuevo
                                </a>

                                <button class="btn btn-sm btn-primary flex-shrink-0"
                                        wire:click="export">
                                    <i class="ti ti-file-analytics f-s-12"></i> Exportar
                                </button>

                            </div>
                        </div>
                    </div>
                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>Id</th>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Acción</th>
                            </tr>
                            </thead>

                            <tbody>
                            @if($concepts->count() > 0)
                                @foreach($concepts as $concept)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $concept->code }}</td>
                                        <td>{{ $concept->name }}</td>
                                        <td>{{ ucfirst($concept->type) }}</td>
                                        <td>
                                            @hasanyrole('director|gerente')
                                            <a href="{{ route('settings.concepts.edit', $concept->id) }}">
                                                <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"></i>
                                            </a>
                                            @endhasanyrole
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td class=" py-4 text-muted" colspan="5">No se encontraron resultados
                                    </td>
                                </tr>
                            @endif
                            </tbody>

                            <tfoot class="bg-primary">
                            <tr>
                                <td class="sticky-col"></td>
                                <td class="text-start">TOTAL</td>
                                <td class="sticky-col-2"></td>
                                <td colspan="2" class="num">{{ number_format($concepts->count()) }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="save,update">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>
