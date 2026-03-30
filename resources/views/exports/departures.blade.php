{{-- resources/views/exports/departures.blade.php --}}
@php
    $groupMode = (bool) ($filters['groupMode'] ?? false);
    $ds  = 'border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;';
    $dsr = 'border-style:dotted solid dotted solid;text-align:right;vertical-align:middle;';
@endphp
<html>
<head><meta charset="UTF-8"></head>
<body topmargin="0" leftmargin="0" rightmargin="0" bottommargin="0">

<table cellspacing="0" border="1" style="border-collapse:collapse;">

    {{-- Título --}}
    <tr>
        <td colspan="13" align="center" style="font-weight:bold;color:red;font-size:11pt;">
            LISTADO GENERAL DE SALIDA
        </td>
    </tr>

    {{-- Header fila 1 --}}
    <tr>
        <th bgcolor="#2874A6" align="center" style="color:white;" rowspan="2"><b>N°</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;" rowspan="2"><b>Placa</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;" rowspan="2"><b>Fecha</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;" colspan="2"><b>Hora</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;" rowspan="2"><b>Sucursal</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;" rowspan="2"><b>Usuario</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;" colspan="3"><b>Empresa</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;" colspan="3"><b>Vehiculo</b></th>
    </tr>

    {{-- Header fila 2 --}}
    <tr>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Sal.</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Frec.</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Salida</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>T. S</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>S/</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>P.</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>P.J</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>S/</b></th>
    </tr>

    {{-- Datos sección 1 --}}
    @forelse($rows as $d)
        @php
            $timesVal   = (int)   ($d->times       ?? 0);
            $priceVal   = (float) ($d->price       ?? 0);
            $passengers = (int)   ($d->passenger   ?? 0);
            $passage    = (float) ($d->passage     ?? 0);
            $totPasaje  = (float) ($d->total_pasaje ?? 0);
        @endphp
        <tr>
            <td style="{{ $ds }}">{{ $loop->iteration }}</td>
            <td style="{{ $ds }}">{{ $d->plate ?? '-' }}</td>
            <td style="{{ $ds }}">{{ $groupMode ? '-' : (\Illuminate\Support\Str::of($d->date ?? '')->substr(0,10) ?: '-') }}</td>
            <td style="{{ $ds }}">{{ $groupMode ? '-' : ($d->hour ?? '-') }}</td>
            <td style="{{ $ds }}">{{ $groupMode ? '-' : ($d->freq ?? '0:00:00') }}</td>
            <td style="{{ $ds }}">{{ $d->headquarter_name ?? '-' }}</td>
            <td style="{{ $ds }}">{{ $d->user_name ?? '-' }}</td>
            <td style="{{ $ds }}">{{ number_format($timesVal) }}</td>
            <td style="{{ $ds }}">{{ number_format($timesVal) }}</td>
            <td style="{{ $ds }}">{{ number_format($priceVal, 2) }}</td>
            <td style="{{ $ds }}">{{ number_format($passengers) }}</td>
            <td style="{{ $ds }}">{{ number_format($passage, 2) }}</td>
            <td style="{{ $ds }}">{{ number_format($totPasaje, 2) }}</td>
        </tr>
    @empty
        <tr><td colspan="13" style="{{ $ds }}">Sin datos</td></tr>
    @endforelse

    {{-- Total sección 1 --}}
    <tr style="background:#CEE7FF;">
        <th></th><th></th><th></th><th></th><th></th><th></th>
        <th><b>TOTAL</b></th>
        <th>{{ number_format((int)($totals->times_total ?? 0)) }}</th>
        <th>{{ number_format((int)($totals->times_total ?? 0)) }}</th>
        <th>{{ number_format((float)($totals->price_total ?? 0), 2) }}</th>
        <th>{{ number_format((int)($totals->passengers_total ?? 0)) }}</th>
        <th>{{ number_format((float)($totals->passage_total ?? 0), 2) }}</th>
        <th>{{ number_format((float)($totals->total_pasaje_total ?? 0), 2) }}</th>
    </tr>

    {{-- Título Apoyo --}}
    <tr>
        <td colspan="13" align="center" style="font-weight:bold;color:red;font-size:11pt;">V. APOYO</td>
    </tr>

    {{-- Header Apoyo fila 1 --}}
    <tr>
        <th bgcolor="#2874A6" align="center" style="color:white;" rowspan="2"><b>N°</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;" rowspan="2"><b>Placa</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;" rowspan="2"><b>Fecha</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;" colspan="2"><b>Hora</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;" rowspan="2"><b>Sucursal</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;" rowspan="2"><b>Usuario</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;" colspan="3"><b>Empresa</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;" colspan="3"><b>Vehiculo</b></th>
    </tr>

    {{-- Header Apoyo fila 2 --}}
    <tr>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Sal.</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Frec.</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Salida</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>T. S</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>S/</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>P.</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>PJ</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>S/</b></th>
    </tr>

    {{-- Datos Apoyo (rojo) --}}
    @forelse($supportRows as $d)
        @php
            $timesVal   = (int)   ($d->times       ?? 0);
            $priceVal   = (float) ($d->price       ?? 0);
            $passengers = (int)   ($d->passenger   ?? 0);
            $passage    = (float) ($d->passage     ?? 0);
            $totPasaje  = (float) ($d->total_pasaje ?? 0);
        @endphp
        <tr style="color:red;">
            <td style="{{ $ds }}">{{ $loop->iteration }}</td>
            <td style="{{ $ds }}">{{ $d->plate ?? '-' }}</td>
            <td style="{{ $ds }}">{{ $groupMode ? '-' : (\Illuminate\Support\Str::of($d->date ?? '')->substr(0,10) ?: '-') }}</td>
            <td style="{{ $ds }}">{{ $groupMode ? '-' : ($d->hour ?? '-') }}</td>
            <td style="{{ $ds }}">{{ $groupMode ? '-' : ($d->freq ?? '0:00:00') }}</td>
            <td style="{{ $ds }}">{{ $d->headquarter_name ?? '-' }}</td>
            <td style="{{ $ds }}">{{ $d->user_name ?? '-' }}</td>
            <td style="{{ $ds }}">{{ number_format($timesVal) }}</td>
            <td style="{{ $ds }}">{{ number_format($timesVal) }}</td>
            <td style="{{ $ds }}">{{ number_format($priceVal, 2) }}</td>
            <td style="{{ $ds }}">{{ number_format($passengers) }}</td>
            <td style="{{ $ds }}">{{ number_format($passage, 2) }}</td>
            <td style="{{ $ds }}">{{ number_format($totPasaje, 2) }}</td>
        </tr>
    @empty
        <tr style="color:red;"><td colspan="13" style="{{ $ds }}">Sin datos</td></tr>
    @endforelse

    {{-- Total Apoyo --}}
    <tr style="background:#CEE7FF;">
        <th></th><th></th><th></th><th></th><th></th><th></th>
        <th><b>TOTAL</b></th>
        <th>{{ number_format((int)($supTotals->times_total ?? 0)) }}</th>
        <th>{{ number_format((int)($supTotals->times_total ?? 0)) }}</th>
        <th>{{ number_format((float)($supTotals->price_total ?? 0), 2) }}</th>
        <th>{{ number_format((int)($supTotals->passengers_total ?? 0)) }}</th>
        <th>{{ number_format((float)($supTotals->passage_total ?? 0), 2) }}</th>
        <th>{{ number_format((float)($supTotals->total_pasaje_total ?? 0), 2) }}</th>
    </tr>

    {{-- Total General --}}
    <tr style="background:#CEE7FF;">
        <th></th><th></th><th></th><th></th><th></th><th></th>
        <th><b>TOTAL General</b></th>
        <th>{{ number_format((int)($grand->times_total ?? 0)) }}</th>
        <th>{{ number_format((int)($grand->times_total ?? 0)) }}</th>
        <th>{{ number_format((float)($grand->price_total ?? 0), 2) }}</th>
        <th>{{ number_format((int)($grand->passengers_total ?? 0)) }}</th>
        <th>{{ number_format((float)($grand->passage_total ?? 0), 2) }}</th>
        <th>{{ number_format((float)($grand->total_pasaje_total ?? 0), 2) }}</th>
    </tr>

</table>
</body>
</html>
