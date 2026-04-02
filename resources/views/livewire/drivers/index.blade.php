<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">LISTADO GENERAL DE CONDUCTORES ({{ $drivers->count() + $driversFree->count() }})</h4>
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

    @if(session('driver_success'))
    <div class="alert alert-success alert-dismissible fade show py-2 mb-2" role="alert">
        <i class="ti ti-circle-check me-1"></i> {{ session('driver_success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
    @endif

    @if(session('driver_error'))
    <div class="alert alert-danger alert-dismissible fade show py-2 mb-2" role="alert">
        <i class="ti ti-alert-circle me-1"></i> {{ session('driver_error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
    @endif

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
                                   href="{{ route('settings.drivers.create') }}" target="_blank">
                                    <i class="ti ti-square-plus f-s-12"></i> Nuevo
                                </a>
                                @endhasanyrole

                                <button class="btn btn-sm btn-primary flex-shrink-0"
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
                                @hasanyrole('director|gerente|administrador')<th></th>@endhasanyrole
                                <th>Nº</th>
                                <th>Cod</th>
                                <th>Placa</th>
                                <th>Nombre</th>
                                <th>DNI</th>
                                <th>I.Contrato</th>
                                <th>F.Contrato</th>
                                <th>Celular</th>
                                <th>Estado</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if($drivers->count() > 0)
                                @foreach($drivers as $driver)
                                    <tr>
                                        @hasanyrole('director|gerente|administrador')
                                        <td width="50">
                                            <a href="{{ route('settings.drivers.edit', $driver->id) }}">
                                                <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"></i>
                                            </a>
                                        </td>
                                        @endhasanyrole
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{$driver->vehicles->first()->sort_order}}</td>
                                        <td>{{ $driver->vehicles->first()->plate ?? '—' }}</td>
                                        <td>
                                            {{ $driver->name }}

                                            @php
                                                $flag = null;
                                                $expRaw = $driver->document_expiration_date ?? null;

                                                if ($expRaw && $expRaw !== '0000-00-00') {
                                                    $expDate = \Illuminate\Support\Carbon::parse($expRaw);
                                                    $today   = \Illuminate\Support\Carbon::today();

                                                    // diffInDays con $absolute = false para saber si está en pasado/futuro
                                                    $daysDiff = $today->diffInDays($expDate, false);

                                                    if ($daysDiff < 0) {
                                                        // ya pasó la fecha
                                                        $flag = 'expired';
                                                    } elseif ($daysDiff <= 10) {
                                                        // faltan 10 días o menos
                                                        $flag = 'soon';
                                                    }
                                                }
                                            @endphp

                                            @if($flag === 'soon')
                                                <span class="badge bg-warning text-dark ms-1">Por vencer</span>
                                            @elseif($flag === 'expired')
                                                <span class="badge bg-danger ms-1">Vencido</span>
                                            @endif
                                        </td>
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

                                    </tr>
                                @endforeach
                            @else
                                <tr><td colspan="{{ auth()->user()->hasAnyRole('director','gerente','administrador') ? 10 : 9 }}">No se encontrarón resultados</td></tr>
                            @endif
                            </tbody>
                            <tfoot class="bg-primary">
                            <tr>
                                <td colspan="{{ auth()->user()->hasAnyRole('director','gerente','administrador') ? 10 : 9 }}" class="text-end f-w-600">TOTAL: {{ $drivers->count() }}</td>
                            </tr>
                            </tfoot>
                        </table>

                        <h5 class="mb-2 title-modules text-center">Conductores Libres: {{ $driversFree->count() }}</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover">
                                <thead class="bg-primary">
                                <tr>
                                    @hasanyrole('director|gerente|administrador')<th></th>@endhasanyrole
                                    <th>Id</th>
                                    <th>Nombre</th>
                                    <th>DNI</th>
                                    <th>I.Contrato</th>
                                    <th>F.Contrato</th>
                                    <th>Celular</th>
                                    <th>Estado</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($driversFree as $driver)
                                    <tr>
                                        @hasanyrole('director|gerente|administrador')
                                        <td width="50">
                                            <a href="{{ route('settings.drivers.edit', $driver->id) }}">
                                                <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"></i>
                                            </a>
                                        </td>
                                        @endhasanyrole
                                        <td>{{ $loop->iteration }}</td>
                                        <td>
                                            {{ $driver->name }}

                                            @php
                                                $flag = null;
                                                $expRaw = $driver->document_expiration_date ?? null;

                                                if ($expRaw && $expRaw !== '0000-00-00') {
                                                    $expDate = \Illuminate\Support\Carbon::parse($expRaw);
                                                    $today   = \Illuminate\Support\Carbon::today();

                                                    // diffInDays con $absolute = false para saber si está en pasado/futuro
                                                    $daysDiff = $today->diffInDays($expDate, false);

                                                    if ($daysDiff < 0) {
                                                        // ya pasó la fecha
                                                        $flag = 'expired';
                                                    } elseif ($daysDiff <= 10) {
                                                        // faltan 10 días o menos
                                                        $flag = 'soon';
                                                    }
                                                }
                                            @endphp

                                            @if($flag === 'soon')
                                                <span class="badge bg-warning text-dark ms-1">Por vencer</span>
                                            @elseif($flag === 'expired')
                                                <span class="badge bg-danger ms-1">Vencido</span>
                                            @endif
                                        </td>
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

                                    </tr>
                                @empty
                                    <tr><td colspan="{{ auth()->user()->hasAnyRole('director','gerente','administrador') ? 8 : 7 }}">No se encontrarón resultados</td></tr>
                                @endforelse
                                </tbody>
                                <tfoot class="bg-primary">
                                <tr>
                                    <td colspan="{{ auth()->user()->hasAnyRole('director','gerente','administrador') ? 8 : 7 }}" class="text-end f-w-600">TOTAL: {{ $driversFree->count() }}</td>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
