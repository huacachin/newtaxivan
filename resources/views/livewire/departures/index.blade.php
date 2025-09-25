@push('styles')
    <style>
        .hide-label { position:absolute; left:-9999px; }
    </style>
@endpush

<div class="container-fluid">
    <!-- Basic Table start -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Salidas</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-door-exit f-s-16"></i>
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
    <!-- Basic Table end -->
    <div class="row table-section">
        <!-- Simple Table start -->
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        @if($searchType != 3)
                            <div class="col-xl-5 col-md-6 mb-2 mb-md-0">
                                <label class="form-label">Buscar</label>
                                <form class="app-form app-icon-form" action="#">

                                    <div class="position-relative">
                                        <input type="search" class="form-control" placeholder="Buscar..."
                                               aria-label="Buscar" wire:model.live="searchText">
                                        <i class="ti ti-search text-dark"></i>
                                    </div>
                                </form>
                            </div>
                        @else
                            <div class="col-xl-5 col-md-6 mb-2 mb-md-0">
                                <label class="form-label">Selecciona una sucursal</label>
                                <select class="form-select" aria-label="Selecciona item a filtrar"
                                        wire:model.live="searchText">
                                    <option value="">Todos</option>
                                    @foreach($headquarters as $h)
                                        <option value="{{$h->id}}">{{$h->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="col-xl-3 col-md-4 mb-2 mb-md-0">
                            <label class="form-label">Filtro</label>
                            <select class="form-select" aria-label="Selecciona item a filtrar"
                                    wire:model.live="searchType">
                                <option value="1">Placa</option>
                                <option value="2">Usuario</option>
                                <option value="3">Sucursal</option>
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-4 mb-2 mb-md-0">
                            <label class="form-label">Fecha Inicio</label>
                            <input type="date" class="form-control" wire:model.live="fromDate">
                        </div>
                        <div class="col-xl-2 col-md-4 mb-2 mb-md-0">
                            <label class="form-label">Fecha Fin</label>
                            <input type="date" class="form-control" wire:model.live="toDate">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Simple Table end -->
        <!-- Simple Table start -->
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-xl-2 col-md-4 mb-2 mb-md-0">
                            <button wire:click="reportMonthly" class="btn btn-primary w-100"><i class="ti ti-report-analytics f-s-16"></i>
                                Mensual
                            </button>
                        </div>
                        <div class="col-xl-2 col-md-4 mb-2 mb-md-0">
                            <button wire:click="reportRmp"  class="btn btn-primary w-100"><i class="ti ti-report-analytics f-s-16"></i>
                                RMP V.T
                            </button>
                        </div>
                        <div class="col-xl-2 col-md-4 mb-2 mb-md-0">
                            <button class="btn btn-primary w-100"><i class="ti ti-report-analytics f-s-16"></i>
                                Estadis.
                            </button>
                        </div>
                        <div class="col-xl-2 col-md-4 mb-2 mb-md-0">
                            <button class="btn btn-primary w-100" wire:click="export"><i class="ti ti-file-analytics f-s-16"></i>
                                Exportar
                            </button>
                        </div>
                        <div class="col-xl-2 col-md-4 mb-2 mb-md-0">
                            <button class="btn btn-primary w-100" wire:click="openAddModal"><i class="ti ti-square-plus f-s-16"></i>
                                Nuevo
                            </button>
                        </div>
                        <div class="col-xl-1 col-md-4 mb-2 mb-md-0">
                            <button class="btn btn-primary w-100" id="down"><i
                                    class="ti ti-square-chevrons-down f-s-17"></i>
                            </button>
                        </div>
                        <div class="col-xl-1 col-md-4 mb-2 mb-md-0">
                            <button class="btn {{($groupMode ? 'btn-success':'btn-primary')}} w-100"
                                    wire:click="toggleGroup"><i
                                    class="ti ti-a-b-2 f-s-17"></i>
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <!-- Simple Table end -->
        <!-- Simple Table start -->
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped table-hover"
                               wire:key="dep-table-{{ $groupMode ? 'g' : 'd' }}">

                            <thead class="table-primary text-center">
                            <tr>
                                @if(!$groupMode)
                                    <th class="ta-center" rowspan="2"></th>
                                @endif
                                <th class="ta-center" rowspan="2">N°</th>
                                <th class="ta-center" rowspan="2">Placa</th>
                                <th class="ta-center" rowspan="2">Fecha</th>
                                <th class="ta-center" colspan="2">Hora</th>
                                <th class="ta-center" rowspan="2">Sucursal</th>
                                <th class="ta-center" rowspan="2">Usuario</th>
                                <th class="ta-center" colspan="3">Empresa</th>
                                <th class="ta-center" colspan="3">Vehiculo</th>
                                <th class="ta-center" rowspan="2">Map</th>
                            </tr>
                            <tr>
                                <th class="ta-center">Sal.</th>
                                <th class="ta-center">Frec.</th>

                                <th class="ta-center">Salida</th>
                                <th class="ta-center">T. S</th>
                                <th class="ta-center">S/</th>

                                <th class="ta-center">P.</th>
                                <th class="ta-center">PJ</th>
                                <th class="ta-center">S/</th>
                            </tr>
                            </thead>


                            <tbody>
                            @if($rows->count() > 0)
                                @foreach($rows as $d)
                                    <tr class="text-center">
                                        @if(!$groupMode)
                                            <td class="text-center">
                                                <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"
                                                   wire:click="openEditModal({{ $d->id }})"></i>
                                            </td>
                                        @endif
                                        @if($groupMode)
                                            <td> {{ $d->ordinal }}</td>
                                        @else
                                            <td>{{$loop->iteration}}</td>
                                        @endif


                                        <td>{{$d->plate}}</td>
                                        <td>{{ \Illuminate\Support\Carbon::parse($d->date)->format('d/m/Y') }}</td>
                                        @if(!$groupMode)
                                            <td class="p-2">{{ $d->hour }}</td>
                                        @else
                                            <td>-</td>
                                        @endif

                                        <td>
                                            @if(!empty($d->freq))
                                                {{ $d->freq }}
                                            @else
                                                0:00:00
                                            @endif
                                        </td>
                                        <td>{{$d->headquarter_name}}</td>
                                        <td>{{$d->user_name}}</td>
                                        <td>{{ number_format($groupMode ? ($d->k1 ?? 0) : ($d->times ?? 0)) }}</td>
                                        <td>{{ number_format($groupMode ? ($d->k1 ?? 0) : ($d->times ?? 0)) }}</td>
                                        <td>{{ number_format($groupMode ? ($d->p1 ?? 0) : ($d->price ?? 0), 2) }}</td>
                                        <td>{{ number_format($groupMode ? ($d->pasajeros ?? 0) : ($d->passenger ?? 0)) }}</td>
                                        <td>{{ number_format($groupMode ? ($d->pasaje ?? 0) : ($d->passage ?? 0), 2) }} </td>
                                        <td>{{ number_format($d->total_pasaje ?? 0, 2) }}</td>
                                        @if(!$groupMode)
                                            <td>
                                                @if(!empty($d->latitude) && !empty($d->longitude))
                                                    <a href="https://maps.google.com/?q={{ $d->latitude }},{{ $d->longitude }}"
                                                       target="_blank" class="underline">🌍</a>
                                                @endif
                                            </td>
                                        @else
                                            <td>-</td>
                                        @endif
                                    </tr>
                                @endforeach

                            @else
                                <tr>
                                    <td colspan="15" class="text-center">No se encontrarón resultados</td>
                                </tr>
                            @endif
                            </tbody>

                            <tfoot class="table-primary text-center f-w-600">
                            <tr class="bg-blue-50 font-semibold">
                                <td class="p-2" colspan="8">TOTAL</td>
                                <td class="p-2 text-right">{{ number_format($totals->times_total ?? 0) }}</td>
                                <td class="p-2 text-right">{{ number_format($totals->times_total ?? 0) }}</td>
                                <td class="p-2 text-right">{{ number_format($totals->price_total ?? 0, 2) }}</td>
                                <td class="p-2 text-right">{{ number_format($totals->passengers_total ?? 0) }}</td>
                                <td class="p-2 text-right">{{ number_format($totals->passage_total ?? 0, 2) }}</td>
                                <td class="p-2 text-right">{{ number_format($totals->total_pasaje_total ?? 0, 2) }}</td>
                                <td>-</td>
                            </tr>
                            </tfoot>


                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- Simple Table end -->
        <!-- Simple Table start -->
        <div class="col-xl-12">
            <div class="card">
                <div class="card-header">
                    <h5>Vehículos de apoyo</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped table-hover">
                            <thead class="table-primary text-center">
                            <tr>
                                @if(!$groupMode)
                                    <th class="ta-center" rowspan="2"></th>
                                @endif
                                <th class="ta-center" rowspan="2">N°</th>
                                <th class="ta-center" rowspan="2">Placa</th>
                                <th class="ta-center" rowspan="2">Fecha</th>
                                <th class="ta-center" colspan="2">Hora</th>
                                <th class="ta-center" rowspan="2">Sucursal</th>
                                <th class="ta-center" rowspan="2">Usuario</th>
                                <th class="ta-center" colspan="3">Empresa</th>
                                <th class="ta-center" colspan="3">Vehiculo</th>
                                <th class="ta-center" rowspan="2">Map</th>
                            </tr>
                            <tr>
                                <th class="ta-center">Sal.</th>
                                <th class="ta-center">Frec.</th>

                                <th class="ta-center">Salida</th>
                                <th class="ta-center">T. S</th>
                                <th class="ta-center">S/</th>

                                <th class="ta-center">P.</th>
                                <th class="ta-center">PJ</th>
                                <th class="ta-center">S/</th>
                            </tr>
                            </thead>
                            <tbody wire:key="dep-support-tbody-{{ $groupMode ? 'g' : 'd' }}">
                            @forelse($supportRows as $d)
                                <tr class="text-center">
                                    @if(!$groupMode)
                                        <td class="text-center">
                                            <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"
                                               wire:click="openEditModal({{ $d->id }})"></i>
                                        </td>
                                    @endif

                                    {{-- Nº: ordinal 1..N en agrupado, iteración en detalle --}}
                                    <td>
                                        @if($groupMode) {{ $d->ordinal }} @else {{ $loop->iteration }} @endif
                                    </td>

                                    <td>{{ $d->plate }}</td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($d->date)->format('d/m/Y') }}</td>

                                    @if(!$groupMode)
                                        <td>{{ $d->hour }}</td>
                                        <td>{{ $d->freq ?: '0:00:00' }}</td>
                                    @else
                                        <td>-</td>
                                        <td>-</td>
                                    @endif

                                    <td>{{ $d->headquarter_name ?? '-' }}</td>
                                    <td>{{ $d->user_name ?? '-' }}</td>

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
                                                <a href="https://maps.google.com/?q={{ $d->latitude }},{{ $d->longitude }}" target="_blank">🌍</a>
                                            @endif
                                        </td>
                                    @else
                                        <td>-</td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="15" class="text-center">No se encontraron resultados</td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="table-primary text-center f-w-600"
                                   wire:key="dep-support-tfoot-{{ $groupMode ? 'g' : 'd' }}">
                            <tr>
                                <td colspan="8" class="text-end">TOTAL</td>
                                <td class="text-end">{{ number_format((float) data_get($supportTotals, 'times_total', 0)) }}</td>
                                <td class="text-end">{{ number_format((float) data_get($supportTotals, 'times_total', 0)) }}</td>
                                <td class="text-end">{{ number_format((float) data_get($supportTotals, 'price_total', 0), 2) }}</td>
                                <td class="text-end">{{ number_format((float) data_get($supportTotals, 'passengers_total', 0)) }}</td>
                                <td class="text-end">{{ number_format((float) data_get($supportTotals, 'passage_total', 0), 2) }}</td>
                                <td class="text-end">{{ number_format((float) data_get($supportTotals, 'total_pasaje_total', 0), 2) }}</td>
                                <td>-</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

        </div>
        <!-- Total General start -->
        <div class="col-xl-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h5>Total general (Registrados + Apoyo)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped table-hover">
                            <thead class="table-primary text-center">
                            <tr>
                                <th class="ta-center" colspan="8">TOTAL GENERAL</th>
                                <th class="ta-center">Salida</th>
                                <th class="ta-center">T. S</th>
                                <th class="ta-center">S/</th>
                                <th class="ta-center">P.</th>
                                <th class="ta-center">PJ</th>
                                <th class="ta-center">S/</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr class="text-center f-w-600">
                                <td class="text-end p-2" colspan="8">TOTAL GENERAL</td>
                                {{-- Empresa --}}
                                <td class="text-end p-2">{{ number_format($grandTotals->times_total ?? 0) }}</td>
                                <td class="text-end p-2">{{ number_format($grandTotals->times_total ?? 0) }}</td>
                                <td class="text-end p-2">{{ number_format($grandTotals->price_total ?? 0, 2) }}</td>
                                {{-- Vehículo --}}
                                <td class="text-end p-2">{{ number_format($grandTotals->passengers_total ?? 0) }}</td>
                                <td class="text-end p-2">{{ number_format($grandTotals->passage_total ?? 0, 2) }}</td>
                                <td class="text-end p-2">{{ number_format($grandTotals->total_pasaje_total ?? 0, 2) }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- Si quieres, debajo puedes poner un mini resumen --}}
                    {{-- <p class="mb-0">Registros combinados: {{ number_format($grandTotals->records ?? 0) }}</p> --}}
                </div>
            </div>
        </div>
        <!-- Total General end -->

    </div>
    <div class="modal fade" id="modalAddDeparture" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Salida</h5>
                    <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <strong>Revisa los siguientes errores:</strong>
                            <ul class="mb-0 mt-2 ps-3">
                                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="dep_plate" class="form-label">Placa</label>
                                <input id="dep_plate" type="text" class="form-control" placeholder="ABC-123"
                                       wire:model.defer="plate">
                                @error('plate') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Fecha</label>
                                <input type="date" class="form-control" wire:model.defer="date">
                                @error('date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Sucursal</label>
                                <select class="form-select" wire:model.defer="headquarter_id">
                                    <option value="">Seleccionar</option>
                                    @foreach($listHeadquarters as $hq)
                                        <option value="{{ $hq->id }}">{{ $hq->name }}</option>
                                    @endforeach
                                </select>
                                @error('headquarter_id') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Precio (S/)</label>
                                <input type="number" step="0.01" min="1" class="form-control" wire:model.defer="price">
                                @error('price') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Pasajeros</label>
                                <input type="number" class="form-control" wire:model.defer="passenger" min="1">
                                @error('passenger') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Pasaje (S/)</label>
                                <input type="number" class="form-control" wire:model.defer="passage" step="0.01" min="1">
                                @error('passage') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Geolocalización (solo lectura; se llena automáticamente) --}}
                        <div class="col-md-6 hide-label">
                            <div class="mb-3">
                                <label class="form-label visually-hidden">Latitud</label>
                                <input id="dep_lat_add" type="text" class="form-control" wire:model.defer="latitude" readonly>
                            </div>
                        </div>
                        <div class="col-md-6 hide-label">
                            <div class="mb-3">
                                <label class="form-label visually-hidden">Longitud</label>
                                <input id="dep_lng_add" type="text" class="form-control" wire:model.defer="longitude" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light-primary" wire:click="save">Agregar</button>
                    <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEditDeparture" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Salida</h5>
                    <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <strong>Revisa los siguientes errores:</strong>
                            <ul class="mb-0 mt-2 ps-3">
                                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- mismos campos que en Agregar --}}
                    <div class="row">
                        {{-- placa / fecha / sucursal / precio / pasajeros / pasaje --}}
                        {{-- ... copia/pega los inputs de arriba, cambiando los ids de lat/lng --}}
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="dep_plate_edit" class="form-label">Placa</label>
                                <input id="dep_plate_edit" type="text" class="form-control" wire:model.defer="plate">
                                @error('plate') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Fecha</label>
                                <input type="date" class="form-control" wire:model.defer="date">
                                @error('date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Sucursal</label>
                                <select class="form-select" wire:model.defer="headquarter_id">
                                    <option value="">Seleccionar</option>
                                    @foreach($listHeadquarters as $hq)
                                        <option value="{{ $hq->id }}">{{ $hq->name }}</option>
                                    @endforeach
                                </select>
                                @error('headquarter_id') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Precio (S/)</label>
                                <input type="number" step="0.01" class="form-control" wire:model.defer="price" min="1">
                                @error('price') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Pasajeros</label>
                                <input type="number" class="form-control" wire:model.defer="passenger" min="1">
                                @error('passenger') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Pasaje (S/)</label>
                                <input type="number" step="0.01" class="form-control" wire:model.defer="passage" min="1">
                                @error('passage') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        {{-- Geolocalización --}}
                        <div class="col-md-6 hide-label">
                            <div class="mb-3">
                                <label class="form-label visually-hidden">Latitud</label>
                                <input id="dep_lat_edit" type="text" class="form-control" wire:model.defer="latitude" readonly>
                            </div>
                        </div>
                        <div class="col-md-6 hide-label">
                            <div class="mb-3">
                                <label class="form-label visually-hidden">Longitud</label>
                                <input id="dep_lng_edit" type="text" class="form-control" wire:model.defer="longitude" readonly>
                            </div>
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

@push('scripts')
    <script>
        (function () {
            function getGeoAndFill(latId, lngId) {
                if (!navigator.geolocation) return;
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        const lat = pos.coords.latitude.toFixed(6);
                        const lng = pos.coords.longitude.toFixed(6);
                        const latInput = document.getElementById(latId);
                        const lngInput = document.getElementById(lngId);
                        if (latInput && lngInput) {
                            latInput.value = lat;
                            lngInput.value = lng;
                            // notifica a Livewire el cambio del input
                            latInput.dispatchEvent(new Event('input', { bubbles: true }));
                            lngInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    },
                    (err) => { /* opcional: mostrar aviso */ },
                    { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
                );
            }

            document.addEventListener('shown.bs.modal', function (e) {
                const id = e.target?.id || '';
                if (id === 'modalAddDeparture') {
                    getGeoAndFill('dep_lat_add','dep_lng_add');
                }
                if (id === 'modalEditDeparture') {
                    getGeoAndFill('dep_lat_edit','dep_lng_edit');
                }
            });
        })();
    </script>
@endpush

