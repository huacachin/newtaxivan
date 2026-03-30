
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">REPORTE ESTADÍSTICO CAJA M.A</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Caja</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Rep Est Caja M.A</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row">
        <div class="card">

            <div class="card-body">
                <div class="row my-2">
                    <div class="col-12">
                        <div class="d-flex flex-wrap align-items-end gap-2 overflow-auto py-1">

                            <!-- Mes -->
                            <div class="flex-shrink-0" style="min-width: 200px;">
                                <label class="form-label mb-1">Mes</label>
                                <select wire:model.live="month" class="form-select form-select-sm">
                                    @for ($m=1; $m<=12; $m++)
                                        <option value="{{ $m }}">{{ \App\Livewire\Cash\RepEstCajaMa::monthName($m) }}</option>
                                    @endfor
                                </select>
                            </div>

                            <!-- Año -->
                            <div class="flex-shrink-0" style="min-width: 140px;">
                                <label class="form-label mb-1">Año</label>
                                <select wire:model.live="year" class="form-select form-select-sm">
                                    @for ($y = 2015; $y <= 2030; $y++)
                                        <option value="{{ $y }}">{{ $y }}</option>
                                    @endfor
                                </select>
                            </div>

                            <!-- Sede -->
                            <div class="flex-shrink-0" style="min-width: 220px;">
                                <label class="form-label mb-1">Sede</label>
                                <select wire:model.live="headquarterId" class="form-select form-select-sm">
                                    <option value="">Todas</option>
                                    @foreach ($headquarters as $hq)
                                        <option value="{{ $hq['id'] }}">{{ $hq['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Exportar -->
                            <button class="btn btn-sm btn-primary flex-shrink-0 align-self-end"
                                    wire:click="export">
                                <i class="ti ti-file-analytics f-s-12"></i>
                            </button>

                            <!-- Exportar -->
                            <button class="btn btn-sm btn-primary flex-shrink-0 align-self-end" id ="down">
                                <i class="fa-solid fa-angle-down"></i>
                            </button>

                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="bg-primary text-center">
                        <tr>
                            <th rowspan="3">Fecha</th>
                            <th colspan="9">Ingreso</th>
                            <th rowspan="3">Egreso</th>
                            <th rowspan="3">Utilidad</th>
                        </tr>
                        <tr>
                            <th colspan="4">Pagos</th>
                            <th colspan="3">Salidas</th>
                            <th rowspan="2">Otros</th>
                            <th rowspan="3">Total</th>
                        </tr>
                        <tr>
                            <th>Cotización</th>
                            <th>Retraso</th>
                            <th>Deuda</th>
                            <th>Total</th>
                            <th>Empresa</th>
                            <th>Apoyo</th>
                            <th>Total</th>
                        </tr>
                        </thead>
                        @php $fmt = fn($v) => $v != 0 ? number_format($v,2,'.',',') : ''; @endphp
                        <tbody class="text-center">
                        @forelse($rows as $r)
                            @php
                                $isSunday = \Carbon\Carbon::createFromFormat('d/m/Y',$r['fecha'])->isSunday();
                            @endphp
                            @php
                                $dateObj = \Carbon\Carbon::createFromFormat('d/m/Y', $r['fecha']);
                                $dateStr = $dateObj->format('Y-m-d');
                            @endphp
                            <tr>
                                <td @if($isSunday) style="background-color: var(--bs-danger) !important; color: #fff !important;" @else style="color: #000;" @endif>{{ $r['fecha'] }}</td>

                                <td style="color: #3e9281;">@if($r['cotizacion'] != 0)<a href="{{ route('payments.index', ['from' => $dateStr, 'to' => $dateStr, 'type' => 'PAGO']) }}" target="_blank">{{ $fmt($r['cotizacion']) }}</a>@endif</td>
                                <td style="color: #3e9281;">@if($r['retraso'] != 0)<a href="{{ route('payments.index', ['from' => $dateStr, 'to' => $dateStr, 'type' => 'RETRASO']) }}" target="_blank">{{ $fmt($r['retraso']) }}</a>@endif</td>
                                <td style="color: #3e9281;">@if($r['deuda'] != 0)<a href="{{ route('payments.index', ['from' => $dateStr, 'to' => $dateStr, 'type' => 'DEUDA']) }}" target="_blank">{{ $fmt($r['deuda']) }}</a>@endif</td>
                                <td style="color: #3e9281;">@if($r['pago_total'] != 0)<a href="{{ route('payments.index', ['from' => $dateStr, 'to' => $dateStr]) }}" target="_blank">{{ $fmt($r['pago_total']) }}</a>@endif</td>

                                <td style="color: #3e9281;">@if($r['empresa'] != 0)<a href="{{ route('departures.index', ['fromDate' => $dateStr, 'toDate' => $dateStr]) }}" target="_blank">{{ $fmt($r['empresa']) }}</a>@endif</td>
                                <td style="color: #3e9281;">@if($r['apoyo'] != 0)<a href="{{ route('departures.index', ['fromDate' => $dateStr, 'toDate' => $dateStr]) }}" target="_blank">{{ $fmt($r['apoyo']) }}</a>@endif</td>
                                <td style="color: #3e9281;">@if($r['salidas_total'] != 0)<a href="{{ route('departures.index', ['fromDate' => $dateStr, 'toDate' => $dateStr]) }}" target="_blank">{{ $fmt($r['salidas_total']) }}</a>@endif</td>

                                <td style="color: #3e9281;">@if($r['otros'] != 0)<a href="{{ route('cash.incomes', ['date_start' => $dateStr, 'date_end' => $dateStr]) }}" target="_blank">{{ $fmt($r['otros']) }}</a>@endif</td>
                                <td style="color: red;">{{ $fmt($r['ingresos_total']) }}</td>

                                <td style="color: #3e9281;">@if($r['egreso'] != 0)<a href="{{ route('cash.expenses', ['date_start' => $dateStr, 'date_end' => $dateStr]) }}" target="_blank">{{ $fmt($r['egreso']) }}</a>@endif</td>
                                <td style="color: red;">{{ $fmt($r['utilidad']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12">
                                    Sin datos para el período seleccionado.
                                </td>
                            </tr>
                        @endforelse

                        @if(!empty($rows))
                            <tr>
                                <td class="fw-bold" style="background-color: #CEE7FF !important; color: #000 !important;">Total</td>

                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($totales['pago']) }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($totales['retraso']) }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($totales['deuda']) }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($totales['pago_total']) }}</td>

                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($totales['empresa']) }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($totales['apoyo']) }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($totales['salidas_total']) }}</td>

                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($totales['otros']) }}</td>
                                <td class="fw-bold" style="background-color: #CEE7FF !important; color: red !important;">{{ $fmt($totales['ingresos_total']) }}</td>

                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($totales['egreso']) }}</td>
                                <td class="fw-bold" style="background-color: #CEE7FF !important; color: red !important;">{{ $fmt($totales['utilidad']) }}</td>
                            </tr>
                        @endif
                        </tbody>
                    </table>
                    <h5 class="mb-2 title-modules" >ESTADÍSTICA DE CAJA ANUAL – {{ $year }}</h5>
                    <table class="table table-striped table-bordered">
                        <thead class="bg-primary">
                        <tr>
                            <th rowspan="3">Mes</th>
                            <th colspan="9">Ingreso</th>
                            <th rowspan="3">Egreso</th>
                            <th rowspan="3">Utilidad</th>
                        </tr>
                        <tr>
                            <th colspan="4">Pago</th>
                            <th colspan="3">Salidas</th>
                            <th rowspan="2">Otros</th>
                            <th rowspan="3">Total</th>
                        </tr>
                        <tr>
                            <th>Cotización</th>
                            <th>Retraso</th>
                            <th>Deuda</th>
                            <th>Total</th>
                            <th>Empresa</th>
                            <th>Apoyo</th>
                            <th>Total</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($anual as $r)
                            <tr>
                                <td style="color: #000;">{{ $r['mes'] }}</td>

                                <td style="color: #3e9281;">{{ $fmt($r['pago']) }}</td>
                                <td style="color: #3e9281;">{{ $fmt($r['retraso']) }}</td>
                                <td style="color: #3e9281;">{{ $fmt($r['deuda']) }}</td>
                                <td style="color: #3e9281;">{{ $fmt($r['pago_total']) }}</td>

                                <td style="color: #3e9281;">{{ $fmt($r['empresa']) }}</td>
                                <td style="color: #3e9281;">{{ $fmt($r['apoyo']) }}</td>
                                <td style="color: #3e9281;">{{ $fmt($r['salidas_total']) }}</td>

                                <td style="color: #3e9281;">{{ $fmt($r['otros']) }}</td>
                                <td style="color: red;">{{ $fmt($r['ingresos_total']) }}</td>

                                <td style="color: #3e9281;">{{ $fmt($r['egreso']) }}</td>
                                <td style="color: red;">{{ $fmt($r['utilidad']) }}</td>
                            </tr>
                        @endforeach


                        </tbody>
                        <tfoot>
                        @if(!empty($anual))
                            <tr>
                                <td class="fw-bold" style="background-color: #CEE7FF !important; color: #000 !important;">Total</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($anualTotales['pago']) }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($anualTotales['retraso']) }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($anualTotales['deuda']) }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($anualTotales['pago_total']) }}</td>

                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($anualTotales['empresa']) }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($anualTotales['apoyo']) }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($anualTotales['salidas_total']) }}</td>

                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($anualTotales['otros']) }}</td>
                                <td class="fw-bold" style="background-color: #CEE7FF !important; color: red !important;">{{ $fmt($anualTotales['ingresos_total']) }}</td>

                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($anualTotales['egreso']) }}</td>
                                <td class="fw-bold" style="background-color: #CEE7FF !important; color: red !important;">{{ $fmt($anualTotales['utilidad']) }}</td>
                            </tr>

                            <tr>
                                <td class="fw-bold" style="background-color: #CEE7FF !important; color: #000 !important;">Promedio</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($anualPromedios['pago']) }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($anualPromedios['retraso']) }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($anualPromedios['deuda']) }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($anualPromedios['pago_total']) }}</td>

                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($anualPromedios['empresa']) }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($anualPromedios['apoyo']) }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($anualPromedios['salidas_total']) }}</td>

                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($anualPromedios['otros']) }}</td>
                                <td class="fw-bold" style="background-color: #CEE7FF !important; color: red !important;">{{ $fmt($anualPromedios['ingresos_total']) }}</td>

                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ $fmt($anualPromedios['egreso']) }}</td>
                                <td class="fw-bold" style="background-color: #CEE7FF !important; color: red !important;">{{ $fmt($anualPromedios['utilidad']) }}</td>
                            </tr>
                        @endif
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
