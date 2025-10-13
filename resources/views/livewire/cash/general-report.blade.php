<div class="p-4 space-y-4">
    <h2 class="text-xl font-semibold text-center">REPORTE GENERAL</h2>

    <div class="flex flex-wrap items-center gap-2 justify-center">
        <div>
            <label class="text-sm">Mes</label>
            <select wire:model.live="month" class="border rounded px-2 py-1">
                @for($m=1;$m<=12;$m++)
                    <option value="{{ $m }}">{{ \Carbon\Carbon::create(null, $m, 1)->locale('es')->translatedFormat('F') }}</option>
                @endfor
            </select>
        </div>
        <div>
            <label class="text-sm">Año</label>
            <select wire:model.live="year" class="border rounded px-2 py-1">
                @for($y=now()->year-5;$y<=now()->year+1;$y++)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>
        </div>

        <div class="ml-4 text-sm px-3 py-1 bg-slate-100 rounded">
            <span class="font-medium">Saldo inicial del mes:</span>
            <span class="font-mono">{{ number_format($carryFromPrevMonth, 2) }}</span>
        </div>
    </div>

    <div class="overflow-x-auto border rounded">
        <table class="table table-bordered">
            <thead class="bg-primary" >
            <tr>
                <th class="px-2 py-2 w-14">ITEM</th>
                <th class="px-2 py-2 w-28">FECHA</th>
                <th class="px-2 py-2 w-72">DATOS CLIENTE</th>
                <th class="px-2 py-2">GLOSA</th>
                <th class="px-2 py-2 w-28 text-right">INGRESO</th>
                <th class="px-2 py-2 w-28 text-right">EGRESO</th>
            </tr>
            </thead>
            <tbody>
            @php $item=1; @endphp

            @foreach($rowsByDay as $day => $pack)
                @php
                    $rows = $pack['rows'];
                    $sumI = $pack['sum_ingreso'];
                    $sumE = $pack['sum_egreso'];
                    $saldo = $pack['saldo_final'];
                @endphp


                {{-- filas del día --}}
                @foreach($rows as $r)
                    <tr class="odd:bg-white even:bg-slate-50">
                        <td class="px-2 py-1 text-center">{{ $item++ }}</td>
                        <td class="px-2 py-1">{{ $r['date'] }}</td>
                        <td class="px-2 py-1"></td>
                        <td class="px-2 py-1">{{ $r['glosa'] }}</td>
                        <td class="px-2 py-1 text-right">{{ $r['ingreso'] ? number_format($r['ingreso'],2) : '0.00' }}</td>
                        <td class="px-2 py-1 text-right">{{ $r['egreso'] ? number_format($r['egreso'],2) : '0.00' }}</td>
                    </tr>
                @endforeach

                {{-- FOOT del día: SALDO FINAL–INICIAL --}}
                <tr class="bg-slate-200 font-semibold">
                    <td class="px-2 py-2 text-center">—</td>
                    <td class="px-2 py-2"></td>

                    {{-- Columna "DATOS CLIENTE" --}}
                    <td class="px-2 py-2">
                        SALDO <span class="text-red-600">FINAL–INICIAL</span>
                    </td>

                    {{-- Columna "GLOSA": aquí mostramos el saldo acumulado --}}
                    <td class="px-2 py-2">
                        <span class="font-medium">Saldo acumulado:</span>
                        <span class="font-mono">{{ number_format($saldo, 2) }}</span>
                    </td>

                    {{-- Totales del día --}}
                    <td class="px-2 py-2 text-right text-blue-700">{{ number_format($sumI, 2) }}</td>
                    <td class="px-2 py-2 text-right text-orange-600">{{ number_format($sumE, 2) }}</td>
                </tr>
            @endforeach
            </tbody>

            {{-- FOOTER del mes --}}
            <tfoot>
            <tr class="bg-slate-800 text-white font-semibold">
                <td class="px-2 py-2 text-center" colspan="4">TOTAL GENERAL</td>
                <td class="px-2 py-2 text-right">{{ number_format($totalIncomes,2) }}</td>
                <td class="px-2 py-2 text-right">{{ number_format($totalExpenses,2) }}</td>
            </tr>
            <tr class="bg-slate-200 font-bold">
                <td class="px-2 py-2" colspan="2">UTILIDAD</td>
                <td class="px-2 py-2" colspan="2"></td>
                <td class="px-2 py-2 text-right text-blue-700">
                    {{ number_format($finalBalance,2) }}
                </td>
                <td class="px-2 py-2"></td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>
