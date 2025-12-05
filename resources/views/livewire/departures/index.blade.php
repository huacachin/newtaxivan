@push('datepicker_css')
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/i18n/jquery-ui-i18n.min.js"></script>
@endpush
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

        #uiFromDate, #uiToDate {
            background: url({{asset('images/calen.png')}}) #fff no-repeat right;
            background-size: 21px 16px;
            padding-right: 2rem;
        }
    </style>
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

    <div class="row table-section">

        <!-- Tabla principal -->
        <div class="col-xl-12">
            <div class="card">

                <div class="card-body">

                    {{-- ===== Fila 1: radios ===== --}}


                    {{-- ===== Fila 2: search/select + fechas + botón ===== --}}
                    <div class="row g-2 align-items-end mb-2">

                        {{-- Campo dinámico: input o select (forzamos remount con wire:key) --}}
                        <div class="col-auto" wire:key="search-field-{{ (int) $searchType }}">
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
                                                   wire:model.live="searchType">  {{-- sin .live ni wire:click --}}
                                            <label class="form-check-label" for="rbPlate">Placa</label>
                                        </div>

                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input mg-e-4"
                                                   type="radio"
                                                   name="rbFilter"
                                                   id="rbUser"
                                                   value="2"
                                                   wire:model.live="searchType">
                                            <label class="form-check-label" for="rbUser">Usuario</label>
                                        </div>

                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input mg-e-4"
                                                   type="radio"
                                                   name="rbFilter"
                                                   id="rbHQ"
                                                   value="3"
                                                   wire:model.live="searchType">
                                            <label class="form-check-label" for="rbHQ">Sucursal</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @if((int) $searchType !== 3)
                                <input type="search"
                                       class="form-control form-control-sm"
                                       placeholder="@if($searchType==1) Buscar por placa... @elseif($searchType==2) Buscar por usuario... @else Buscar... @endif"
                                       aria-label="Buscar"
                                       wire:model.live="searchText">
                            @else
                                <select class="form-control form-control-sm"
                                        aria-label="Selecciona sucursal"
                                        wire:model.live="searchText"
                                        wire:key="hq-select">
                                    <option value="">Todos</option>
                                    @foreach($headquarters as $h)
                                        <option value="{{ $h->id }}">{{ $h->name }}</option>
                                    @endforeach
                                </select>
                            @endif
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
                                    wire:loading.attr="disabled"
                                    wire:target="applyDateRange">
                                <i class="ti ti-search f-s-12"></i>
                            </button>
                        </div>
                    </div>

                    {{-- ===== Acciones (dejas tu bloque actual) ===== --}}
                    <div class="d-flex flex-wrap gap-2 justify-content-start mb-2">
                        <button class="btn btn-sm btn-success" wire:click="openAddWindow">
                            <i class="ti ti-square-plus f-s-12"></i> Nuevo
                        </button>
                        @role('admin')
                        <button wire:click="reportMonthly" class="btn btn-sm btn-primary">
                            <i class="ti ti-report-analytics f-s-12"></i> Mensual
                        </button>
                        <button wire:click="reportRmp" class="btn btn-sm btn-primary">
                            <i class="ti ti-report-analytics f-s-12"></i> RMP V.T
                        </button>
                        <button wire:click="reportStats" class="btn btn-sm btn-primary">
                            <i class="ti ti-report-analytics f-s-12"></i> Estadis.
                        </button>
                        @endrole

                        <button class="btn btn-sm btn-primary" wire:click="export">
                            <i class="ti ti-file-analytics f-s-12"></i>
                        </button>

                        <button class="btn btn-sm btn-primary" id="down" title="Bajar">
                            <i class="ti ti-square-chevrons-down f-s-12"></i>
                        </button>
                        <button
                            class="btn btn-sm {{ $groupMode ? 'btn-success' : 'btn-primary' }}"
                            wire:click="toggleGroup"
                            aria-pressed="{{ $groupMode ? 'true' : 'false' }}"
                            title="{{ $groupMode ? 'Agrupado: ON' : 'Agrupado: OFF' }}"
                        >
                            {{ $groupMode ? 'TS[ON]' : 'TS[OFF]' }}
                        </button>
                    </div>

                    <div class="table-responsive">
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
                                                <i  class="ti ti-edit f-s-18 text-success" wire:click="openEditWindow({{ $d->id }})"></i>
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
                                <td colspan="{{(!$groupMode) ? '8':'7'}}">TOTAL</td>
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

                    <h4 class="my-2 title-modules text-center">VEHÍCULOS DE APOYO</h4>
                    <div class="table-responsive mb-3">
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
                                            <i  class="ti ti-edit f-s-18 text-success" style="cursor:pointer"
                                                wire:click="openEditWindow({{ $d->id }})"></i>
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
                                    <td  colspan="15 title-modules">No se encontraron resultados</td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="text-center f-w-600  bg-primary"
                                   wire:key="dep-support-tfoot-{{ $groupMode ? 'g' : 'd' }}">
                            <tr>
                                <td class="text-end pa-e-6" colspan="{{(!$groupMode) ? '8':'6'}}">TOTAL</td>
                                <td>{{ number_format((float) data_get($supportTotals, 'times_total', 0)) }}</td>
                                <td>{{ number_format((float) data_get($supportTotals, 'times_total', 0)) }}</td>
                                <td>{{ number_format((float) data_get($supportTotals, 'price_total', 0), 2) }}</td>
                                <td>{{ number_format((float) data_get($supportTotals, 'passengers_total', 0)) }}</td>
                                <td>{{ number_format((float) data_get($supportTotals, 'passage_total', 0), 2) }}</td>
                                <td>{{ number_format((float) data_get($supportTotals, 'total_pasaje_total', 0), 2) }}</td>
                                <td>-</td>
                            </tr>
                            <tr>
                                <td class="text-end pa-e-6" colspan="{{(!$groupMode) ? '8':'6'}}">TOTAL GENERAL</td>
                                {{-- Empresa --}}
                                <td>{{ number_format($grandTotals->times_total ?? 0) }}</td>
                                <td>{{ number_format($grandTotals->times_total ?? 0) }}</td>
                                <td>{{ number_format($grandTotals->price_total ?? 0, 2) }}</td>
                                {{-- Vehículo --}}
                                <td>{{ number_format($grandTotals->passengers_total ?? 0) }}</td>
                                <td>{{ number_format($grandTotals->passage_total ?? 0, 2) }}</td>
                                <td>{{ number_format($grandTotals->total_pasaje_total ?? 0, 2) }}</td>
                                <td>-</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>

                </div>

            </div>
        </div>



    </div>

    {{-- Overlay de carga scopeado (ya no targetea fromDate/toDate) --}}
    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="openAddWindow,openEditWindow,searchType,applyDateRange,groupMode,export,toggleGroup,save,update,reportMonthly,reportRmp,reportStats">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>

@push('datepicker_js')
    <script>
        $( function() {

            $( "#uiFromDate" ).datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: 'yy-mm-dd',
                onSelect: function (dateText, inst) {
                    @this.set('uiFromDate', dateText);
                }
            });

            $( "#uiToDate" ).datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: 'yy-mm-dd',
                onSelect: function (dateText, inst) {
                    @this.set('uiToDate', dateText);
                }
            });
        } );
    </script>
@endpush
