@push('styles')
    <style>
        .bg-modules{ background-color:red !important; }
    </style>
@endpush

@php
    $monthName = \Illuminate\Support\Str::upper(
        \Carbon\Carbon::create($year, $month, 1)->translatedFormat('F')
    );
    $months = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
    $years  = range(now()->year-10, now()->year+1);
@endphp

<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">REPORTE MENSUAL POR PLACA - V.T {{$monthName}} DEL {{$year}}</h4>
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
                    <a href="#" class="f-s-14">Mensual por placa</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">

        <!-- Tabla mensual por placa -->
        <div class="col-xl-12">
            <div class="card shadow-sm">

                <div class="card-body">

                    <div class="row mb-2">
                        <div class="col-12">
                            <div class="d-flex flex-nowrap align-items-end gap-2 overflow-auto py-1">

                                <!-- Mes -->
                                <div class="flex-shrink-0">
                                    <label class="form-label mb-1">Mes</label>
                                    <select class="form-select form-select-sm" wire:model.live="month">
                                        @foreach($months as $mVal => $mName)
                                            <option value="{{ $mVal }}">{{ $mName }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Año -->
                                <div class="flex-shrink-0">
                                    <label class="form-label mb-1">Año</label>
                                    <select class="form-select form-select-sm" wire:model.live="year">
                                        @foreach($years as $y)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Regresar -->
                                <a href="{{ route('departures.index') }}"
                                   class="btn btn-sm btn-primary flex-shrink-0 align-self-end">
                                    <i class="ti ti-rotate-2 f-s-12"></i> Regresar
                                </a>

                                <!-- Exportar -->
                                <a href="#"
                                   wire:click.prevent="export"
                                   class="btn btn-sm btn-primary flex-shrink-0 align-self-end">
                                    <i class="ti ti-file-analytics f-s-12"></i> Exportar
                                </a>

                                <button class="btn btn-sm btn-primary" id="down" title="Bajar">
                                    <i class="fa-solid fa-angle-down"></i>
                                </button>



                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>Item</th>
                                <th>COD</th>
                                <th>Placa</th>
                                @foreach($days as $d)
                                    @php $isSun = \Carbon\Carbon::create($year, $month, $d)->isSunday(); @endphp
                                    <th class="{{ $isSun ? 'bg-danger' : '' }}">{{ $d }}</th>
                                @endforeach
                                <th>T. Salida</th>
                            </tr>
                            </thead>

                            <tbody>
                            @php $i=0; @endphp
                            @forelse($rows as $row)
                                @php $i++; @endphp
                                <tr class="text-center">
                                    <td>{{ $i }}</td>
                                    <td>{{ $row['sort_order'] }}</td>
                                    <td class="text-start">{{ $row['plate'] }}</td>
                                    @foreach($days as $d)
                                        <td>{{ $row['daily'][$d] ?? 0 }}</td>
                                    @endforeach
                                    <td><strong>{{ $row['total'] }}</strong></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 2 + count($days) + 1 }}" class="text-center text-muted py-4">
                                        No se encontraron resultados
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="text-center f-w-600 bg-primary">
                            <tr>
                                <th colspan="3" class="text-center">Total Vueltas</th>
                                @foreach($days as $d)
                                    <th class="bg-modules">{{ $totalPerDay[$d] ?? 0 }}</th>
                                @endforeach
                                <th>{{ array_sum($totalPerDay) }}</th>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-center">Total V.T</th>
                                @foreach($days as $d)
                                    <th class="bg-modules">{{ $vehiclesWorkedPerDay[$d] ?? 0 }}</th>
                                @endforeach
                                <th>{{ array_sum($vehiclesWorkedPerDay) }}</th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>

                    <small class="text-muted">
                        * Encabezado y pie fijos al hacer scroll. Domingos resaltados en rojo.
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="month,year,export">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>
