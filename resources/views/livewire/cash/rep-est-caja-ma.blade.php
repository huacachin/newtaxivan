
@push('styles')
    <style>
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
    </style>
@endpush
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Reporte Estadístico Caja M.A</h4>
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
            <div class="card-header">
                <div class="row">
                    <div class="col-md-4">
                        <label>Mes</label>
                        <select wire:model.live="month" class="form-select">
                            @for ($m=1; $m<=12; $m++)
                                <option value="{{ $m }}">{{ \App\Livewire\Cash\RepEstCajaMa::monthName($m) }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>Año</label>
                        <select wire:model.live="year" class="form-select">
                            @for ($y = 2015; $y <= 2030; $y++)
                                <option value="{{ $y }}">{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>Sede</label>
                        <select wire:model.live="headquarterId" class="form-select">
                            <option value="">Todas</option>
                            @foreach ($headquarters as $hq)
                                <option value="{{ $hq['id'] }}">{{ $hq['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead class="bg-primary text-center">
                        <tr>
                            <th rowspan="3" class="p-2 border">Fecha</th>
                            <th colspan="9" class="p-2 border">Ingreso</th>
                            <th rowspan="3" class="p-2 border">Egreso</th>
                            <th rowspan="3" class="p-2 border">Utilidad</th>
                        </tr>
                        <tr>
                            <th colspan="4" class="p-2 border">Pagos</th>
                            <th colspan="3" class="p-2 border">Salidas</th>
                            <th rowspan="2" class="p-2 border">Otros</th>
                            <th rowspan="3" class="p-2 border">Total</th>
                        </tr>
                        <tr>
                            <th class="p-2 border">Cotización</th>
                            <th class="p-2 border">Retraso</th>
                            <th class="p-2 border">Deuda</th>
                            <th class="p-2 border">Total</th>
                            <th class="p-2 border">Empresa</th>
                            <th class="p-2 border">Apoyo</th>
                            <th class="p-2 border">Total</th>
                        </tr>
                        </thead>
                        <tbody class="text-center">
                        @forelse($rows as $r)
                            @php
                                $isSunday = \Carbon\Carbon::createFromFormat('d/m/Y',$r['fecha'])->isSunday();
                            @endphp
                            <tr @if($isSunday) class="bg-danger " @endif>
                                <td @if($isSunday) class="text-white" @endif >{{ $r['fecha'] }}</td>

                                <td @if($isSunday) class="text-white" @endif >{{ number_format($r['cotizacion'],2,'.',',') }}</td>
                                <td @if($isSunday) class="text-white" @endif >{{ number_format($r['retraso'],2,'.',',') }}</td>
                                <td @if($isSunday) class="text-white" @endif >{{ number_format($r['deuda'],2,'.',',') }}</td>
                                <td @if($isSunday) class="text-white" @endif >{{ number_format($r['pago_total'],2,'.',',') }}</td>

                                <td @if($isSunday) class="text-white" @endif >{{ number_format($r['empresa'],2,'.',',') }}</td>
                                <td @if($isSunday) class="text-white" @endif >{{ number_format($r['apoyo'],2,'.',',') }}</td>
                                <td @if($isSunday) class="text-white" @endif >{{ number_format($r['salidas_total'],2,'.',',') }}</td>

                                <td @if($isSunday) class="text-white" @endif >{{ number_format($r['otros'],2,'.',',') }}</td>
                                <td @if($isSunday) class="text-white" @endif >{{ number_format($r['ingresos_total'],2,'.',',') }}</td>

                                <td @if($isSunday) class="text-white" @endif >{{ number_format($r['egreso'],2,'.',',') }}</td>
                                <td @if($isSunday) class="text-white" @endif >{{ number_format($r['utilidad'],2,'.',',') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12">
                                    Sin datos para el período seleccionado.
                                </td>
                            </tr>
                        @endforelse

                        @if(!empty($rows))
                            <tr class="bg-primary">
                                <td class="border p-2 text-right">Total</td>

                                <td class="border p-2 text-right">{{ number_format($totales['pago'],2,'.',',') }}</td>
                                <td class="border p-2 text-right">{{ number_format($totales['retraso'],2,'.',',') }}</td>
                                <td class="border p-2 text-right">{{ number_format($totales['deuda'],2,'.',',') }}</td>
                                <td class="border p-2 text-right">{{ number_format($totales['pago_total'],2,'.',',') }}</td>

                                <td class="border p-2 text-right">{{ number_format($totales['empresa'],2,'.',',') }}</td>
                                <td class="border p-2 text-right">{{ number_format($totales['apoyo'],2,'.',',') }}</td>
                                <td class="border p-2 text-right">{{ number_format($totales['salidas_total'],2,'.',',') }}</td>

                                <td class="border p-2 text-right">{{ number_format($totales['otros'],2,'.',',') }}</td>
                                <td class="border p-2 text-right text-red-600">{{ number_format($totales['ingresos_total'],2,'.',',') }}</td>

                                <td class="border p-2 text-right">{{ number_format($totales['egreso'],2,'.',',') }}</td>
                                <td class="border p-2 text-right text-red-600">{{ number_format($totales['utilidad'],2,'.',',') }}</td>
                            </tr>
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="card">
            <div class="card-header text-center">
                <h4>ESTADÍSTICA DE CAJA ANUAL – {{ $year }}</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead class="bg-primary text-center">
                        <tr>
                            <th rowspan="3" class="p-2 border">Mes</th>
                            <th colspan="9" class="p-2 border">Ingreso</th>
                            <th rowspan="3" class="p-2 border">Egreso</th>
                            <th rowspan="3" class="p-2 border">Utilidad</th>
                        </tr>
                        <tr>
                            <th colspan="4" class="p-2 border">Pago</th>
                            <th colspan="3" class="p-2 border">Salidas</th>
                            <th rowspan="2" class="p-2 border">Otros</th>
                            <th rowspan="3" class="p-2 border">Total</th>
                        </tr>
                        <tr>
                            <th class="p-2 border">Cotización</th>
                            <th class="p-2 border">Retraso</th>
                            <th class="p-2 border">Deuda</th>
                            <th class="p-2 border">Total</th>
                            <th class="p-2 border">Empresa</th>
                            <th class="p-2 border">Apoyo</th>
                            <th class="p-2 border">Total</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($anual as $r)
                            <tr>
                                <td class="border p-2 font-medium">{{ $r['mes'] }}</td>

                                <td class="border p-2 text-right">{{ number_format($r['pago'],2,'.',',') }}</td>
                                <td class="border p-2 text-right">{{ number_format($r['retraso'],2,'.',',') }}</td>
                                <td class="border p-2 text-right">{{ number_format($r['deuda'],2,'.',',') }}</td>
                                <td class="border p-2 text-right">{{ number_format($r['pago_total'],2,'.',',') }}</td>

                                <td class="border p-2 text-right">{{ number_format($r['empresa'],2,'.',',') }}</td>
                                <td class="border p-2 text-right">{{ number_format($r['apoyo'],2,'.',',') }}</td>
                                <td class="border p-2 text-right">{{ number_format($r['salidas_total'],2,'.',',') }}</td>

                                <td class="border p-2 text-right">{{ number_format($r['otros'],2,'.',',') }}</td>
                                <td class="border p-2 text-right text-red-600">{{ number_format($r['ingresos_total'],2,'.',',') }}</td>

                                <td class="border p-2 text-right">{{ number_format($r['egreso'],2,'.',',') }}</td>
                                <td class="border p-2 text-right text-red-600">{{ number_format($r['utilidad'],2,'.',',') }}</td>
                            </tr>
                        @endforeach

                        @if(!empty($anual))
                            <tr class="bg-primary">
                                <td class="border p-2">Total</td>
                                <td class="border p-2 text-right">{{ number_format($anualTotales['pago'],2,'.',',') }}</td>
                                <td class="border p-2 text-right">{{ number_format($anualTotales['retraso'],2,'.',',') }}</td>
                                <td class="border p-2 text-right">{{ number_format($anualTotales['deuda'],2,'.',',') }}</td>
                                <td class="border p-2 text-right">{{ number_format($anualTotales['pago_total'],2,'.',',') }}</td>

                                <td class="border p-2 text-right">{{ number_format($anualTotales['empresa'],2,'.',',') }}</td>
                                <td class="border p-2 text-right">{{ number_format($anualTotales['apoyo'],2,'.',',') }}</td>
                                <td class="border p-2 text-right">{{ number_format($anualTotales['salidas_total'],2,'.',',') }}</td>

                                <td class="border p-2 text-right">{{ number_format($anualTotales['otros'],2,'.',',') }}</td>
                                <td class="border p-2 text-right text-red-600">{{ number_format($anualTotales['ingresos_total'],2,'.',',') }}</td>

                                <td class="border p-2 text-right">{{ number_format($anualTotales['egreso'],2,'.',',') }}</td>
                                <td class="border p-2 text-right text-red-600">{{ number_format($anualTotales['utilidad'],2,'.',',') }}</td>
                            </tr>

                            <tr class="bg-primary">
                                <td class="border p-2">Promedio</td>
                                <td class="border p-2 text-right">{{ number_format($anualPromedios['pago'],2,'.',',') }}</td>
                                <td class="border p-2 text-right">{{ number_format($anualPromedios['retraso'],2,'.',',') }}</td>
                                <td class="border p-2 text-right">{{ number_format($anualPromedios['deuda'],2,'.',',') }}</td>
                                <td class="border p-2 text-right">{{ number_format($anualPromedios['pago_total'],2,'.',',') }}</td>

                                <td class="border p-2 text-right">{{ number_format($anualPromedios['empresa'],2,'.',',') }}</td>
                                <td class="border p-2 text-right">{{ number_format($anualPromedios['apoyo'],2,'.',',') }}</td>
                                <td class="border p-2 text-right">{{ number_format($anualPromedios['salidas_total'],2,'.',',') }}</td>

                                <td class="border p-2 text-right">{{ number_format($anualPromedios['otros'],2,'.',',') }}</td>
                                <td class="border p-2 text-right text-red-600">{{ number_format($anualPromedios['ingresos_total'],2,'.',',') }}</td>

                                <td class="border p-2 text-right">{{ number_format($anualPromedios['egreso'],2,'.',',') }}</td>
                                <td class="border p-2 text-right text-red-600">{{ number_format($anualPromedios['utilidad'],2,'.',',') }}</td>
                            </tr>
                        @endif
                        </tbody>
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
