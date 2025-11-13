{{-- resources/views/exports/departures.blade.php --}}
@php
    $range = ($filters['fromDate'] ?: '—') . ' a ' . ($filters['toDate'] ?: '—');
    $label = match((int)($filters['searchType'] ?? 1)) { 1 => 'Placa', 2 => 'Usuario', 3 => 'Sucursal', default => 'Búsqueda' };
    $extra = trim((string)($filters['searchText'] ?? ''));
    $groupMode = (bool) ($filters['groupMode'] ?? false);
@endphp

<table border="1" style="border-collapse: collapse;">
    {{-- Fila 1: Título + rango (mismo renglón) --}}
    <tr>
        <td colspan="13" align="center"
            style="font-weight:bold;color:#F80000;">
            LISTADO GENERAL DE SALIDAS
        </td>
    </tr>

    {{-- ================== SECCIÓN 1: VEHÍCULOS REGISTRADOS ================== --}}

    {{-- Encabezado 1 --}}
    <tr style="background-color:#2874A6;color:#FFFFFF;font-weight:bold;">
        <td rowspan="2" align="center"><strong>N°</strong></td>
        <td rowspan="2" align="center"><strong>Placa</strong></td>
        <td rowspan="2" align="center"><strong>Fecha</strong></td>

        <td colspan="2" align="center"><strong>Hora</strong></td>

        <td rowspan="2" align="center"><strong>Sucursal</strong></td>
        <td rowspan="2" align="center"><strong>Usuario</strong></td>

        <td colspan="3" align="center"><strong>Empresa</strong></td>
        <td colspan="3" align="center"><strong>Vehículo</strong></td>
    </tr>

    {{-- Encabezado 2 --}}
    <tr style="background-color:#2874A6;color:#FFFFFF;font-weight:bold;">
        <td align="center"><strong>Sal.</strong></td>
        <td align="center"><strong>Frec.</strong></td>

        <td align="center"><strong>Salida</strong></td>
        <td align="center"><strong>T. S</strong></td>
        <td align="center"><strong>S/</strong></td>

        <td align="center"><strong>P.</strong></td>
        <td align="center"><strong>PJ</strong></td>
        <td align="center"><strong>S/</strong></td>
    </tr>

    {{-- Cuerpo sección 1 --}}
    @forelse($rows as $i => $d)
        <tr>
            <td align="center">{{ $loop->iteration }}</td>
            <td>{{ $d->plate ?? '-' }}</td>

            <td align="center">
                @if($groupMode)
                    -
                @else
                    {{ \Illuminate\Support\Str::of($d->date ?? '')->substr(0,10) ?: '-' }}
                @endif
            </td>

            <td align="center">
                @if($groupMode) - @else {{ $d->hour ?? '-' }} @endif
            </td>

            <td align="center">
                @if($groupMode) - @else {{ $d->freq ?? '0:00:00' }} @endif
            </td>

            <td align="center">{{ $d->headquarter_name ?? '-' }}</td>
            <td align="center">{{ $d->user_name ?? '-' }}</td>

            @php
                $timesVal   = (int) ($d->times ?? 0);
                $priceVal   = (float) ($d->price ?? 0);
                $passengers = (int) ($d->passenger ?? 0);
                $passage    = (float) ($d->passage ?? 0);
                $totPasaje  = (float) ($d->total_pasaje ?? 0);
            @endphp

            <td align="right">{{ number_format($timesVal) }}</td>
            <td align="right">{{ number_format($timesVal) }}</td>
            <td align="right">{{ number_format($priceVal, 2) }}</td>

            <td align="right">{{ number_format($passengers) }}</td>
            <td align="right">{{ number_format($passage, 2) }}</td>
            <td align="right">{{ number_format($totPasaje, 2) }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="13" align="center">Sin datos</td>
        </tr>
    @endforelse

    {{-- TFOOT sección 1 --}}
    <tr style="background-color:#CEE7FF;font-weight:bold;">
        <td colspan="7" align="right"><strong>TOTAL</strong></td>

        <td align="right"><strong>{{ number_format((int) ($totals->times_total ?? 0)) }}</strong></td>
        <td align="right"><strong>{{ number_format((int) ($totals->times_total ?? 0)) }}</strong></td>
        <td align="right"><strong>{{ number_format((float) ($totals->price_total ?? 0), 2) }}</strong></td>

        <td align="right"><strong>{{ number_format((int) ($totals->passengers_total ?? 0)) }}</strong></td>
        <td align="right"><strong>{{ number_format((float) ($totals->passage_total ?? 0), 2) }}</strong></td>
        <td align="right"><strong>{{ number_format((float) ($totals->total_pasaje_total ?? 0), 2) }}</strong></td>
    </tr>

    {{-- ================== SECCIÓN 2: V.APOYO (SIN FILA VACÍA ANTES) ================== --}}
    <tr>
        <td colspan="13" align="center"
            style="font-weight:bold;color:#F80000;">
            V.APOYO
        </td>
    </tr>

    {{-- Encabezado 1 --}}
    <tr style="background-color:#2874A6;color:#FFFFFF;font-weight:bold;">
        <td rowspan="2" align="center"><strong>N°</strong></td>
        <td rowspan="2" align="center"><strong>Placa</strong></td>
        <td rowspan="2" align="center"><strong>Fecha</strong></td>

        <td colspan="2" align="center"><strong>Hora</strong></td>

        <td rowspan="2" align="center"><strong>Sucursal</strong></td>
        <td rowspan="2" align="center"><strong>Usuario</strong></td>

        <td colspan="3" align="center"><strong>Empresa</strong></td>
        <td colspan="3" align="center"><strong>Vehículo</strong></td>
    </tr>

    {{-- Encabezado 2 --}}
    <tr style="background-color:#2874A6;color:#FFFFFF;font-weight:bold;">
        <td align="center"><strong>Sal.</strong></td>
        <td align="center"><strong>Frec.</strong></td>

        <td align="center"><strong>Salida</strong></td>
        <td align="center"><strong>T. S</strong></td>
        <td align="center"><strong>S/</strong></td>

        <td align="center"><strong>P.</strong></td>
        <td align="center"><strong>PJ</strong></td>
        <td align="center"><strong>S/</strong></td>
    </tr>

    {{-- Cuerpo sección 2 (en rojo) --}}
    @forelse($supportRows as $i => $d)
        <tr style="color:#CC0000;">
            <td align="center">{{ $loop->iteration }}</td>
            <td>{{ $d->plate ?? '-' }}</td>

            <td align="center">
                @if($groupMode)
                    -
                @else
                    {{ \Illuminate\Support\Str::of($d->date ?? '')->substr(0,10) ?: '-' }}
                @endif
            </td>

            <td align="center">@if($groupMode) - @else {{ $d->hour ?? '-' }} @endif</td>
            <td align="center">@if($groupMode) - @else {{ $d->freq ?? '0:00:00' }} @endif</td>

            <td align="center">{{ $d->headquarter_name ?? '-' }}</td>
            <td align="center">{{ $d->user_name ?? '-' }}</td>

            @php
                $timesVal   = (int) ($d->times ?? 0);
                $priceVal   = (float) ($d->price ?? 0);
                $passengers = (int) ($d->passenger ?? 0);
                $passage    = (float) ($d->passage ?? 0);
                $totPasaje  = (float) ($d->total_pasaje ?? 0);
            @endphp

            <td align="right">{{ number_format($timesVal) }}</td>
            <td align="right">{{ number_format($timesVal) }}</td>
            <td align="right">{{ number_format($priceVal, 2) }}</td>

            <td align="right">{{ number_format($passengers) }}</td>
            <td align="right">{{ number_format($passage, 2) }}</td>
            <td align="right">{{ number_format($totPasaje, 2) }}</td>
        </tr>
    @empty
        <tr style="color:#CC0000;">
            <td colspan="13" align="center">Sin datos</td>
        </tr>
    @endforelse

    {{-- TFOOT sección 2 --}}
    <tr style="background-color:#CEE7FF;font-weight:bold;">
        <td colspan="7" align="right"><strong>TOTAL</strong></td>

        <td align="right"><strong>{{ number_format((int) ($supTotals->times_total ?? 0)) }}</strong></td>
        <td align="right"><strong>{{ number_format((int) ($supTotals->times_total ?? 0)) }}</strong></td>
        <td align="right"><strong>{{ number_format((float) ($supTotals->price_total ?? 0), 2) }}</strong></td>

        <td align="right"><strong>{{ number_format((int) ($supTotals->passengers_total ?? 0)) }}</strong></td>
        <td align="right"><strong>{{ number_format((float) ($supTotals->passage_total ?? 0), 2) }}</strong></td>
        <td align="right"><strong>{{ number_format((float) ($supTotals->total_pasaje_total ?? 0), 2) }}</strong></td>
    </tr>

    {{-- ================== TOTAL GENERAL (PEGADO) ================== --}}
    <tr style="background-color:#CEE7FF;font-weight:bold;">
        <td colspan="7" align="right"><strong>TOTAL GENERAL</strong></td>

        <td align="right"><strong>{{ number_format((int) ($grand->times_total ?? 0)) }}</strong></td>
        <td align="right"><strong>{{ number_format((int) ($grand->times_total ?? 0)) }}</strong></td>
        <td align="right"><strong>{{ number_format((float) ($grand->price_total ?? 0), 2) }}</strong></td>

        <td align="right"><strong>{{ number_format((int) ($grand->passengers_total ?? 0)) }}</strong></td>
        <td align="right"><strong>{{ number_format((float) ($grand->passage_total ?? 0), 2) }}</strong></td>
        <td align="right"><strong>{{ number_format((float) ($grand->total_pasaje_total ?? 0), 2) }}</strong></td>
    </tr>
</table>
