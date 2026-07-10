{{-- resources/views/livewire/vehicles/index.blade.php --}}
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

    @if(session('vehicle_success'))
    <div class="alert alert-success alert-dismissible fade show py-2 mb-2" role="alert">
        <i class="ti ti-circle-check me-1"></i> {{ session('vehicle_success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
    @endif

    @if(session('vehicle_error'))
    <div class="alert alert-danger alert-dismissible fade show py-2 mb-2" role="alert">
        <i class="ti ti-alert-circle me-1"></i> {{ session('vehicle_error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
    @endif

    <div class="row table-section">

        <!-- Tabla -->
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">

                        <h5 class="mb-1">Total vehículos: <span class="title-modules">{{ $vehicles->count() }}</span></h5>
                        <p class="mb-0">
                            <strong>D2:</strong> <strong style="color:red">{{ $vehicles->where('fuel','D2')->count() }}</strong> ·
                            <strong>Gas:</strong> <strong style="color:red">{{ $vehicles->where('fuel','GAS')->count() }}</strong> ·
                            <strong>V.T:</strong> <strong style="color:red">{{ $vehicles->whereIn('fuel', ['GAS','D2'])->count() }}</strong> ·
                            <strong>V.Q.N.T:</strong> <strong style="color:red">{{ $vehicles->whereNotIn('fuel', ['GAS','D2'])->count() }}</strong> ·
                            <strong>Propietario:</strong> <strong style="color:red">{{ $owners }}</strong> ·
                            <strong>Conductor:</strong> <strong style="color:red">{{ $drivers }}</strong>
                        </p>
                        <div class="row my-2">
                            <div class="col-12">
                                <!-- Una sola fila: no-wrap + scroll horizontal -->
                                <div class="d-flex flex-wrap align-items-end gap-2 overflow-auto py-1">



                                    <!-- Estado -->
                                    <div class="flex-shrink-0" style="min-width: 160px;">
                                        <select class="form-select form-select-sm"
                                                aria-label="Estado del vehiculo"
                                                wire:model="status">
                                            <option value="active">Activo</option>
                                            <option value="inactive">Cesado</option>
                                        </select>
                                    </div>

                                    <!-- Filtro -->
                                    <div class="flex-shrink-0" style="min-width: 180px;">
                                        <select class="form-select form-select-sm"
                                                aria-label="Filtro"
                                                wire:model="filter">
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
                                               wire:model="search">
                                    </div>

                                    <button class="btn btn-sm btn-dark flex-shrink-0" wire:click="$refresh">
                                        <i class="ti ti-search f-s-12"></i>
                                    </button>

                                    <!-- Botones -->
                                    @hasanyrole('director|gerente|administrador')
                                    <a class="btn btn-sm btn-primary flex-shrink-0"
                                       href="{{ route('settings.vehicles.create') }}" target="_blank">
                                        <i class="ti ti-square-plus f-s-12"></i> Nuevo
                                    </a>
                                    @endhasanyrole



                                    <button class="btn btn-sm btn-export flex-shrink-0"
                                            wire:click="export">
                                        <i class="ti ti-file-analytics f-s-12"></i> Exportar
                                    </button>

                                    <button id="down"
                                            class="btn btn-sm btn-primary flex-shrink-0">
                                        <i class="fa-solid fa-angle-down"></i>
                                    </button>



                                </div>
                            </div>
                        </div>

                    {{-- ════════ MOBILE: lista de cards (oculta en md+) ════════ --}}
                    <div class="d-md-none list-cards">
                        @if($vehicles->count())
                            @foreach($vehicles as $vehicle)
                                @php
                                    $cond = strtoupper((string)($vehicle->condition ?? ''));
                                    $condCardClass = 'vh-cond ';
                                    if (str_starts_with($cond, 'EX')) { $condCardClass .= 'vh-cond--ex'; }
                                    elseif ($cond === 'GN') { $condCardClass .= 'vh-cond--gn'; }
                                    elseif ($cond === 'DT') { $condCardClass .= 'vh-cond--dt'; }
                                @endphp
                                <article class="list-card">
                                    <header class="list-card__head">
                                        <div class="list-card__title-wrap">
                                            <span class="list-card__index">{{ $loop->iteration }}</span>
                                            <span class="list-card__title list-card__title--plate">{{ $vehicle->plate }}</span>
                                            @if(!empty($vehicle->badges))
                                                <span class="list-card__badges">
                                                    @foreach($vehicle->badges as $b)
                                                        @if(!empty($b['html']))
                                                            <span class="badge {{ $b['class'] }} {{ $b['text'] ?? 'text-white' }}" data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="{{ $b['html'] }}">
                                                                {{ $b['abbr'] }}
                                                            </span>
                                                        @else
                                                            <span class="badge {{ $b['class'] }} {{ $b['text'] ?? 'text-white' }}" title="{{ $b['title'] }}">
                                                                {{ $b['abbr'] }}
                                                            </span>
                                                        @endif
                                                    @endforeach
                                                </span>
                                            @endif
                                        </div>
                                        @hasanyrole('director|gerente|administrador|controlador')
                                            <a href="{{ route('settings.vehicles.edit', $vehicle->id) }}" class="list-card__edit" aria-label="Editar">
                                                <i class="ti ti-edit"></i>
                                            </a>
                                        @endhasanyrole
                                    </header>

                                    <div class="list-card__subtitle">
                                        {{ $vehicle->brand ?: '—' }}
                                        @if($vehicle->year) · {{ $vehicle->year }} @endif
                                    </div>

                                    <div class="list-card__chips">
                                        @if($cond)<span class="{{ $condCardClass }}">{{ $cond }}</span>@endif
                                        @if($vehicle->fuel)<span class="list-chip">{{ $vehicle->fuel }}</span>@endif
                                        @if($vehicle->class)<span class="list-chip">{{ $vehicle->class }}</span>@endif
                                        @if($vehicle->type)<span class="list-chip list-chip--muted">{{ $vehicle->type }}</span>@endif
                                        @if($vehicle->images && $vehicle->images->count())
                                            @php $imgUrls = $vehicle->images->map(fn($i) => asset('storage/'.$i->image_path))->values()->all(); @endphp
                                            <button type="button"
                                                    class="list-chip list-chip--photos"
                                                    x-data
                                                    x-on:click="$dispatch('open-lightbox', { images: {{ json_encode($imgUrls) }}, index: 0 })"
                                                    title="Ver fotos">
                                                <i class="ti ti-photo"></i> Ver fotos ({{ count($imgUrls) }})
                                            </button>
                                        @endif
                                    </div>

                                    <ul class="list-card__meta">
                                        <li>
                                            <span class="list-card__meta-lbl"><i class="ti ti-user"></i> Propietario</span>
                                            <span class="list-card__meta-val">{{ $vehicle->owner->name ?? '—' }}</span>
                                        </li>
                                        <li>
                                            <span class="list-card__meta-lbl"><i class="ti ti-steering-wheel"></i> Conductor</span>
                                            <span class="list-card__meta-val">{{ $vehicle->driver->name ?? '—' }}</span>
                                        </li>
                                        @if($vehicle->affiliated_company)
                                            <li>
                                                <span class="list-card__meta-lbl"><i class="ti ti-building"></i> Empresa</span>
                                                <span class="list-card__meta-val">{{ $vehicle->affiliated_company }}</span>
                                            </li>
                                        @endif
                                        @if($status === 'inactive' && $vehicle->termination_date)
                                            <li>
                                                <span class="list-card__meta-lbl"><i class="ti ti-calendar-off"></i> Fecha cese</span>
                                                <span class="list-card__meta-val">{{ $vehicle->termination_date->format('d/m/Y') }}</span>
                                            </li>
                                        @endif
                                    </ul>
                                </article>
                            @endforeach
                        @else
                            <div class="list-cards__empty">Sin resultados</div>
                        @endif
                    </div>

                    {{-- ════════ DESKTOP: tabla original (oculta en mobile) ════════ --}}
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                @hasanyrole('director|gerente|administrador|controlador')
                                <th class="sticky-col"></th>
                                @endhasanyrole
                                <th>Nº</th>
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
                                @endhasanyrole
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
                                        @hasanyrole('director|gerente|administrador|controlador')
                                        <td>
                                            <a href="{{ route('settings.vehicles.edit', $vehicle->id) }}">
                                                <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"></i>
                                            </a>
                                        </td>
                                        @endhasanyrole

                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $vehicle->sort_order }}</td>

                                        <td>
                                            {{ $vehicle->plate }}
                                            @if(!empty($vehicle->badges))
                                                <span class="ms-2 d-inline-flex gap-1 align-items-center">
                                                    @foreach($vehicle->badges as $b)
                                                        @if(!empty($b['html']))
                                                            <span class="badge {{ $b['class'] }} {{ $b['text'] ?? 'text-white' }}" data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="{{ $b['html'] }}">
                                                                {{ $b['abbr'] }}
                                                            </span>
                                                        @else
                                                            <span class="badge {{ $b['class'] }} {{ $b['text'] ?? 'text-white' }}" title="{{ $b['title'] }}">
                                                                {{ $b['abbr'] }}
                                                            </span>
                                                        @endif
                                                    @endforeach
                                                </span>
                                            @endhasanyrole
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
                                        @endhasanyrole
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    @php $colspan = 12 + ($status === "inactive" ? 1 : 0) + (auth()->user()->hasAnyRole('director','gerente','administrador','controlador') ? 1 : 0); @endphp
                                    <td colspan="{{ $colspan }}">No se encontrarón resultados</td>
                                </tr>
                            @endhasanyrole
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

    @include('partials.image-lightbox')
</div>

@once
    @push('styles')
    <style>
        .list-chip--photos {
            background: #e3f2fd;
            color: #0d47a1;
            border: 1px solid rgba(13,71,161,.2);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            transition: background .15s, border-color .15s;
        }
        .list-chip--photos:hover { background: #bbdefb; border-color: rgba(13,71,161,.4); }
        .list-chip--photos i { font-size: 14px; }
    </style>
    @endpush
@endonce
