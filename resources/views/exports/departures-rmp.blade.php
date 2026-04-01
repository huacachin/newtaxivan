@php
    $ds  = 'border:1px dotted #808080;border-left:1px solid #000;border-right:1px solid #000;text-align:center;vertical-align:middle;font-size:10pt;';
    $hdr = 'background:#2874A6;color:white;font-weight:bold;text-align:center;vertical-align:middle;font-size:10pt;border:1px solid #000;';
    $sun = 'background:#FF0000;color:white;font-weight:bold;text-align:center;vertical-align:middle;font-size:10pt;border:1px solid #000;';
    $ftr = 'background:#CEE7FF;font-weight:bold;text-align:center;vertical-align:middle;font-size:10pt;border:1px solid #000;';
    $ttl = 'font-weight:bold;color:red;text-align:center;vertical-align:middle;font-size:11pt;border:1px solid #000;';
    $totalCols = 3 + $daysInMonth + 1;
@endphp
<html>
<head><meta charset="UTF-8"></head>
<body topmargin="0" leftmargin="0" rightmargin="0" bottommargin="0">

<table cellspacing="0" border="1" style="border-collapse:collapse;">
    <tr>
        <td colspan="{{ $totalCols }}" style="{{ $ttl }}">
            REPORTE MENSUAL POR PARADERO V.T. {{ mb_strtoupper($monthName) }} {{ $year }}
        </td>
    </tr>
    <tr>
        <th style="{{ $hdr }}width:10;">CONTROLADOR</th>
        <th style="{{ $hdr }}width:8;">PARADERO</th>
        <th style="{{ $hdr }}width:4;">TIPO</th>
        @for($d = 1; $d <= $daysInMonth; $d++)
            <th style="{{ $sundays[$d] ? $sun : $hdr }}width:3;">{{ $d }}</th>
        @endfor
        <th style="{{ $hdr }}width:3;">V.T</th>
    </tr>

    @php
        $prevController = null;
        $prevStop = null;
        $controllerSpans = [];
        $stopSpans = [];

        // Calcular rowspans
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
    @endphp

    @foreach($rows as $idx => $r)
        <tr>
            @if(($controllerSpans[$idx] ?? 0) > 0)
                <td rowspan="{{ $controllerSpans[$idx] }}" style="{{ $hdr }}">{{ $r['controller'] }}</td>
            @endif
            @if(($stopSpans[$idx] ?? 0) > 0)
                <td rowspan="{{ $stopSpans[$idx] }}" style="{{ $hdr }}">{{ $r['stop'] }}</td>
            @endif
            <td style="{{ $hdr }}">{{ $r['type'] === 'Emp' ? 'Emp.' : 'Apoyo.' }}</td>
            @for($d = 1; $d <= $daysInMonth; $d++)
                <td style="{{ $ds }}">{{ $r['days'][$d] ?? 0 }}</td>
            @endfor
            <td style="{{ $ds }}font-weight:bold;">{{ $r['total'] }}</td>
        </tr>
    @endforeach

    {{-- Totales --}}
    <tr>
        <td colspan="2" rowspan="3" style="{{ $hdr }}">TOTAL GENERAL</td>
        <td style="{{ $ftr }}">T.E</td>
        @for($d = 1; $d <= $daysInMonth; $d++)
            <td style="{{ $ftr }}">{{ $totalsTE[$d] ?? 0 }}</td>
        @endfor
        <td style="{{ $ftr }}">{{ $grandTE }}</td>
    </tr>
    <tr>
        <td style="{{ $ftr }}">T.A</td>
        @for($d = 1; $d <= $daysInMonth; $d++)
            <td style="{{ $ftr }}">{{ $totalsTA[$d] ?? 0 }}</td>
        @endfor
        <td style="{{ $ftr }}">{{ $grandTA }}</td>
    </tr>
    <tr>
        <td style="{{ $ftr }}">V.T</td>
        @for($d = 1; $d <= $daysInMonth; $d++)
            <td style="{{ $ftr }}">{{ $totalsVT[$d] ?? 0 }}</td>
        @endfor
        <td style="{{ $ftr }}">{{ $grandVT }}</td>
    </tr>
</table>

</body>
</html>
