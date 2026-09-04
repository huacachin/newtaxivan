@push('datepicker_css')
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/i18n/jquery-ui-i18n.min.js"></script>
@endpush

<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">LISTADO GENERAL DE SALIDAS</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i  class="ti ti-door-exit f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Salidas</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Listar</a>
                </li>
            </ul>
        </div>
    </div>

    @if(session('departure_success'))
    <div class="alert alert-success alert-dismissible fade show py-2 mb-2" role="alert">
        <i class="ti ti-circle-check me-1"></i> {{ session('departure_success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
    @endif

    @if(session('departure_error'))
    <div class="alert alert-danger alert-dismissible fade show py-2 mb-2" role="alert">
        <i class="ti ti-alert-circle me-1"></i> {{ session('departure_error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
    @endif

    <div class="row table-section">

        <!-- Tabla principal -->
        <div class="col-xl-12">
            <div class="card">

                <div class="card-body">

                    {{-- ===== Fila 1: radios ===== --}}


                    {{-- ===== Fila 2: search/select + fechas + botón ===== --}}
                    <div class="row g-2 align-items-end mb-2">

                        {{-- Campo dinámico: Alpine maneja el toggle visual sin server roundtrip --}}
                        <div class="col-auto" x-data="{ filterType: @entangle('searchType') }">
                            <div class="row g-2 mb-2">
                                <div class="col-12 f-s-11">
                                    <div class="d-flex flex-wrap align-items-center gap-3">
                                        <span class="small text-muted">Buscar:</span>

                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input mg-e-4"
                                                   type="radio"
                                                   name="rbFilter"
                                                   id="rbPlate"
                                                   value="1"
                                                   x-model="filterType">
                                            <label class="form-check-label" for="rbPlate">Placa</label>
                                        </div>

                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input mg-e-4"
                                                   type="radio"
                                                   name="rbFilter"
                                                   id="rbUser"
                                                   value="2"
                                                   x-model="filterType">
                                            <label class="form-check-label" for="rbUser">Usuario</label>
                                        </div>

                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input mg-e-4"
                                                   type="radio"
                                                   name="rbFilter"
                                                   id="rbHQ"
                                                   value="3"
                                                   x-model="filterType">
                                            <label class="form-check-label" for="rbHQ">Sucursal</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            {{-- Buscador con historial: LRU local por tipo de busqueda + valores del servidor.
                                 wire:model.change hace que elegir del historial (o pulsar Enter) ejecute la busqueda;
                                 sin el modificador, wire:model solo guarda el valor y espera al boton de la lupa. --}}
                            <div class="txt-suggest"
                                 x-show="filterType != 3"
                                 x-data="textSuggest({
                                     storageKey: () => filterType == 2 ? 'departures.search.user' : 'departures.search.plate',
                                     server: () => (filterType == 2 ? $wire.userSuggestions : $wire.plateSuggestions) || [],
                                     max: 8
                                 })">
                                <input type="search"
                                       x-ref="input"
                                       class="form-control form-control-sm"
                                       :placeholder="filterType == 1 ? 'Buscar por placa...' : (filterType == 2 ? 'Buscar por usuario...' : 'Buscar...')"
                                       aria-label="Buscar"
                                       @focus="show()"
                                       @click="show()"
                                       @input="show()"
                                       @keydown="onKey($event)"
                                       @blur="onBlur()"
                                       wire:model.change="searchText">
                                <ul class="txt-suggest__list" x-ref="list" role="listbox"
                                    x-show="open && items.length" x-cloak>
                                    <template x-for="(it, i) in items" :key="it.value">
                                        <li :class="itemClass(it, i)" role="option" :title="it.hint"
                                            @mousedown.prevent @click="pick(it.value)"
                                            @mouseenter="active = i" x-text="it.value"></li>
                                    </template>
                                </ul>
                            </div>
                            <select class="form-control form-control-sm"
                                    aria-label="Selecciona sucursal"
                                    wire:model.change="searchText"
                                    x-show="filterType == 3"
                                    x-cloak>
                                <option value="">Todos</option>
                                @foreach($headquarters as $h)
                                    <option value="{{ $h->id }}">{{ $h->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Fecha Inicio --}}
                        <div class="col-auto">
                            <label class="form-label mb-1">Fecha Inicio</label>
                            <input type="text" id="uiFromDate" class="form-control form-control-sm" wire:model.defer="uiFromDate">
                        </div>

                        {{-- Fecha Fin --}}
                        <div class="col-auto">
                            <label class="form-label mb-1">Fecha Fin</label>
                            <input type="text" id="uiToDate" class="form-control form-control-sm" wire:model.defer="uiToDate">
                        </div>

                        {{-- Botón aplicar (pegado a los inputs) --}}
                        <div class="col-auto d-flex align-items-end">
                            <button class="btn btn-search btn-sm"
                                    wire:click="applyDateRange"

                                    wire:target="applyDateRange">
                                <i class="ti ti-search f-s-12"></i>
                            </button>
                        </div>
                    </div>

                    {{-- ===== Acciones (dejas tu bloque actual) ===== --}}
                    <div class="d-flex flex-wrap gap-2 justify-content-start mb-2">
                        <a class="btn btn-sm btn-success" href="{{ route('departures.add') }}" target="_blank">
                            <i class="ti ti-square-plus f-s-12"></i> Nuevo
                        </a>
                        <a href="{{ route('departures.monthly') }}" class="btn btn-sm btn-primary" target="_blank">
                            <i class="ti ti-report-analytics f-s-12"></i> Mensual
                        </a>
                        <a href="{{ route('departures.rmp') }}" class="btn btn-sm btn-primary" target="_blank">
                            <i class="ti ti-report-analytics f-s-12"></i> RMP V.T
                        </a>
                        <a href="{{ route('departures.stats') }}" class="btn btn-sm btn-primary" target="_blank">
                            <i class="ti ti-report-analytics f-s-12"></i> Estadis.
                        </a>


                        <button
                            class="btn btn-sm {{ $groupMode ? 'btn-success' : 'btn-primary' }}"
                            wire:click="toggleGroup"
                            aria-pressed="{{ $groupMode ? 'true' : 'false' }}"
                            title="{{ $groupMode ? 'Agrupado: ON' : 'Agrupado: OFF' }}"
                        >
                            {{ $groupMode ? 'TS[ON]' : 'TS[OFF]' }}
                        </button>
                        <button class="btn btn-sm btn-export" wire:click="export">
                            <i class="ti ti-file-analytics f-s-12"></i>
                        </button>

                        <button class="btn btn-sm btn-primary" id="down" title="Bajar">
                            <i class="fa-solid fa-angle-down"></i>
                        </button>
                    </div>

                    @if($showSection === 'all' || $showSection === 'principal')
                    @if(!$groupMode)
                        {{-- ════════ MOBILE: cards de salidas (modo detallado) ════════ --}}
                        <div class="d-md-none list-cards">
                            @if($rows->count() > 0)
                                @foreach($rows as $d)
                                    <article class="list-card">
                                        <header class="list-card__head">
                                            <div class="list-card__title-wrap">
                                                <span class="list-card__index">{{ $loop->iteration }}</span>
                                                <span class="list-card__title list-card__title--plate">{{ $d->plate }}</span>
                                            </div>
                                            @if(auth()->user()->hasRole('director') || (auth()->user()->hasAnyRole(['administrador','gerente']) && \Carbon\Carbon::parse($d->date)->isToday()))
                                                <a href="{{ route('departures.edit', $d->id) }}" class="list-card__edit" aria-label="Editar">
                                                    <i class="ti ti-edit"></i>
                                                </a>
                                            @endif
                                        </header>

                                        <div class="list-card__chips">
                                            <span class="list-chip list-chip--info">{{ \Illuminate\Support\Carbon::parse($d->date)->format('d/m/Y') }}</span>
                                            @if($d->hour)<span class="list-chip">{{ $d->hour }}</span>@endif
                                            @if($d->headquarter_name)<span class="list-chip list-chip--muted">{{ $d->headquarter_name }}</span>@endif
                                        </div>

                                        <ul class="list-card__meta">
                                            <li>
                                                <span class="list-card__meta-lbl"><i class="ti ti-cash"></i> Salida (S/)</span>
                                                <span class="list-card__meta-val list-card__meta-val--amount">{{ number_format($d->price ?? 0, 2) }}</span>
                                            </li>
                                            <li>
                                                <span class="list-card__meta-lbl"><i class="ti ti-users"></i> Pasajeros</span>
                                                <span class="list-card__meta-val">{{ (int)($d->passenger ?? 0) }}</span>
                                            </li>
                                            <li>
                                                <span class="list-card__meta-lbl"><i class="ti ti-ticket"></i> Pasaje (S/)</span>
                                                <span class="list-card__meta-val">{{ number_format($d->passage ?? 0, 2) }}</span>
                                            </li>
                                            <li>
                                                <span class="list-card__meta-lbl"><i class="ti ti-user"></i> Usuario</span>
                                                <span class="list-card__meta-val">{{ $d->user_name ?: '—' }}</span>
                                            </li>
                                        </ul>
                                    </article>
                                @endforeach
                            @else
                                <div class="list-cards__empty">Sin salidas</div>
                            @endif
                        </div>
                    @endif

                    <div class="table-responsive {{ !$groupMode ? 'd-none d-md-block' : '' }}">
                        <table class="table table-bordered table-striped table-hover  p-0"
                               wire:key="dep-table-{{ $groupMode ? 'g' : 'd' }}">

                            <thead class="text-center bg-primary" >
                            <tr>
                                @if(!$groupMode)
                                    <th rowspan="2"></th>
                                @endif
                                <th class="text-center " rowspan="2">N°</th>
                                <th class="text-center " rowspan="2">Placa</th>
                                <th class="text-center " rowspan="2">Fecha</th>
                                <th class="text-center " colspan="2">Hora</th>
                                <th class="text-center " rowspan="2">Sucursal</th>
                                <th class="text-center " rowspan="2">Usuario</th>
                                <th class="text-center " colspan="3">Empresa</th>
                                <th class="text-center " colspan="3">Vehiculo</th>
                                    @if(!$groupMode)
                                <th class="text-center " rowspan="2">Map</th>
                                    @endif
                            </tr>
                            <tr>
                                <th class="ta-center ">Sal.</th>
                                <th class="ta-center ">Frec.</th>

                                <th class="ta-center ">Salida</th>
                                <th class="ta-center ">T. S</th>
                                <th class="ta-center ">S/</th>

                                <th class="ta-center ">P.</th>
                                <th class="ta-center ">PJ</th>
                                <th class="ta-center ">S/</th>
                            </tr>
                            </thead>

                            <tbody>
                            @if($rows->count() > 0)
                                @foreach($rows as $d)
                                    <tr>
                                            @if(!$groupMode)
                                                <td class="text-center ">
                                                    @if(auth()->user()->hasRole('director') || (auth()->user()->hasAnyRole(['administrador','gerente']) && \Carbon\Carbon::parse($d->date)->isToday()))
                                                    <a href="{{ route('departures.edit', $d->id) }}"><i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"></i></a>
                                                    @endif
                                                </td>
                                            @endif

                                        {{-- Nº --}}
                                        @if($groupMode)
                                            <td>{{ $d->ordinal }}</td>
                                        @else
                                            <td>{{ $loop->iteration }}</td>
                                        @endif

                                        <td>{{ $d->plate }}</td>
                                        <td>{{ \Illuminate\Support\Carbon::parse($d->date)->format('d/m/Y') }}</td>

                                        {{-- Hora: Salida --}}
                                        @if(!$groupMode)
                                            <td>{{ $d->hour }}</td>
                                        @else
                                            <td>-</td>
                                        @endif

                                        {{-- Hora: Frec. (fix para agrupar) --}}
                                        <td>
                                            @if($groupMode)
                                                -
                                            @else
                                                {{ data_get($d, 'freq', '0:00:00') ?: '0:00:00' }}
                                            @endif
                                        </td>

                                        <td>{{ $d->headquarter_name }}</td>
                                        <td>{{ $d->user_name }}</td>

                                        {{-- Empresa --}}
                                        <td>{{ number_format($groupMode ? ($d->k1 ?? 0) : ($d->times ?? 0)) }}</td>
                                        <td>{{ number_format($groupMode ? ($d->k1 ?? 0) : ($d->times ?? 0)) }}</td>
                                        <td>{{ number_format($groupMode ? ($d->p1 ?? 0) : ($d->price ?? 0), 2) }}</td>

                                        {{-- Vehículo --}}
                                        <td>{{ number_format($groupMode ? ($d->pasajeros ?? 0) : ($d->passenger ?? 0)) }}</td>
                                        <td>{{ number_format($groupMode ? ($d->pasaje ?? 0) : ($d->passage ?? 0), 2) }}</td>
                                        <td>{{ number_format($d->total_pasaje ?? 0, 2) }}</td>

                                        {{-- Map sólo en detalle --}}
                                        @if(!$groupMode)
                                            <td>
                                                @if(!empty($d->latitude) && !empty($d->longitude))
                                                    <a href="https://maps.google.com/?q={{ $d->latitude }},{{ $d->longitude }}"
                                                       target="_blank" class="underline">🌍</a>
                                                @else
                                                    -
                                                @endif
                                            </td>

                                        @endif
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="15" class="text-center ">No se encontrarón resultados</td>
                                </tr>
                            @endif
                            </tbody>

                            <tfoot class="text-center f-w-600 bg-primary">
                            <tr>
                                <td colspan="{{ (!$groupMode) ? (8) : 7 }}">TOTAL</td>
                                <td>{{ number_format($totals->times_total ?? 0) }}</td>
                                <td>{{ number_format($totals->times_total ?? 0) }}</td>
                                <td>{{ number_format($totals->price_total ?? 0, 2) }}</td>
                                <td>{{ number_format($totals->passengers_total ?? 0) }}</td>
                                <td>{{ number_format($totals->passage_total ?? 0, 2) }}</td>
                                <td>{{ number_format($totals->total_pasaje_total ?? 0, 2) }}</td>
                                @if(!$groupMode)
                                <td>-</td>
                                @endif
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                    @endif

                    @if($showSection === 'all' || $showSection === 'apoyo')
                    <div class="section-banner section-banner--support">
                        <span class="section-banner__count">{{ $supportRows->count() ?? 0 }}</span>
                        <div class="section-banner__body">
                            <span class="section-banner__title">Vehículos de apoyo</span>
                            <span class="section-banner__subtitle">Salidas sin vehículo de flota</span>
                        </div>
                        <span class="section-banner__rule"></span>
                    </div>

                    @if(!$groupMode)
                        {{-- ════════ MOBILE: cards de salidas de apoyo (modo detallado) ════════ --}}
                        <div class="d-md-none list-cards mb-3">
                            @if($supportRows->count() > 0)
                                @foreach($supportRows as $d)
                                    <article class="list-card list-card--support">
                                        <header class="list-card__head">
                                            <div class="list-card__title-wrap">
                                                <span class="list-card__index">{{ $loop->iteration }}</span>
                                                <span class="list-card__title list-card__title--plate">{{ $d->plate }}</span>
                                                <span class="list-card__support-tag">
                                                    <i class="ti ti-shield-half-filled"></i> Apoyo
                                                </span>
                                            </div>
                                            @if(auth()->user()->hasRole('director') || (auth()->user()->hasAnyRole(['administrador','gerente']) && \Carbon\Carbon::parse($d->date)->isToday()))
                                                <a href="{{ route('departures.edit', $d->id) }}" class="list-card__edit" aria-label="Editar">
                                                    <i class="ti ti-edit"></i>
                                                </a>
                                            @endif
                                        </header>

                                        <div class="list-card__chips">
                                            <span class="list-chip list-chip--info">{{ \Illuminate\Support\Carbon::parse($d->date)->format('d/m/Y') }}</span>
                                            @if($d->hour)<span class="list-chip">{{ $d->hour }}</span>@endif
                                            @if($d->headquarter_name)<span class="list-chip list-chip--muted">{{ $d->headquarter_name }}</span>@endif
                                        </div>

                                        <ul class="list-card__meta">
                                            <li>
                                                <span class="list-card__meta-lbl"><i class="ti ti-cash"></i> Salida (S/)</span>
                                                <span class="list-card__meta-val list-card__meta-val--amount">{{ number_format($d->price ?? 0, 2) }}</span>
                                            </li>
                                            <li>
                                                <span class="list-card__meta-lbl"><i class="ti ti-users"></i> Pasajeros</span>
                                                <span class="list-card__meta-val">{{ (int)($d->passenger ?? 0) }}</span>
                                            </li>
                                            <li>
                                                <span class="list-card__meta-lbl"><i class="ti ti-ticket"></i> Pasaje (S/)</span>
                                                <span class="list-card__meta-val">{{ number_format($d->passage ?? 0, 2) }}</span>
                                            </li>
                                            <li>
                                                <span class="list-card__meta-lbl"><i class="ti ti-user"></i> Usuario</span>
                                                <span class="list-card__meta-val">{{ $d->user_name ?: '—' }}</span>
                                            </li>
                                        </ul>
                                    </article>
                                @endforeach
                            @else
                                <div class="list-cards__empty">Sin salidas de apoyo</div>
                            @endif
                        </div>
                    @endif

                    <div class="table-responsive mb-3 {{ !$groupMode ? 'd-none d-md-block' : '' }}">
                        <table class=" table table-bordered table-striped   p-0 table-hover">
                            <thead class="text-center bg-primary">
                            <tr>
                                    @if(!$groupMode)
                                        <th rowspan="2"></th>
                                    @endif
                                <th rowspan="2">N°</th>
                                <th rowspan="2">Placa</th>
                                <th rowspan="2">Fecha</th>
                                <th colspan="2">Hora</th>
                                <th rowspan="2">Sucursal</th>
                                <th rowspan="2">Usuario</th>
                                <th colspan="3">Empresa</th>
                                <th colspan="3">Vehiculo</th>
                                    @if(!$groupMode)
                                <th rowspan="2">Map</th>
                                        @endif
                            </tr>
                            <tr>
                                <th>Sal.</th>
                                <th>Frec.</th>

                                <th>Salida</th>
                                <th>T. S</th>
                                <th>S/</th>

                                <th>P.</th>
                                <th>PJ</th>
                                <th>S/</th>
                            </tr>
                            </thead>

                            <tbody wire:key="dep-support-tbody-{{ $groupMode ? 'g' : 'd' }}">
                            @forelse($supportRows as $d)
                                <tr class="text-center ">
                                        @if(!$groupMode)
                                            <td class="text-center title-modules">
                                                @if(auth()->user()->hasRole('director') || (auth()->user()->hasAnyRole(['administrador','gerente']) && \Carbon\Carbon::parse($d->date)->isToday()))
                                                <a href="{{ route('departures.edit', $d->id) }}"><i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"></i></a>
                                                @endif
                                            </td>
                                        @endif

                                    {{-- Nº --}}
                                    <td class="title-modules">@if($groupMode) {{ $d->ordinal }} @else {{ $loop->iteration }} @endif</td>

                                    <td class="title-modules">{{ $d->plate }}</td>
                                    <td class="title-modules">{{ \Illuminate\Support\Carbon::parse($d->date)->format('d/m/Y') }}</td>

                                    {{-- Hora: Salida --}}
                                    @if(!$groupMode)
                                        <td class="title-modules">{{ $d->hour }}</td>
                                    @else
                                        <td class="title-modules">-</td>
                                    @endif

                                    {{-- Hora: Frec. (fix para agrupar) --}}
                                    <td class="title-modules">
                                        @if($groupMode)
                                            -
                                        @else
                                            {{ data_get($d, 'freq', '0:00:00') ?: '0:00:00' }}
                                        @endif
                                    </td>

                                    <td class="p-2 title-modules" >{{ $d->headquarter_name ?? '-' }}</td>
                                    <td class="p-2 title-modules" >{{ $d->user_name ?? '-' }}</td>

                                    {{-- Empresa --}}
                                    <td class="p-2 title-modules" >{{ number_format($groupMode ? ($d->k1 ?? 0) : ($d->times ?? 0)) }}</td>
                                    <td class="p-2 title-modules" >{{ number_format($groupMode ? ($d->k1 ?? 0) : ($d->times ?? 0)) }}</td>
                                    <td class="p-2 title-modules" >{{ number_format($groupMode ? ($d->p1 ?? 0) : ($d->price ?? 0), 2) }}</td>

                                    {{-- Vehículo --}}
                                    <td class="p-2 title-modules" >{{ number_format($groupMode ? ($d->pasajeros ?? 0) : ($d->passenger ?? 0)) }}</td>
                                    <td class="p-2 title-modules" >{{ number_format($groupMode ? ($d->pasaje ?? 0) : ($d->passage ?? 0), 2) }}</td>
                                    <td class="p-2 title-modules" >{{ number_format($d->total_pasaje ?? 0, 2) }}</td>

                                    {{-- Map solo en detalle --}}
                                    @if(!$groupMode)
                                        <td class="title-modules">
                                            @if(!empty($d->latitude) && !empty($d->longitude))
                                                <a href="https://maps.google.com/?q={{ $d->latitude }},{{ $d->longitude }}" target="_blank">🌍</a>
                                            @else
                                                -
                                            @endif
                                        </td>

                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td  colspan="{{ 15 }}">No se encontraron resultados</td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="text-center f-w-600  bg-primary"
                                   wire:key="dep-support-tfoot-{{ $groupMode ? 'g' : 'd' }}">
                            <tr>
                                <td class="text-center" colspan="{{ (!$groupMode) ? (8) : 7 }}">TOTAL</td>
                                <td>{{ number_format((float) data_get($supportTotals, 'times_total', 0)) }}</td>
                                <td>{{ number_format((float) data_get($supportTotals, 'times_total', 0)) }}</td>
                                <td>{{ number_format((float) data_get($supportTotals, 'price_total', 0), 2) }}</td>
                                <td>{{ number_format((float) data_get($supportTotals, 'passengers_total', 0)) }}</td>
                                <td>{{ number_format((float) data_get($supportTotals, 'passage_total', 0), 2) }}</td>
                                <td>{{ number_format((float) data_get($supportTotals, 'total_pasaje_total', 0), 2) }}</td>
                                @if(!$groupMode)<td>-</td>@endif
                            </tr>
                            <tr>
                                <td class="text-center" colspan="{{ (!$groupMode) ? (8) : 7 }}">TOTAL GENERAL</td>
                                {{-- Empresa --}}
                                <td>{{ number_format($grandTotals->times_total ?? 0) }}</td>
                                <td>{{ number_format($grandTotals->times_total ?? 0) }}</td>
                                <td>{{ number_format($grandTotals->price_total ?? 0, 2) }}</td>
                                {{-- Vehículo --}}
                                <td>{{ number_format($grandTotals->passengers_total ?? 0) }}</td>
                                <td>{{ number_format($grandTotals->passage_total ?? 0, 2) }}</td>
                                <td>{{ number_format($grandTotals->total_pasaje_total ?? 0, 2) }}</td>
                                @if(!$groupMode)<td>-</td>@endif
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                    @endif

                </div>

            </div>
        </div>



    </div>

    {{-- Overlay de carga scopeado (ya no targetea fromDate/toDate) --}}
</div>

@push('datepicker_js')
    <script>
        $( function() {
            var wire = @this;
            initLivewireDatepicker([
                ['#uiFromDate', 'uiFromDate'],
                ['#uiToDate',   'uiToDate'],
            ], wire);
        });
    </script>
@endpush
