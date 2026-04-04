<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">PROPIETARIOS</h4>
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
                    <a href="#" class="f-s-14">Propietarios</a>
                </li>
            </ul>
        </div>
    </div>

    @if(session('owner_success'))
    <div class="alert alert-success alert-dismissible fade show py-2 mb-2" role="alert">
        <i class="ti ti-circle-check me-1"></i> {{ session('owner_success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
    @endif

    @if(session('owner_error'))
    <div class="alert alert-danger alert-dismissible fade show py-2 mb-2" role="alert">
        <i class="ti ti-alert-circle me-1"></i> {{ session('owner_error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
    @endif

    <div class="row table-section">
        <!-- Tabla principal: Propietarios -->
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">

                        <h5 class="mb-1">Total de propietarios: <span class="title-modules">{{ $owners->count() + $ownersFree->count() }}</span></h5>
                        <p class="mb-0">
                            <strong>Propietarios:</strong> <strong style="color:red">{{ $owners->count() }}</strong> ·
                            <strong>Libres:</strong> <strong style="color:red">{{ $ownersFree->count() }}</strong>
                        </p>

                    <div class="row my-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-2 overflow-auto py-1">
                                <!-- Filtro -->
                                <div class="flex-shrink-0" style="min-width: 160px;">
                                    <select class="form-select form-select-sm"
                                            aria-label="Selecciona item a filtrar"
                                            wire:model="filter">
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
                                           wire:model="search">
                                </div>

                                <button class="btn btn-sm btn-dark flex-shrink-0" wire:click="$refresh">
                                    <i class="ti ti-search f-s-12"></i>
                                </button>

                                <!-- Botones -->
                                @hasanyrole('director|gerente|administrador')
                                <a class="btn btn-sm btn-primary flex-shrink-0"
                                   href="{{ route('settings.owners.create') }}" target="_blank">
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
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                @hasanyrole('director|gerente|administrador')<th scope="col"></th>@endhasanyrole
                                <th>Nº</th>
                                <th>Cod</th>
                                <th scope="col">Placa</th>
                                <th scope="col">Nombre / Empresa</th>
                                <th scope="col">Dni / Ruc</th>
                                <th scope="col">Cel.</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if($owners->count() > 0)
                                @foreach ($owners as $owner)
                                    <tr>
                                        @hasanyrole('director|gerente|administrador')
                                        <td>
                                            <a href="{{ route('settings.owners.edit', $owner->id) }}">
                                                <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"></i>
                                            </a>
                                        </td>
                                        @endhasanyrole
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{$owner->sort_order}}</td>
                                        <td>{{ $owner->plate }}</td>
                                        <td>{{ $owner->name }}</td>
                                        <td>
                                            {{ $owner->document_number }}

                                            @if(!empty($owner->document_expiration_date))
                                                @php
                                                    $exp   = \Carbon\Carbon::parse($owner->document_expiration_date)->startOfDay();
                                                    $today = now()->startOfDay();
                                                    $diff  = $today->diffInDays($exp, false); // puede ser negativo si ya venció
                                                @endphp

                                                @if($diff <= 0)
                                                    {{-- Vencido: fecha igual o menor a hoy --}}
                                                    <span class="badge bg-danger ms-1">
                Vencido
            </span>
                                                @elseif($diff > 0 && $diff <= 10)
                                                    {{-- Por vencer: faltan 1 a 10 días --}}
                                                    <span class="badge bg-warning text-dark ms-1">
                Por vencer
            </span>
                                                @endif
                                            @endif
                                        </td>
                                        <td>{{ $owner->phone }}</td>

                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="{{ auth()->user()->hasAnyRole('director','gerente','administrador') ? 7 : 6 }}">No se encontrarón resultados</td>
                                </tr>
                            @endif
                            </tbody>
                            <tfoot>
                            <tr>
                                <td colspan="{{ auth()->user()->hasAnyRole('director','gerente','administrador') ? 7 : 6 }}">Propietarios: {{ $owners->count() }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                    <h5 class="mb-2 title-modules text-center">Propietarios Libres: {{ $ownersFree->count() }}</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                @hasanyrole('director|gerente|administrador')<th scope="col"></th>@endhasanyrole
                                <th scope="col">Nº</th>
                                <th scope="col">Cod</th>
                                <th scope="col">Placa</th>
                                <th scope="col">Nombre/Empresa</th>
                                <th scope="col">DNI/RUC</th>
                                <th scope="col">Cel.</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($ownersFree as $owner)
                                <tr>
                                    @hasanyrole('director|gerente|administrador')
                                    <td width="50">
                                        <a href="{{ route('settings.owners.edit', $owner->id) }}">
                                            <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"></i>
                                        </a>
                                    </td>
                                        @endhasanyrole
                                    <td>{{ $loop->iteration }}</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>{{ $owner->name }}</td>
                                    <td>
                                        {{ $owner->document_number }}

                                        @if(!empty($owner->document_expiration_date))
                                            @php
                                                $exp   = \Carbon\Carbon::parse($owner->document_expiration_date)->startOfDay();
                                                $today = now()->startOfDay();
                                                $diff  = $today->diffInDays($exp, false);
                                            @endphp

                                            @if($diff <= 0)
                                                <span class="badge bg-danger ms-1">
                Vencido
            </span>
                                            @elseif($diff > 0 && $diff <= 10)
                                                <span class="badge bg-warning text-dark ms-1">
                Por vencer
            </span>
                                            @endif
                                        @endif
                                    </td>

                                    <td>{{ $owner->phone }}</td>

                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ auth()->user()->hasAnyRole('director','gerente','administrador') ? 7 : 6 }}">No se encontrarón resultados</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot>
                            <tr>
                                <td colspan="{{ auth()->user()->hasAnyRole('director','gerente','administrador') ? 7 : 6 }}">Libres: {{ $ownersFree->count() }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
