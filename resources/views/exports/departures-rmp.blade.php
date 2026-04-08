@php
    $ds  = 'border:1px dotted #808080;border-left:1px solid #000;border-right:1px solid #000;text-align:center;vertical-align:middle;font-size:9pt;white-space:nowrap;';
    $hdr = 'background:#2874A6;color:white;font-weight:bold;text-align:center;vertical-align:middle;font-size:9pt;border:1px solid #000;white-space:nowrap;';
    $sun = 'background:#FF0000;color:white;font-weight:bold;text-align:center;vertical-align:middle;font-size:9pt;border:1px solid #000;white-space:nowrap;';
    $ftr = 'background:#CEE7FF;font-weight:bold;text-align:center;vertical-align:middle;font-size:9pt;border:1px solid #000;white-space:nowrap;';
    $ttl = 'font-weight:bold;color:red;text-align:center;vertical-align:middle;font-size:10pt;border:1px solid #000;white-space:nowrap;';
    $totalCols = 3 + $daysInMonth + 1;
@endphp
<html>
<head><meta charset="UTF-8"></head>
<body topmargin="0" leftmargin="0" rightmargin="0" bottommargin="0">

<table cellspacing="0" border="1" style="border-collapse:collapse;">
    <col width="120">
    <col width="90">
    <col width="55">
    @for($d = 1; $d <= $daysInMonth; $d++)
        <col width="35">
    @endfor
    <col width="43">

    <tr>
        <td colspan="{{ $totalCols }}" style="{{ $ttl }}">
            REPORTE MENSUAL POR PARADERO VEH.TRAB {{ mb_strtoupper($monthName) }} {{ $year }}
        </td>
    </tr>
    <tr>
        <th style="{{ $hdr }}">CONTROLADOR</th>
        <th colspan="2" style="{{ $hdr }}">PARADERO</th>
        @for($d = 1; $d <= $daysInMonth; $d++)
            <th style="{{ $sundays[$d] ? $sun : $hdr }}">{{ $d }}</th>
        @endfor
        <th style="{{ $hdr }}">V.T</th>
    </tr>

    @php
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

    <tr>
        <td colspan="2" rowspan="3" style="{{ $hdr }}">TOTAL GENERAL</td>
        <td style="{{ $ftr }}">T. Emp.</td>
        @for($d = 1; $d <= $daysInMonth; $d++)
            <td style="{{ $ftr }}">{{ $totalsTE[$d] ?? 0 }}</td>
        @endfor
        <td style="{{ $ftr }}">{{ $grandTE }}</td>
    </tr>
    <tr>
        <td style="{{ $ftr }}">T. Ap.</td>
        @for($d = 1; $d <= $daysInMonth; $d++)
            <td style="{{ $ftr }}">{{ $totalsTA[$d] ?? 0 }}</td>
        @endfor
        <td style="{{ $ftr }}">{{ $grandTA }}</td>
    </tr>
    <tr>
        <td style="{{ $ftr }}">T. Veh</td>
        @for($d = 1; $d <= $daysInMonth; $d++)
            <td style="{{ $ftr }}">{{ $totalsVT[$d] ?? 0 }}</td>
        @endfor
        <td style="{{ $ftr }}">{{ $grandVT }}</td>
    </tr>
</table>

</body>
</html>
