@php
    $ds  = 'border:1px dotted #808080;border-left:1px solid #000;border-right:1px solid #000;text-align:center;vertical-align:middle;font-size:9pt;';
    $hdr = 'background:#2874A6;color:white;font-weight:bold;text-align:center;vertical-align:middle;font-size:9pt;border:1px solid #000;';
    $sun = 'background:#FF0000;color:white;font-weight:bold;text-align:center;vertical-align:middle;font-size:9pt;border:1px solid #000;';
    $ftr = 'background:#CEE7FF;font-weight:bold;text-align:center;vertical-align:middle;font-size:9pt;border:1px solid #000;';
    $ttl = 'font-weight:bold;color:red;text-align:center;vertical-align:middle;font-size:10pt;border:1px solid #000;';
    $totalCols = 3 + $daysInMonth + 1;
    $totalColsHQ = 2 + $daysInMonth + 1;

    $colDefs = '<col width="25"><col width="25"><col width="55">';
    $colDefsHQ = '<col width="25"><col width="55">';
    for ($x = 1; $x <= $daysInMonth; $x++) { $colDefs .= '<col width="25">'; $colDefsHQ .= '<col width="25">'; }
    $colDefs .= '<col width="35">';
    $colDefsHQ .= '<col width="35">';
@endphp
<html>
<head><meta charset="UTF-8"></head>
<body topmargin="0" leftmargin="0" rightmargin="0" bottommargin="0">

{{-- ===== TABLA GENERAL ===== --}}
<table cellspacing="0" border="1" style="border-collapse:collapse;">
    {!! $colDefs !!}
    <tr>
        <td colspan="{{ $totalCols }}" style="{{ $ttl }}">
            REPORTE SALIDA MENSUAL POR PLACA - V.T {{ mb_strtoupper($monthName) }} {{ $year }}
        </td>
    </tr>
    <tr>
        <th style="{{ $hdr }}">Nº</th>
        <th style="{{ $hdr }}">COD</th>
        <th style="{{ $hdr }}">Placa</th>
        @for($d = 1; $d <= $daysInMonth; $d++)
            <th style="{{ $sundays[$d] ? $sun : $hdr }}">{{ $d }}</th>
        @endfor
        <th style="{{ $hdr }}">T. Salida</th>
    </tr>

    @php $i = 0; @endphp
    @foreach($rows as $row)
        @php $i++; @endphp
        <tr>
            <td style="{{ $ds }}">{{ $i }}</td>
            <td style="{{ $ds }}">{{ $row['sort_order'] ?? '' }}</td>
            <td style="{{ $ds }}">{{ $row['plate'] }}</td>
            @for($d = 1; $d <= $daysInMonth; $d++)
                <td style="{{ $ds }}">{{ $row['daily'][$d] ?? 0 }}</td>
            @endfor
            <td style="{{ $ds }}font-weight:bold;">{{ $row['total'] }}</td>
        </tr>
    @endforeach

    <tr>
        <td colspan="3" style="{{ $ftr }}">Total Vueltas</td>
        @for($d = 1; $d <= $daysInMonth; $d++)
            <td style="{{ $ftr }}">{{ $totalPerDay[$d] ?? 0 }}</td>
        @endfor
        <td style="{{ $ftr }}">{{ array_sum($totalPerDay) }}</td>
    </tr>
    <tr>
        <td colspan="3" style="{{ $ftr }}">Total V.T.</td>
        @for($d = 1; $d <= $daysInMonth; $d++)
            <td style="{{ $ftr }}">{{ $vehiclesWorkedPerDay[$d] ?? 0 }}</td>
        @endfor
        <td style="{{ $ftr }}">{{ array_sum($vehiclesWorkedPerDay) }}</td>
    </tr>
</table>

<br>

{{-- ===== TABLAS POR SEDE ===== --}}
@foreach($hqTables as $hqId => $hq)
<table cellspacing="0" border="1" style="border-collapse:collapse;">
    {!! $colDefsHQ !!}
    <tr>
        <td colspan="{{ $totalColsHQ }}" style="{{ $ttl }}">
            {{ mb_strtoupper($hq['name']) }} - V.T {{ mb_strtoupper($monthName) }} {{ $year }}
        </td>
    </tr>
    <tr>
        <th style="{{ $hdr }}">Nº</th>
        <th style="{{ $hdr }}">Placa</th>
        @for($d = 1; $d <= $daysInMonth; $d++)
            <th style="{{ $sundays[$d] ? $sun : $hdr }}">{{ $d }}</th>
        @endfor
        <th style="{{ $hdr }}">T. Salida</th>
    </tr>

    @php $hi = 0; @endphp
    @foreach($hq['rows'] as $row)
        @php $hi++; @endphp
        <tr>
            <td style="{{ $ds }}">{{ $hi }}</td>
            <td style="{{ $ds }}">{{ $row['plate'] }}</td>
            @for($d = 1; $d <= $daysInMonth; $d++)
                <td style="{{ $ds }}">{{ $row['daily'][$d] ?? 0 }}</td>
            @endfor
            <td style="{{ $ds }}font-weight:bold;">{{ $row['total'] }}</td>
        </tr>
    @endforeach

    <tr>
        <td colspan="2" style="{{ $ftr }}">Total Vueltas</td>
        @for($d = 1; $d <= $daysInMonth; $d++)
            <td style="{{ $ftr }}">{{ $hq['totalPerDay'][$d] ?? 0 }}</td>
        @endfor
        <td style="{{ $ftr }}">{{ array_sum($hq['totalPerDay']) }}</td>
    </tr>
    <tr>
        <td colspan="2" style="{{ $ftr }}">Total V.T.</td>
        @for($d = 1; $d <= $daysInMonth; $d++)
            <td style="{{ $ftr }}">{{ $hq['vtPerDay'][$d] ?? 0 }}</td>
        @endfor
        <td style="{{ $ftr }}">{{ array_sum($hq['vtPerDay']) }}</td>
    </tr>
</table>

<br>
@endforeach

{{-- ===== RESUMEN POR SEDE ===== --}}
@if(count($hqSummary) > 0)
<table cellspacing="0" border="1" style="border-collapse:collapse;">
    <col width="120">
    <col width="80">
    <col width="80">
    <tr>
        <td colspan="3" style="{{ $ttl }}">
            RESUMEN POR SEDE - {{ mb_strtoupper($monthName) }} {{ $year }}
        </td>
    </tr>
    <tr>
        <th style="{{ $hdr }}">Sede</th>
        <th style="{{ $hdr }}">Total Vueltas</th>
        <th style="{{ $hdr }}">Total V.T</th>
    </tr>
    @foreach($hqSummary as $s)
        <tr>
            <td style="{{ $ds }}text-align:left;">{{ $s['name'] }}</td>
            <td style="{{ $ds }}">{{ $s['totalVueltas'] }}</td>
            <td style="{{ $ds }}">{{ $s['totalVT'] }}</td>
        </tr>
    @endforeach
    <tr>
        <td style="{{ $ftr }}">TOTAL GENERAL</td>
        <td style="{{ $ftr }}">{{ $grandTotalVueltas }}</td>
        <td style="{{ $ftr }}">{{ $grandTotalVT }}</td>
    </tr>
</table>
@endif

</body>
</html>
