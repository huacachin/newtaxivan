
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
                        <tbody class="text-center">
                        @forelse($rows as $r)
                            @php
                                $isSunday = \Carbon\Carbon::createFromFormat('d/m/Y',$r['fecha'])->isSunday();
                            @endphp
                            <tr>
                                <td @if($isSunday) style="background-color: var(--bs-danger) !important; color: #fff !important;" @else style="color: #000;" @endif>{{ $r['fecha'] }}</td>

                                <td style="color: #3e9281;">{{ number_format($r['cotizacion'],2,'.',',') }}</td>
                                <td style="color: #3e9281;">{{ number_format($r['retraso'],2,'.',',') }}</td>
                                <td style="color: #3e9281;">{{ number_format($r['deuda'],2,'.',',') }}</td>
                                <td style="color: #3e9281;">{{ number_format($r['pago_total'],2,'.',',') }}</td>

                                <td style="color: #3e9281;">{{ number_format($r['empresa'],2,'.',',') }}</td>
                                <td style="color: #3e9281;">{{ number_format($r['apoyo'],2,'.',',') }}</td>
                                <td style="color: #3e9281;">{{ number_format($r['salidas_total'],2,'.',',') }}</td>

                                <td style="color: #3e9281;">{{ number_format($r['otros'],2,'.',',') }}</td>
                                <td style="color: red;">{{ number_format($r['ingresos_total'],2,'.',',') }}</td>

                                <td style="color: #3e9281;">{{ number_format($r['egreso'],2,'.',',') }}</td>
                                <td style="color: red;">{{ number_format($r['utilidad'],2,'.',',') }}</td>
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

                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($totales['pago'],2,'.',',') }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($totales['retraso'],2,'.',',') }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($totales['deuda'],2,'.',',') }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($totales['pago_total'],2,'.',',') }}</td>

                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($totales['empresa'],2,'.',',') }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($totales['apoyo'],2,'.',',') }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($totales['salidas_total'],2,'.',',') }}</td>

                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($totales['otros'],2,'.',',') }}</td>
                                <td class="fw-bold" style="background-color: #CEE7FF !important; color: red !important;">{{ number_format($totales['ingresos_total'],2,'.',',') }}</td>

                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($totales['egreso'],2,'.',',') }}</td>
                                <td class="fw-bold" style="background-color: #CEE7FF !important; color: red !important;">{{ number_format($totales['utilidad'],2,'.',',') }}</td>
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

                                <td style="color: #3e9281;">{{ number_format($r['pago'],2,'.',',') }}</td>
                                <td style="color: #3e9281;">{{ number_format($r['retraso'],2,'.',',') }}</td>
                                <td style="color: #3e9281;">{{ number_format($r['deuda'],2,'.',',') }}</td>
                                <td style="color: #3e9281;">{{ number_format($r['pago_total'],2,'.',',') }}</td>

                                <td style="color: #3e9281;">{{ number_format($r['empresa'],2,'.',',') }}</td>
                                <td style="color: #3e9281;">{{ number_format($r['apoyo'],2,'.',',') }}</td>
                                <td style="color: #3e9281;">{{ number_format($r['salidas_total'],2,'.',',') }}</td>

                                <td style="color: #3e9281;">{{ number_format($r['otros'],2,'.',',') }}</td>
                                <td style="color: red;">{{ number_format($r['ingresos_total'],2,'.',',') }}</td>

                                <td style="color: #3e9281;">{{ number_format($r['egreso'],2,'.',',') }}</td>
                                <td style="color: red;">{{ number_format($r['utilidad'],2,'.',',') }}</td>
                            </tr>
                        @endforeach


                        </tbody>
                        <tfoot>
                        @if(!empty($anual))
                            <tr>
                                <td class="fw-bold" style="background-color: #CEE7FF !important; color: #000 !important;">Total</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($anualTotales['pago'],2,'.',',') }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($anualTotales['retraso'],2,'.',',') }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($anualTotales['deuda'],2,'.',',') }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($anualTotales['pago_total'],2,'.',',') }}</td>

                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($anualTotales['empresa'],2,'.',',') }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($anualTotales['apoyo'],2,'.',',') }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($anualTotales['salidas_total'],2,'.',',') }}</td>

                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($anualTotales['otros'],2,'.',',') }}</td>
                                <td class="fw-bold" style="background-color: #CEE7FF !important; color: red !important;">{{ number_format($anualTotales['ingresos_total'],2,'.',',') }}</td>

                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($anualTotales['egreso'],2,'.',',') }}</td>
                                <td class="fw-bold" style="background-color: #CEE7FF !important; color: red !important;">{{ number_format($anualTotales['utilidad'],2,'.',',') }}</td>
                            </tr>

                            <tr>
                                <td class="fw-bold" style="background-color: #CEE7FF !important; color: #000 !important;">Promedio</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($anualPromedios['pago'],2,'.',',') }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($anualPromedios['retraso'],2,'.',',') }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($anualPromedios['deuda'],2,'.',',') }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($anualPromedios['pago_total'],2,'.',',') }}</td>

                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($anualPromedios['empresa'],2,'.',',') }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($anualPromedios['apoyo'],2,'.',',') }}</td>
                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($anualPromedios['salidas_total'],2,'.',',') }}</td>

                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($anualPromedios['otros'],2,'.',',') }}</td>
                                <td class="fw-bold" style="background-color: #CEE7FF !important; color: red !important;">{{ number_format($anualPromedios['ingresos_total'],2,'.',',') }}</td>

                                <td style="background-color: #CEE7FF !important; color: #000 !important;">{{ number_format($anualPromedios['egreso'],2,'.',',') }}</td>
                                <td class="fw-bold" style="background-color: #CEE7FF !important; color: red !important;">{{ number_format($anualPromedios['utilidad'],2,'.',',') }}</td>
                            </tr>
                        @endif
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>


    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="month,year,headquarterId,export">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>
