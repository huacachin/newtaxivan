{{-- resources/views/livewire/vehicles/index.blade.php --}}
@push('styles')
    <style>
        /* ===== Encabezado/pie oscuros y sticky ===== */
        .tableFixHead thead th{
            position: sticky; top: 0; z-index: 3;
            background-color:#009BDC !important; color:#fff !important;
            vertical-align: middle;
        }
        .tableFixHead tfoot th,
        .tableFixHead tfoot td{
            position: sticky; bottom: 0; z-index: 2;
            background-color:#009BDC !important; color:#fff !important;
        }

        /* ===== Columnas sticky (Id y Placa) ===== */
        :root{ --w-item:64px; --w-plate:120px; }
        .tableFixHead .sticky-col   { position: sticky; left:0;              z-index:4; min-width:var(--w-item); }
        .tableFixHead .sticky-col-2 { position: sticky; left:var(--w-item);  z-index:4; min-width:var(--w-plate); }
        .tableFixHead tbody td.sticky-col,
        .tableFixHead tbody td.sticky-col-2{
            background:#fff !important; background-clip: padding-box;
            box-shadow: 1px 0 0 rgba(0,0,0,.06) inset; white-space: nowrap;
        }

        /* ===== Utilidades ===== */
        th, td{ white-space:nowrap; vertical-align:middle; text-align:center; }
        .text-start{ text-align:left !important; }

        /* ===== Badges de condición ===== */
        .cond-badge{ display:inline-block; padding:.2rem .45rem; border-radius:.35rem;
            font-size:.75rem; font-weight:600; letter-spacing:.3px; }
        .cond-EX{  background:#e2e8f0; color:#334155; }   /* EX/EX5 gris */
        .cond-GN{  background:#fef3c7; color:#92400e; }   /* GN ámbar */
        .cond-DT{  background:#dcfce7; color:#166534; }   /* DT verde */
    </style>
@endpush

<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Vehículos</h4>
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
                <div class="card-header">
                    <h5 class="mb-1">Total vehículos: {{ $vehicles->count() }}</h5>
                    <p class="mb-0">
                        <strong>D2:</strong> {{ $vehicles->where('fuel','D2')->count() }} ·
                        <strong>Gas:</strong> {{ $vehicles->where('fuel','GAS')->count() }} ·
                        <strong>V.T:</strong> {{ $vehicles->whereIn('fuel', ['GAS','D2'])->count() }} ·
                        <strong>V.Q.N.T:</strong> {{ $vehicles->whereNotIn('fuel', ['GAS','D2'])->count() }} ·
                        <strong>Propietario:</strong> {{ $owners }} ·
                        <strong>Conductor:</strong> {{ $drivers }}
                    </p>
                    <div class="row g-3 align-items-end mt-2">
                        <div class="col-xl-3 col-md-2">
                            <form class="app-form app-icon-form" action="#">
                                <div class="position-relative">
                                    <input type="search" class="form-control" placeholder="Buscar..."
                                           aria-label="Buscar" wire:model.live="search">
                                    <i class="ti ti-search text-dark"></i>
                                </div>
                            </form>
                        </div>

                        <div class="col-xl-2 col-md-2">
                            <select class="form-select" aria-label="Estado del vehiculo" wire:model.live="status">
                                <option value="active">Activo</option>
                                <option value="inactive">Cesado</option>
                            </select>
                        </div>

                        <div class="col-xl-2 col-md-2">
                            <select class="form-select" aria-label="Filtro" wire:model.live="filter">
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

                        <div class="col-xl-2 col-md-2">
                            <button class="btn btn-primary w-100" wire:click="openAddModal">
                                <i class="ti ti-square-plus f-s-17"></i> Nuevo
                            </button>
                        </div>
                        <div class="col-xl-2 col-md-2">
                            <button class="btn btn-primary w-100" wire:click="export">
                                <i class="ti ti-file-analytics f-s-17"></i> Exportar
                            </button>
                        </div>
                        <div class="col-xl-1 col-md-2">
                            <button id="down" class="btn btn-primary w-100">
                                <i class="ti ti-square-chevrons-down f-s-17"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive tableFixHead">
                        <table class="table table-sm table-bordered table-striped table-hover align-middle">
                            <thead class="text-center">
                            <tr>
                                <th class="sticky-col">Acción</th>
                                <th>Id</th>
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
                                        <td class="sticky-col">
                                            <i class="ti ti-edit f-s-18 text-success"
                                               style="cursor:pointer"
                                               wire:click="openEditModal({{ $vehicle->id }})"></i>
                                        </td>

                                        <td>{{ $loop->iteration }}</td>

                                        <td class="sticky-col-2 text-start">
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
                                        <td class="text-start">{{ $vehicle->owner->name ?? '-' }}</td>
                                        <td class="text-start">{{ $vehicle->driver->name ?? '-' }}</td>
                                        <td>{{ $vehicle->type }}</td>
                                        <td>{{ $vehicle->fuel }}</td>
                                        <td>
                                            <span class="{{ $condClass }}">{{ $cond ?: '-' }}</span>
                                        </td>
                                        <td class="text-start">{{ $vehicle->affiliated_company }}</td>

                                        @if($status === "inactive")
                                            <td>{{ $vehicle->termination_date?->format('d/m/Y') ?? '-' }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    @php $colspan = 12 + ($status === "inactive" ? 1 : 0); @endphp
                                    <td colspan="{{ $colspan }}" class="text-center">No se encontrarón resultados</td>
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

    {{-- Modal Crear --}}
    <div class="modal fade" id="modalAddVehicle" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Vehiculo</h5>
                    <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    {{-- Tus inputs (se dejan tal cual) --}}
                    {{-- ... --}}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-primary" wire:click="save">Agregar</button>
                    <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Editar --}}
    <div class="modal fade" id="modalEditVehicle" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Vehiculo</h5>
                    <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Placa</label>
                            <input id="plate-edit" type="text" class="form-control" wire:model.defer="plate" placeholder="ABC-123">
                            @error('plate') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Sede</label>
                            <select class="form-select" wire:model.defer="headquarter">
                                <option value="">— Seleccionar —</option>
                                @foreach($listHeadquarters as $hq)
                                    <option value="{{ $hq->name ?? $hq->title ?? $hq->id }}">{{ $hq->name ?? $hq->title ?? ('ID '.$hq->id) }}</option>
                                @endforeach
                            </select>
                            @error('headquarter') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Fecha ingreso</label>
                            <input type="date" class="form-control" wire:model.defer="entry_date">
                            @error('entry_date') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Fecha cese</label>
                            <input type="date" class="form-control" wire:model.defer="termination_date">
                            @error('termination_date') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Categoría</label>
                            <input type="text" class="form-control" wire:model.defer="class" placeholder="(clase)">
                            @error('class') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Marca</label>
                            <input type="text" class="form-control" wire:model.defer="brand" placeholder="Toyota">
                            @error('brand') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Año</label>
                            <input type="number" class="form-control" wire:model.defer="year" min="1950" max="{{ now()->year + 1 }}">
                            @error('year') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Modelo</label>
                            <input type="text" class="form-control" wire:model.defer="model" placeholder="Yaris / Accent">
                            @error('model') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Carrocería</label>
                            <input type="text" class="form-control" wire:model.defer="bodywork" placeholder="Sedán / Hatchback">
                            @error('bodywork') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Color</label>
                            <input type="text" class="form-control" wire:model.defer="color" placeholder="Rojo / Negro">
                            @error('color') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Modalidad</label>
                            <input type="text" class="form-control" wire:model.defer="type" placeholder="(tipo)">
                            @error('type') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Empresa afiliada</label>
                            <input type="text" class="form-control" wire:model.defer="affiliated_company" placeholder="(empresa)">
                            @error('affiliated_company') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Condición</label>
                            <input type="text" class="form-control" wire:model.defer="condition" placeholder="EX / GN / DT / Activo / ...">
                            @error('condition') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Propietario</label>
                            <select class="form-select" wire:model.defer="owner_id">
                                <option value="">— Seleccionar —</option>
                                @foreach($listOwners as $o)
                                    <option value="{{ $o->id }}">{{ $o->name }}</option>
                                @endforeach
                            </select>
                            @error('owner_id') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Conductor</label>
                            <select class="form-select" wire:model.defer="driver_id">
                                <option value="">— Seleccionar —</option>
                                @foreach($listDrivers as $d)
                                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                                @endforeach
                            </select>
                            @error('driver_id') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Combustible</label>
                            <input type="text" class="form-control" wire:model.defer="fuel" placeholder="GAS / D2 / GNV / GLP">
                            @error('fuel') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">SOAT</label>
                            <input type="date" class="form-control" wire:model.defer="soat_date">
                            @error('soat_date') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Revisión técnica</label>
                            <input type="date" class="form-control" wire:model.defer="technical_review">
                            @error('technical_review') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Certificado</label>
                            <input type="date" class="form-control" wire:model.defer="certificate_date">
                            @error('certificate_date') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label">Detalle</label>
                            <textarea class="form-control" rows="2" wire:model.defer="detail" placeholder="Observaciones…"></textarea>
                            @error('detail') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-primary" wire:click="update">Editar</button>
                    <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>
