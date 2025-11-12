{{-- resources/views/livewire/vehicles/index.blade.php --}}
@push('styles')



    <style>
        table {
            border-collapse: collapse; /* opcional */
            width: 100%;
        }

        th,td {
            padding: 1px !important;
            font-size: 10px !important;
            text-align: center !important;
            vertical-align: middle; /* <-- clave */
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
            <h4 class="main-title title-modules">VEHÍCULOS</h4>
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
                    <a href="#" class="f-s-14">Vehículos</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">

        <!-- Tabla -->
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">

                        <h5 class="mb-1">Total vehículos: {{ $vehicles->count() }}</h5>
                        <p class="mb-0">
                            <strong>D2:</strong> {{ $vehicles->where('fuel','D2')->count() }} ·
                            <strong>Gas:</strong> {{ $vehicles->where('fuel','GAS')->count() }} ·
                            <strong>V.T:</strong> {{ $vehicles->whereIn('fuel', ['GAS','D2'])->count() }} ·
                            <strong>V.Q.N.T:</strong> {{ $vehicles->whereNotIn('fuel', ['GAS','D2'])->count() }} ·
                            <strong>Propietario:</strong> {{ $owners }} ·
                            <strong>Conductor:</strong> {{ $drivers }}
                        </p>
                        <div class="row my-2">
                            <div class="col-12">
                                <!-- Una sola fila: no-wrap + scroll horizontal -->
                                <div class="d-flex flex-wrap align-items-end gap-2 overflow-auto py-1">



                                    <!-- Estado -->
                                    <div class="flex-shrink-0" style="min-width: 160px;">
                                        <select class="form-select form-select-sm"
                                                aria-label="Estado del vehiculo"
                                                wire:model.live="status">
                                            <option value="active">Activo</option>
                                            <option value="inactive">Cesado</option>
                                        </select>
                                    </div>

                                    <!-- Filtro -->
                                    <div class="flex-shrink-0" style="min-width: 180px;">
                                        <select class="form-select form-select-sm"
                                                aria-label="Filtro"
                                                wire:model.live="filter">
                                            <option value="plate">Placa</option>
                                            <option value="brand">Marca</option>
                                            <option value="year">Año</option>
                                            <option value="owner">Propietario</option>
                                            <option value="driver">Conductor</option>
                                            <option value="condition">Condición</option>
                                            <option value="company">Empresa</option>
                                            <option value="category">Categoría</option>
                                            <option value="code">Código</option>
                                        </select>
                                    </div>

                                    <!-- Buscar -->
                                    <div class="flex-shrink-0">
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

                                    <button id="down"
                                            class="btn btn-sm btn-primary flex-shrink-0">
                                        <i class="ti ti-square-chevrons-down f-s-12"></i>
                                    </button>

                                    <button class="btn btn-sm btn-primary flex-shrink-0"
                                            wire:click="export">
                                        <i class="ti ti-file-analytics f-s-12"></i>
                                    </button>



                                </div>
                            </div>
                        </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th class="sticky-col">Acción</th>
                                <th>Item</th>
                                <th>Cod</th>
                                <th class="sticky-col-2">Placa</th>
                                <th>Marca</th>
                                <th>Año</th>
                                <th>Categoría</th>
                                <th>Propietario</th>
                                <th>Conductor</th>
                                <th>Modalidad</th>
                                <th>Combu.</th>
                                <th>Condición</th>
                                <th>Empresa Afil.</th>
                                @if($status === "inactive")
                                    <th>Fecha Cese</th>
                                @endif
                            </tr>
                            </thead>

                            <tbody>
                            @if($vehicles->count())
                                @foreach($vehicles as $vehicle)
                                    @php
                                        $cond = strtoupper((string)($vehicle->condition ?? ''));
                                        $condClass = 'cond-badge ';
                                        if (str_starts_with($cond,'EX')) { $condClass .= 'cond-EX'; }
                                        elseif ($cond === 'GN') { $condClass .= 'cond-GN'; }
                                        elseif ($cond === 'DT') { $condClass .= 'cond-DT'; }
                                    @endphp
                                    <tr>
                                        <td>
                                            <i class="ti ti-edit f-s-18 text-success"
                                               style="cursor:pointer"
                                               wire:click="openEditWindow({{ $vehicle->id }})"></i>
                                        </td>

                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $vehicle->sort_order }}</td>

                                        <td>
                                            {{ $vehicle->plate }}
                                            @if(!empty($vehicle->badges))
                                                <span class="ms-2 d-inline-flex gap-1 align-items-center">
                                                    @foreach($vehicle->badges as $b)
                                                        <span class="badge {{ $b['class'] }} text-white" title="{{ $b['title'] }}">
                                                            {{ $b['abbr'] }}
                                                        </span>
                                                    @endforeach
                                                </span>
                                            @endif
                                        </td>

                                        <td>{{ $vehicle->brand }}</td>
                                        <td>{{ $vehicle->year }}</td>
                                        <td>{{ $vehicle->class }}</td>
                                        <td>{{ $vehicle->owner->name ?? '-' }}</td>
                                        <td>{{ $vehicle->driver->name ?? '-' }}</td>
                                        <td>{{ $vehicle->type }}</td>
                                        <td>{{ $vehicle->fuel }}</td>
                                        <td>
                                            <span class="{{ $condClass }}">{{ $cond ?: '-' }}</span>
                                        </td>
                                        <td>{{ $vehicle->affiliated_company }}</td>

                                        @if($status === "inactive")
                                            <td>{{ $vehicle->termination_date?->format('d/m/Y') ?? '-' }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    @php $colspan = 12 + ($status === "inactive" ? 1 : 0); @endphp
                                    <td colspan="{{ $colspan }}">No se encontrarón resultados</td>
                                </tr>
                            @endif
                            </tbody>

                            {{-- (Opcional) Pie oscuro con totales rápidos
                            <tfoot class="text-center fw-semibold">
                                <tr>
                                    <td class="sticky-col"></td>
                                    <td></td>
                                    <td class="sticky-col-2"></td>
                                    <td colspan="{{ 7 + ($status === 'inactive' ? 1 : 0) }}" class="text-end">TOTAL</td>
                                    <td colspan="2" class="text-start">Registros: {{ $vehicles->count() }}</td>
                                </tr>
                            </tfoot>
                            --}}
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- /Tabla -->
    </div>

    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="export">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>
