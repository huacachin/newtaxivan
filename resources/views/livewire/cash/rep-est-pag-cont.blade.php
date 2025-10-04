<div class="p-4">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold">
            REPORTE ESTADÍSTICO DE SALIDAS–PAGOS DE CONTROLADOR {{ $year }}
        </h2>

        <div class="ml-auto flex items-center gap-2">
            <label for="year" class="text-sm">Año</label>
            <select id="year" class="border rounded px-2 py-1" wire:model.live="year">
                @for ($y = 2015; $y <= 2030; $y++)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>

            <button class="bg-slate-700 text-white rounded px-3 py-2" wire:click="consultar">
                Consultar
            </button>
        </div>
    </div>

    <div class="mt-4 overflow-auto">
        <table class="min-w-full border text-sm">
            <thead class="bg-slate-800 text-white">
            <tr>
                <th class="p-2 border">CONTROL.</th>
                <th class="p-2 border" colspan="2">SEDE</th>
                @php
                    $months = ['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
                @endphp
                @foreach ($months as $m)
                    <th class="p-2 border">{{ $m }}</th>
                @endforeach
                <th class="p-2 border">TOTAL</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($this->rows as $ctrl)
                <tr>
                    <th class="border p-2 align-top" rowspan="{{ count($ctrl['paraderos']) + 3 }}">
                        {{ $ctrl['controlador'] }}
                    </th>
                @php $first = true; @endphp
                @foreach ($ctrl['paraderos'] as $p)
                    @if (!$first)
                        <tr>
                            @endif
                            <th class="border p-2">{{ $p['sucursal'] }}</th>
                            <th class="border p-2">Ingr. Sal.</th>
                            @for ($m=1; $m<=12; $m++)
                                <td class="border p-2 text-right">{{ number_format($p['ingresos_mes'][$m], 2, '.', ',') }}</td>
                            @endfor
                            <td class="border p-2 text-right font-semibold">{{ number_format($p['total'], 2, '.', ',') }}</td>
                        </tr>
                        @php $first = false; @endphp
                        @endforeach

                        {{-- Egreso Pago --}}
                        <tr>
                            <th class="border p-2" colspan="2">Egreso Pago</th>
                            @for ($m=1; $m<=12; $m++)
                                <td class="border p-2 text-right text-red-600">
                                    {{ number_format($ctrl['egreso_pago'][$m], 2, '.', ',') }}
                                </td>
                            @endfor
                            <td class="border p-2 text-right text-red-600 font-semibold">
                                {{ number_format($ctrl['tot_egr_pago'], 2, '.', ',') }}
                            </td>
                        </tr>

                        {{-- Egreso Draco --}}
                        <tr>
                            <th class="border p-2" colspan="2">Egreso Draco</th>
                            @for ($m=1; $m<=12; $m++)
                                <td class="border p-2 text-right">
                                    {{ number_format($ctrl['egreso_draco'][$m], 2, '.', ',') }}
                                </td>
                            @endfor
                            <td class="border p-2 text-right font-semibold">
                                {{ number_format($ctrl['tot_egr_draco'], 2, '.', ',') }}
                            </td>
                        </tr>

                        {{-- Saldo --}}
                        <tr>
                            <th class="border p-2" colspan="2">Saldo</th>
                            @for ($m=1; $m<=12; $m++)
                                <td class="border p-2 text-right" id="saldo">
                                    {{ number_format($ctrl['saldos'][$m], 2, '.', ',') }}
                                </td>
                            @endfor
                            <td class="border p-2 text-right font-semibold">
                                {{ number_format($ctrl['tot_saldo'], 2, '.', ',') }}
                            </td>
                        </tr>
                        @empty
                            <tr>
                                <td colspan="16" class="border p-4 text-center text-slate-500">
                                    Sin datos para {{ $year }}.
                                </td>
                            </tr>
                        @endforelse

                        @if (!empty($this->rows))
                            <tr class="bg-slate-100 font-semibold">
                                <td class="border p-2" colspan="3">SALDO A FAVOR</td>
                                @for ($m=1; $m<=12; $m++)
                                    <td class="border p-2 text-right">
                                        {{ number_format($this->totalesSaldoMes[$m] ?? 0, 2, '.', ',') }}
                                    </td>
                                @endfor
                                <td class="border p-2 text-right">
                                    {{ number_format($this->totalSaldoFavor, 2, '.', ',') }}
                                </td>
                            </tr>
                        @endif
            </tbody>
        </table>
    </div>

    {{-- Comparativo Luis vs Elmer --}}
    <div class="mt-10 overflow-auto">
        <table class="min-w-[900px] border text-sm">
            <thead class="bg-slate-800 text-white">
            <tr>
                <th rowspan="4" class="p-2 border">Item</th>
                <th rowspan="4" class="p-2 border">Mes</th>
                <th class="p-2 border">Luis</th>
                <th class="p-2 border">Elmer</th>
                <th rowspan="4" class="p-2 border">Diferencia</th>
                <th class="p-2 border">Luis</th>
                <th class="p-2 border">Elmer</th>
                <th rowspan="4" class="p-2 border">Diferencia</th>
                <th rowspan="4" class="p-2 border">Diferencia Huaycan / La victoria</th>
            </tr>
            <tr>
                <th class="p-2 border">Huaycan</th>
                <th class="p-2 border">Huaycan</th>
                <th class="p-2 border">La victoria</th>
                <th class="p-2 border">La victoria</th>
            </tr>
            <tr>
                <th class="p-2 border">Ingreso</th>
                <th class="p-2 border">Ingreso</th>
                <th class="p-2 border">Ingreso</th>
                <th class="p-2 border">Ingreso</th>
            </tr>
            <tr>
                <th class="p-2 border">Salida</th>
                <th class="p-2 border">Salida</th>
                <th class="p-2 border">Salida</th>
                <th class="p-2 border">Salida</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($this->comparativo as $r)
                <tr>
                    <td class="border p-2"><b>{{ $r['item'] }}</b></td>
                    <td class="border p-2"><b>{{ $r['mes'] }}</b></td>

                    <td class="border p-2 text-right">{{ number_format($r['a_h'], 2, '.', ',') }}</td>
                    <td class="border p-2 text-right">{{ number_format($r['b_h'], 2, '.', ',') }}</td>
                    <td class="border p-2 text-right text-red-600">{{ number_format($r['dif_h'], 2, '.', ',') }}</td>

                    <td class="border p-2 text-right">{{ number_format($r['a_v'], 2, '.', ',') }}</td>
                    <td class="border p-2 text-right">{{ number_format($r['b_v'], 2, '.', ',') }}</td>
                    <td class="border p-2 text-right text-red-600">{{ number_format($r['dif_v'], 2, '.', ',') }}</td>

                    <td class="border p-2 text-right">{{ number_format($r['dif_h_vs_v'], 2, '.', ',') }}</td>
                </tr>
            @endforeach

            @if (!empty($this->comparativo))
                <tr class="bg-slate-100 font-semibold">
                    <td class="border p-2" colspan="2">Total</td>
                    <td class="border p-2 text-right">{{ number_format($this->comparativoTotales['a_h'], 2, '.', ',') }}</td>
                    <td class="border p-2 text-right">{{ number_format($this->comparativoTotales['b_h'], 2, '.', ',') }}</td>
                    <td class="border p-2 text-right text-red-600">{{ number_format($this->comparativoTotales['dif_h'], 2, '.', ',') }}</td>

                    <td class="border p-2 text-right">{{ number_format($this->comparativoTotales['a_v'], 2, '.', ',') }}</td>
                    <td class="border p-2 text-right">{{ number_format($this->comparativoTotales['b_v'], 2, '.', ',') }}</td>
                    <td class="border p-2 text-right text-red-600">{{ number_format($this->comparativoTotales['dif_v'], 2, '.', ',') }}</td>

                    <td class="border p-2 text-right">{{ number_format($this->comparativoTotales['dif_h_vs_v'], 2, '.', ',') }}</td>
                </tr>
            @endif
            </tbody>
        </table>
    </div>
</div>
