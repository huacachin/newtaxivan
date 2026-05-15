<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">CONDUCTORES</h4>
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

                        <h5 class="mb-1">Total de conductores: <span class="title-modules">{{ $drivers->count() + $driversFree->count() }}</span></h5>
                        <p class="mb-0">
                            <strong>Conductores:</strong> <strong style="color:red">{{ $drivers->count() }}</strong> ·
                            <strong>Libres:</strong> <strong style="color:red">{{ $driversFree->count() }}</strong>
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
                                   href="{{ route('settings.drivers.create') }}" target="_blank">
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

                    {{-- ════════ MOBILE: cards de conductores con vehículo ════════ --}}
                    <div class="d-md-none list-cards">
                        @if($drivers->count() > 0)
                            @foreach($drivers as $driver)
                                @php
                                    $expBadge = null;
                                    $expRaw = $driver->document_expiration_date ?? null;
                                    if ($expRaw && $expRaw !== '0000-00-00') {
                                        $expDate = \Illuminate\Support\Carbon::parse($expRaw);
                                        $today   = \Illuminate\Support\Carbon::today();
                                        $daysDiff = $today->diffInDays($expDate, false);
                                        if ($daysDiff < 0) { $expBadge = ['cls' => 'bg-danger', 'txt' => 'Vencido']; }
                                        elseif ($daysDiff <= 10) { $expBadge = ['cls' => 'bg-warning text-dark', 'txt' => 'Por vencer']; }
                                    }
                                    $vehiclePlate = $driver->vehicles->first()->plate ?? null;
                                @endphp
                                <article class="list-card">
                                    <header class="list-card__head">
                                        <div class="list-card__title-wrap">
                                            <span class="list-card__index">{{ $loop->iteration }}</span>
                                            <span class="list-card__title">{{ $driver->name }}</span>
                                        </div>
                                        @hasanyrole('director|gerente|administrador|controlador')
                                            <a href="{{ route('settings.drivers.edit', $driver->id) }}" class="list-card__edit" aria-label="Editar">
                                                <i class="ti ti-edit"></i>
                                            </a>
                                        @endhasanyrole
                                    </header>

                                    <div class="list-card__chips">
                                        @if($vehiclePlate)<span class="list-chip list-chip--info">{{ $vehiclePlate }}</span>@endif
                                        @if($driver->condition)<span class="list-chip">{{ $driver->condition }}</span>@endif
                                        @if($expBadge)<span class="badge {{ $expBadge['cls'] }}">{{ $expBadge['txt'] }}</span>@endif
                                    </div>

                                    <ul class="list-card__meta">
                                        <li>
                                            <span class="list-card__meta-lbl"><i class="ti ti-id"></i> DNI</span>
                                            <span class="list-card__meta-val">{{ $driver->document_number ?: '—' }}</span>
                                        </li>
                                        @if($driver->phone)
                                            <li>
                                                <span class="list-card__meta-lbl"><i class="ti ti-phone"></i> Celular</span>
                                                <span class="list-card__meta-val">{{ $driver->phone }}</span>
                                            </li>
                                        @endif
                                        @if($driver->contract_start && $driver->contract_start !== '0000-00-00')
                                            <li>
                                                <span class="list-card__meta-lbl"><i class="ti ti-calendar"></i> Inicio</span>
                                                <span class="list-card__meta-val">{{ \Illuminate\Support\Carbon::parse($driver->contract_start)->format('d/m/Y') }}</span>
                                            </li>
                                        @endif
                                    </ul>
                                </article>
                            @endforeach
                        @else
                            <div class="list-cards__empty">Sin conductores</div>
                        @endif
                    </div>

                    {{-- ════════ DESKTOP: tabla original ════════ --}}
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                @hasanyrole('director|gerente|administrador|controlador')<th></th>@endhasanyrole
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
                                        @hasanyrole('director|gerente|administrador|controlador')
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
                                <tr><td colspan="{{ auth()->user()->hasAnyRole('director','gerente','administrador','controlador') ? 10 : 9 }}">No se encontrarón resultados</td></tr>
                            @endif
                            </tbody>
                            <tfoot class="bg-primary">
                            <tr>
                                <td colspan="{{ auth()->user()->hasAnyRole('director','gerente','administrador','controlador') ? 10 : 9 }}" class="text-end f-w-600">TOTAL: {{ $drivers->count() }}</td>
                            </tr>
                            </tfoot>
                        </table>

                        <h5 class="mb-2 title-modules text-center">Conductores Libres: {{ $driversFree->count() }}</h5>

                        {{-- ════════ MOBILE: cards de conductores libres ════════ --}}
                        <div class="d-md-none list-cards">
                            @forelse($driversFree as $driver)
                                @php
                                    $expBadge = null;
                                    $expRaw = $driver->document_expiration_date ?? null;
                                    if ($expRaw && $expRaw !== '0000-00-00') {
                                        $expDate = \Illuminate\Support\Carbon::parse($expRaw);
                                        $today   = \Illuminate\Support\Carbon::today();
                                        $daysDiff = $today->diffInDays($expDate, false);
                                        if ($daysDiff < 0) { $expBadge = ['cls' => 'bg-danger', 'txt' => 'Vencido']; }
                                        elseif ($daysDiff <= 10) { $expBadge = ['cls' => 'bg-warning text-dark', 'txt' => 'Por vencer']; }
                                    }
                                @endphp
                                <article class="list-card list-card--support">
                                    <header class="list-card__head">
                                        <div class="list-card__title-wrap">
                                            <span class="list-card__index">{{ $loop->iteration }}</span>
                                            <span class="list-card__title">{{ $driver->name }}</span>
                                            <span class="list-card__support-tag">
                                                <i class="ti ti-user-question"></i> Libre
                                            </span>
                                        </div>
                                        @hasanyrole('director|gerente|administrador|controlador')
                                            <a href="{{ route('settings.drivers.edit', $driver->id) }}" class="list-card__edit" aria-label="Editar">
                                                <i class="ti ti-edit"></i>
                                            </a>
                                        @endhasanyrole
                                    </header>

                                    <div class="list-card__chips">
                                        @if($driver->condition)<span class="list-chip">{{ $driver->condition }}</span>@endif
                                        @if($expBadge)<span class="badge {{ $expBadge['cls'] }}">{{ $expBadge['txt'] }}</span>@endif
                                    </div>

                                    <ul class="list-card__meta">
                                        <li>
                                            <span class="list-card__meta-lbl"><i class="ti ti-id"></i> DNI</span>
                                            <span class="list-card__meta-val">{{ $driver->document_number ?: '—' }}</span>
                                        </li>
                                        @if($driver->phone)
                                            <li>
                                                <span class="list-card__meta-lbl"><i class="ti ti-phone"></i> Celular</span>
                                                <span class="list-card__meta-val">{{ $driver->phone }}</span>
                                            </li>
                                        @endif
                                    </ul>
                                </article>
                            @empty
                                <div class="list-cards__empty">Sin conductores libres</div>
                            @endforelse
                        </div>

                        {{-- ════════ DESKTOP: tabla original ════════ --}}
                        <div class="table-responsive d-none d-md-block">
                            <table class="table table-bordered table-striped table-hover">
                                <thead class="bg-primary">
                                <tr>
                                    @hasanyrole('director|gerente|administrador|controlador')<th></th>@endhasanyrole
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
                                @forelse($driversFree as $driver)
                                    <tr>
                                        @hasanyrole('director|gerente|administrador|controlador')
                                        <td width="50">
                                            <a href="{{ route('settings.drivers.edit', $driver->id) }}">
                                                <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"></i>
                                            </a>
                                        </td>
                                        @endhasanyrole
                                        <td>{{ $loop->iteration }}</td>
                                        <td>-</td>
                                        <td>-</td>
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
                                    <tr><td colspan="{{ auth()->user()->hasAnyRole('director','gerente','administrador','controlador') ? 10 : 9 }}">No se encontrarón resultados</td></tr>
                                @endforelse
                                </tbody>
                                <tfoot class="bg-primary">
                                <tr>
                                    <td colspan="{{ auth()->user()->hasAnyRole('director','gerente','administrador','controlador') ? 10 : 9 }}" class="text-end f-w-600">TOTAL: {{ $driversFree->count() }}</td>
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
