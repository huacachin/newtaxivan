<div class="p-4">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold">
            REPORTE ESTADÍSTICO CAJA M.A.
        </h2>

        <label class="text-sm ml-4">Mes</label>
        <select class="border rounded px-2 py-1" wire:model.live="month">
            @for ($m=1; $m<=12; $m++)
                <option value="{{ str_pad($m,2,'0',STR_PAD_LEFT) }}">
                    {{ \Carbon\Carbon::create(null,$m,1)->locale('es')->isoFormat('MMMM') }}
                </option>
            @endfor
        </select>

        <label class="text-sm ml-4">Año</label>
        <select class="border rounded px-2 py-1" wire:model.live="year">
            @for ($y=2015; $y<=2030; $y++)
                <option value="{{ $y }}">{{ $y }}</option>
            @endfor
        </select>

        <label class="text-sm ml-4">Sede</label>
        <select class="border rounded px-2 py-1" wire:model.live="headquarterId">
            <option value="">Todas</option>
            @foreach(\Illuminate\Support\Facades\DB::table('headquarters')->orderBy('name')->get() as $hq)
                <option value="{{ $hq->id }}">{{ $hq->name }}</option>
            @endforeach
        </select>

        <button class="ml-auto bg-slate-700 text-white rounded px-3 py-2" wire:click="consultar">
            Consultar
        </button>
    </div>

    <div class="mt-4 overflow-auto" id="Reporte">
        <table class="min-w-[1200px] border text-xs">
            <thead class="bg-slate-800 text-white">
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
            <tbody>
            @forelse($rows as $r)
                @php
                    $isSunday = \Carbon\Carbon::createFromFormat('d/m/Y',$r['fecha'])->isSunday();
                @endphp
                <tr @if($isSunday) class="bg-red-50" @endif>
                    <td class="border p-2">{{ $r['fecha'] }}</td>

                    <td class="border p-2 text-right">{{ number_format($r['cotizacion'],2,'.',',') }}</td>
                    <td class="border p-2 text-right">{{ number_format($r['retraso'],2,'.',',') }}</td>
                    <td class="border p-2 text-right">{{ number_format($r['deuda'],2,'.',',') }}</td>
                    <td class="border p-2 text-right font-medium">{{ number_format($r['pago_total'],2,'.',',') }}</td>

                    <td class="border p-2 text-right">{{ number_format($r['empresa'],2,'.',',') }}</td>
                    <td class="border p-2 text-right">{{ number_format($r['apoyo'],2,'.',',') }}</td>
                    <td class="border p-2 text-right font-medium">{{ number_format($r['salidas_total'],2,'.',',') }}</td>

                    <td class="border p-2 text-right">{{ number_format($r['otros'],2,'.',',') }}</td>
                    <td class="border p-2 text-right font-semibold text-red-600">{{ number_format($r['ingresos_total'],2,'.',',') }}</td>

                    <td class="border p-2 text-right">{{ number_format($r['egreso'],2,'.',',') }}</td>
                    <td class="border p-2 text-right font-semibold text-red-600">{{ number_format($r['utilidad'],2,'.',',') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="border p-4 text-center text-slate-500">
                        Sin datos para el período seleccionado.
                    </td>
                </tr>
            @endforelse

            @if(!empty($rows))
                <tr class="bg-[#CEE7FF] font-semibold">
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

    {{-- ======= RESUMEN ANUAL ======= --}}
    <div class="mt-10 overflow-auto">
        <h3 class="font-semibold mb-2 text-center">ESTADÍSTICA DE CAJA ANUAL – {{ $year }}</h3>

        <table class="min-w-[1200px] border text-xs">
            <thead class="bg-slate-800 text-white">
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
                <tr class="bg-[#CEE7FF] font-semibold">
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

                <tr class="bg-[#CEE7FF] font-semibold">
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
