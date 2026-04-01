@php
    $ds   = 'border:1px dotted #808080;border-left:1px solid #000;border-right:1px solid #000;text-align:center;vertical-align:middle;font-size:9pt;white-space:nowrap;';
    $dsMoney = 'border:1px dotted #808080;border-left:1px solid #000;border-right:1px solid #000;text-align:center;vertical-align:middle;font-size:9pt;white-space:nowrap;color:#F80000;';
    $hdr  = 'background:#2874A6;color:white;font-weight:bold;text-align:center;vertical-align:middle;font-size:9pt;border:1px solid #000;white-space:nowrap;';
    $sun  = 'background:#FF0000;color:white;font-weight:bold;text-align:center;vertical-align:middle;font-size:9pt;border:1px solid #000;white-space:nowrap;';
    $ftr  = 'background:#CEE7FF;font-weight:bold;text-align:center;vertical-align:middle;font-size:9pt;border:1px solid #000;white-space:nowrap;';
    $ftrM = 'background:#CEE7FF;font-weight:bold;text-align:center;vertical-align:middle;font-size:9pt;border:1px solid #000;white-space:nowrap;color:#F80000;';
    $ttl  = 'font-weight:bold;color:red;text-align:center;vertical-align:middle;font-size:10pt;border:1px solid #000;white-space:nowrap;';
    $totalCols = 3 + $daysInMonth + 2;

    // Calcular rowspans
    $controllerSpans = [];
    $stopSpans = [];
    $rowCount = count($rows);
    $i = 0;
    while ($i < $rowCount) {
        $ctrl = $rows[$i]['controller'];
        $ctrlStart = $i;
        $j = $i + 1;
        while ($j < $rowCount && $rows[$j]['controller'] === $ctrl) $j++;
        $controllerSpans[$i] = $j - $ctrlStart;
        for ($k = $ctrlStart + 1; $k < $j; $k++) $controllerSpans[$k] = 0;

        $s = $ctrlStart;
        while ($s < $j) {
            $stop = $rows[$s]['stop'];
            $t = $s + 1;
            while ($t < $j && $rows[$t]['stop'] === $stop) $t++;
            $stopSpans[$s] = $t - $s;
            for ($k = $s + 1; $k < $t; $k++) $stopSpans[$k] = 0;
            $s = $t;
        }
        $i = $j;
    }

    $fmt = function($val) {
        $val = (float)$val;
        if (abs($val) < 0.0001) return '';
        if (fmod($val, 1.0) == 0.0) return number_format($val, 0, '.', '');
        $str = number_format($val, 2, '.', '');
        return rtrim(rtrim($str, '0'), '.');
    };
@endphp
<html>
<head><meta charset="UTF-8"></head>
<body topmargin="0" leftmargin="0" rightmargin="0" bottommargin="0">

<table cellspacing="0" border="1" style="border-collapse:collapse;">
    <col width="75">
    <col width="65">
    <col width="40">
    @for($d = 1; $d <= $daysInMonth; $d++)
        <col width="28">
    @endfor
    <col width="35">
    <col width="40">

    <tr>
        <td colspan="{{ $totalCols }}" style="{{ $ttl }}">
            REPORTE ESTADISTICO DE SALIDAS {{ mb_strtoupper($monthName) }} DEL {{ $year }}
        </td>
    </tr>
    <tr>
        <th style="{{ $hdr }}">CONTROLADOR</th>
        <th colspan="2" style="{{ $hdr }}">PARADERO</th>
        @for($d = 1; $d <= $daysInMonth; $d++)
            <th style="{{ $sundays[$d] ? $sun : $hdr }}">{{ $d }}</th>
        @endfor
        <th style="{{ $hdr }}">SALIDAS</th>
        <th style="{{ $hdr }}">S/</th>
    </tr>

    @foreach($rows as $idx => $r)
        <tr>
            @if(($controllerSpans[$idx] ?? 0) > 0)
                <td rowspan="{{ $controllerSpans[$idx] }}" style="{{ $hdr }}">{{ $r['controller'] }}</td>
            @endif
            @if(($stopSpans[$idx] ?? 0) > 0)
                <td rowspan="{{ $stopSpans[$idx] }}" style="{{ $hdr }}">{{ $r['stop'] }}</td>
            @endif
            <td style="{{ $hdr }}">{{ $r['type'] }}</td>
            @for($d = 1; $d <= $daysInMonth; $d++)
                @php $val = $r['days'][$d] ?? 0; @endphp
                @if($r['type'] === 'S/')
                    <td style="{{ $dsMoney }}">{{ $val != 0 ? $fmt($val) : '' }}</td>
                @else
                    <td style="{{ $ds }}">{{ $val != 0 ? (int)$val : '' }}</td>
                @endif
            @endfor
            <td style="{{ $ds }}">{{ $r['total_sal'] !== null && $r['total_sal'] > 0 ? number_format($r['total_sal']) : '' }}</td>
            <td style="{{ $dsMoney }}">{{ $r['total_soles'] !== null && $r['total_soles'] > 0 ? $fmt($r['total_soles']) : '' }}</td>
        </tr>
    @endforeach

    {{-- Totales --}}
    <tr>
        <td colspan="2" rowspan="2" style="{{ $hdr }}">TOTAL GENERAL</td>
        <td style="{{ $ftr }}">Salidas</td>
        @for($d = 1; $d <= $daysInMonth; $d++)
            <td style="{{ $ftr }}">{{ ($totalsSalidas[$d] ?? 0) != 0 ? (int)$totalsSalidas[$d] : '' }}</td>
        @endfor
        <td rowspan="2" style="{{ $ftr }}">{{ number_format($grandSalidas) }}</td>
        <td rowspan="2" style="{{ $ftr }}">{{ $fmt($grandMonto) }}</td>
    </tr>
    <tr>
        <td style="{{ $ftrM }}">S/</td>
        @for($d = 1; $d <= $daysInMonth; $d++)
            <td style="{{ $ftrM }}">{{ ($totalsMonto[$d] ?? 0) != 0 ? $fmt($totalsMonto[$d]) : '' }}</td>
        @endfor
    </tr>
</table>

</body>
</html>
