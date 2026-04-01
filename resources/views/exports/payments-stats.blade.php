@php
    $ds  = 'border:1px dotted #808080;border-left:1px solid #000;border-right:1px solid #000;text-align:center;vertical-align:middle;font-size:9pt;white-space:nowrap;';
    $hdr = 'background:#2874A6;color:white;font-weight:bold;text-align:center;vertical-align:middle;font-size:9pt;border:1px solid #000;white-space:nowrap;';
    $sun = 'background:#FF0000;color:white;font-weight:bold;text-align:center;vertical-align:middle;font-size:9pt;border:1px solid #000;white-space:nowrap;';
    $ftr = 'background:#CEE7FF;font-weight:bold;text-align:center;vertical-align:middle;font-size:9pt;border:1px solid #000;white-space:nowrap;';
    $ttl = 'font-weight:bold;color:red;text-align:center;vertical-align:middle;font-size:10pt;border:1px solid #000;white-space:nowrap;';
    $totalCols = 3 + $daysInMonth + 1;

    // Calcular rowspans
    $controllerSpans = [];
    $hqSpans = [];
    $rowCount = count($rows);
    $i = 0;
    while ($i < $rowCount) {
        // Controller span: buscar hasta que cambie el controller (col 0)
        if ($rows[$i][0] !== '') {
            $ctrlStart = $i;
            $j = $i + 1;
            while ($j < $rowCount && $rows[$j][0] === '') $j++;
            $controllerSpans[$i] = $j - $ctrlStart;
            for ($k = $ctrlStart + 1; $k < $j; $k++) $controllerSpans[$k] = 0;
            $i = $j;
        } else {
            $controllerSpans[$i] = 0;
            $i++;
        }
    }

    // HQ span: cada 3 filas (Pago/Retraso/Deuda)
    for ($i = 0; $i < $rowCount; $i++) {
        if ($rows[$i][1] !== '') {
            $hqSpans[$i] = 3;
            if ($i + 1 < $rowCount) $hqSpans[$i + 1] = 0;
            if ($i + 2 < $rowCount) $hqSpans[$i + 2] = 0;
        }
    }

    $fmt = function($val) {
        $val = (float)$val;
        if (abs($val) < 0.0001) return '';
        if (fmod($val, 1.0) == 0.0) return number_format($val, 0, '.', '');
        return number_format($val, 2, '.', '');
    };
@endphp
<html>
<head><meta charset="UTF-8"></head>
<body topmargin="0" leftmargin="0" rightmargin="0" bottommargin="0">

<table cellspacing="0" border="1" style="border-collapse:collapse;">
    <col width="105">
    <col width="90">
    <col width="55">
    @for($d = 1; $d <= $daysInMonth; $d++)
        <col width="35">
    @endfor
    <col width="50">

    <tr>
        <td colspan="{{ $totalCols }}" style="{{ $ttl }}">
            REPORTE ESTADISTICO DE PAGO - {{ mb_strtoupper($monthName) }} {{ $year }}
        </td>
    </tr>
    <tr>
        <th style="{{ $hdr }}">CONTROLADOR</th>
        <th colspan="2" style="{{ $hdr }}">PARADERO</th>
        @for($d = 1; $d <= $daysInMonth; $d++)
            <th style="{{ $sundays[$d] ? $sun : $hdr }}">{{ $d }}</th>
        @endfor
        <th style="{{ $hdr }}">TOTAL</th>
    </tr>

    @foreach($rows as $idx => $r)
        <tr>
            @if(($controllerSpans[$idx] ?? 0) > 0)
                <td rowspan="{{ $controllerSpans[$idx] }}" style="{{ $hdr }}">{{ $r[0] }}</td>
            @endif
            @if(($hqSpans[$idx] ?? 0) > 0)
                <td rowspan="{{ $hqSpans[$idx] }}" style="{{ $hdr }}">{{ $r[1] }}</td>
            @endif
            <td style="{{ $hdr }}">{{ $r[2] }}</td>
            @for($d = 1; $d <= $daysInMonth; $d++)
                <td style="{{ $ds }}">{{ $fmt($r[3 + $d - 1]) }}</td>
            @endfor
            <td style="{{ $ds }}font-weight:bold;">{{ $fmt($r[3 + $daysInMonth]) }}</td>
        </tr>
    @endforeach

    {{-- Footer --}}
    <tr>
        <td colspan="3" style="{{ $ftr }}">TOTAL GENERAL</td>
        @for($d = 1; $d <= $daysInMonth; $d++)
            <td style="{{ $ftr }}">{{ $fmt($footer[3 + $d - 1]) }}</td>
        @endfor
        <td style="{{ $ftr }}">{{ $fmt($footer[3 + $daysInMonth]) }}</td>
    </tr>
</table>

</body>
</html>
