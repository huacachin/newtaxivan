@push('styles')
    <style>
        table {
            border-collapse: collapse; /* opcional */
            width: 100%;
        }

        th,td{
            padding: 1px !important;
            font-size: 10px !important;
            text-align: center !important;
            vertical-align: middle;   /* <-- clave */
            white-space: normal !important;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .btn, input,select {
            font-size: 10px !important;
        }

        .screen-overlay {
            position: fixed;
            inset: 0;                 /* full viewport */
            display: none;            /* Livewire lo pondrá en flex */
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,.35);
            backdrop-filter: blur(2px);
            z-index: 2000;            /* sobre modals/backdrops de Bootstrap */
            pointer-events: all;      /* bloquea clics */
        }
    </style>
@endpush

<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">LISTADO GENERAL DE CONDUCTORES ({{ $drivers->count() }})</h4>
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
                    <a href="#" class="f-s-14">Conductores</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <!-- Tabla principal: Conductores -->
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <div class="row my-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-2 overflow-auto py-1">



                                <!-- Filtro -->
                                <div class="flex-shrink-0" style="min-width: 160px;">
                                    <select class="form-select form-select-sm"
                                            aria-label="Selecciona item a filtrar"
                                            wire:model.live="filter">
                                        <option value="plate">Placa</option>
                                        <option value="name">Nombre</option>
                                        <option value="code">Código</option>
                                    </select>
                                </div>

                                <!-- Buscar -->
                                <div class="flex-shrink-0" style="min-width: 200px;">
                                    <input type="search"
                                           class="form-control form-control-sm"
                                           placeholder="Buscar..."
                                           aria-label="Buscar"
                                           wire:model.live="search">
                                </div>

                                <!-- Botones -->
                                <button class="btn btn-sm btn-primary flex-shrink-0"
                                        wire:click="openAddWindow">
                                    <i class="ti ti-square-plus f-s-12"></i> Nuevo
                                </button>

                                <button class="btn btn-sm btn-primary flex-shrink-0"
                                        wire:click="export">
                                    <i class="ti ti-file-analytics f-s-12"></i> Exportar
                                </button>

                                <button id="down"
                                        class="btn btn-sm btn-primary flex-shrink-0">
                                    <i class="ti ti-square-chevrons-down f-s-12"></i>
                                </button>

                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>Id</th>
                                <th>Placa</th>
                                <th>Nombre</th>
                                <th>DNI</th>
                                <th>I.Contrato</th>
                                <th>F.Contrato</th>
                                <th>Celular</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if($drivers->count() > 0)
                                @foreach($drivers as $driver)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $driver->vehicles->first()->plate ?? '—' }}</td>
                                        <td>{{ $driver->name }}</td>
                                        <td>{{ $driver->document_number }}</td>
                                        <td>
                                            {{ ($driver->contract_start && $driver->contract_start !== '0000-00-00')
                                                ? \Illuminate\Support\Carbon::parse($driver->contract_start)->format('d/m/Y') : '—' }}
                                        </td>
                                        <td>
                                            {{ ($driver->contract_end && $driver->contract_end !== '0000-00-00')
                                                ? \Illuminate\Support\Carbon::parse($driver->contract_end)->format('d/m/Y') : '—' }}
                                        </td>
                                        <td>{{ $driver->phone }}</td>
                                        <td>{{ $driver->condition }}</td>
                                        <td width="10">
                                            <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"
                                               wire:click="openEditWindow({{ $driver->id }})"></i>
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr><td colspan="9">No se encontrarón resultados</td></tr>
                            @endif
                            </tbody>
                            <tfoot class="bg-primary">
                            <tr>
                                <td colspan="9" class="text-end f-w-600">TOTAL: {{ $drivers->count() }}</td>
                            </tr>
                            </tfoot>
                        </table>

                        <h5 class="mb-2 title-modules text-center">Conductores Libres: {{ $driversFree->count() }}</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover">
                                <thead class="bg-primary">
                                <tr>
                                    <th>Id</th>
                                    <th>Nombre</th>
                                    <th>DNI</th>
                                    <th>I.Contrato</th>
                                    <th>F.Contrato</th>
                                    <th>Celular</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($driversFree as $driver)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $driver->name }}</td>
                                        <td>{{ $driver->document_number }}</td>
                                        <td>
                                            {{ ($driver->contract_start && $driver->contract_start !== '0000-00-00')
                                                ? \Illuminate\Support\Carbon::parse($driver->contract_start)->format('d/m/Y') : '—' }}
                                        </td>
                                        <td>
                                            {{ ($driver->contract_end && $driver->contract_end !== '0000-00-00')
                                                ? \Illuminate\Support\Carbon::parse($driver->contract_end)->format('d/m/Y') : '—' }}
                                        </td>
                                        <td>{{ $driver->phone }}</td>
                                        <td>{{ $driver->condition }}</td>
                                        <td width="10">
                                            <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"
                                               wire:click="openEditWindow({{ $driver->id }})"></i>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8">No se encontrarón resultados</td></tr>
                                @endforelse
                                </tbody>
                                <tfoot class="bg-primary">
                                <tr>
                                    <td colspan="8" class="text-end f-w-600">TOTAL: {{ $driversFree->count() }}</td>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="export,openAddModal,openEditModal,save,update">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>
