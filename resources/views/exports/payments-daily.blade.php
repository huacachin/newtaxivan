@php
    $ds   = 'border:1px dotted #808080;border-left:1px solid #000;border-right:1px solid #000;text-align:center;vertical-align:middle;font-size:9pt;white-space:nowrap;';
    $hdr  = 'background:#2874A6;color:white;font-weight:bold;text-align:center;vertical-align:middle;font-size:9pt;border:1px solid #000;white-space:nowrap;';
    $sun  = 'background:#FF0000;color:white;font-weight:bold;text-align:center;vertical-align:middle;font-size:9pt;border:1px solid #000;white-space:nowrap;';
    $ftr  = 'background:#2874A6;color:white;font-weight:bold;text-align:center;vertical-align:middle;font-size:9pt;border:1px solid #000;white-space:nowrap;';
    $ttl  = 'font-weight:bold;color:red;text-align:center;vertical-align:middle;font-size:10pt;border:1px solid #000;white-space:nowrap;';
    $grey = 'background:#D0CECE;text-align:center;vertical-align:middle;font-size:9pt;border:1px solid #000;white-space:nowrap;';
    $paid = 'background:green;color:white;font-weight:600;text-align:right;font-size:9pt;border:1px solid #000;white-space:nowrap;';
    $zero = 'background:#FF0000;text-align:center;font-size:9pt;border:1px solid #000;white-space:nowrap;';
    $sunC = 'background:#FFFFFF;text-align:center;font-size:9pt;border:1px solid #000;white-space:nowrap;';

    $baseCols  = 3 + $daysInMonth + 2;
    $extraCols = ($mode === 'Pago') ? 4 : 0;
    $totalCols = $baseCols + $extraCols;

    $fmt = function($val) {
        return number_format((float)$val, 2);
    };
@endphp
<html>
<head><meta charset="UTF-8"></head>
<body topmargin="0" leftmargin="0" rightmargin="0" bottommargin="0">

<table cellspacing="0" border="1" style="border-collapse:collapse;">
    <col width="31">
    <col width="61">
    <col width="36">
    @for($d = 1; $d <= $daysInMonth; $d++)
        <col width="35">
    @endfor
    <col width="72">
    <col width="87">
    @if($mode === 'Pago')
        <col width="72">
        <col width="87">
        <col width="72">
        <col width="87">
    @endif

    {{-- Título --}}
    <tr>
        <td colspan="{{ $totalCols }}" style="{{ $ttl }}">
            {{ $mode === 'Pago' ? 'REPORTE DIARIO DE PAGO' : 'REPORTE DIARIO DE CAJA' }} - {{ mb_strtoupper($monthName) }} {{ $year }}
        </td>
    </tr>

    {{-- Header fila 1 --}}
    <tr>
        <th rowspan="2" style="{{ $hdr }}">Item</th>
        <th rowspan="2" style="{{ $hdr }}">Placa</th>
        <th rowspan="2" style="{{ $hdr }}">Cond.</th>
        @for($d = 1; $d <= $daysInMonth; $d++)
            <th rowspan="2" style="{{ $sundays[$d] ? $sun : $hdr }}">{{ $d }}</th>
        @endfor
        <th colspan="2" style="{{ $hdr }}">TOTAL PAGOS</th>
        @if($mode === 'Pago')
            <th colspan="2" style="{{ $hdr }}">TOTAL DEUDA</th>
            <th colspan="2" style="{{ $hdr }}">TOTAL D. REAL</th>
        @endif
    </tr>

    {{-- Header fila 2 --}}
    <tr>
        <th style="{{ $hdr }}">Días</th>
        <th style="{{ $hdr }}">S/</th>
        @if($mode === 'Pago')
            <th style="{{ $hdr }}">Días</th>
            <th style="{{ $hdr }}">S/</th>
            <th style="{{ $hdr }}">Días</th>
            <th style="{{ $hdr }}">S/</th>
        @endif
    </tr>

    {{-- Datos --}}
    @php $i = 0; @endphp
    @foreach($rows as $r)
        @php $i++; @endphp
        <tr>
            <td style="{{ $grey }}">{{ $i }}</td>
            <td style="{{ $grey }}">{{ $r['plate'] }}</td>
            <td style="{{ $grey }}">{{ strtoupper($r['cond'] ?? '') ?: '-' }}</td>

            @for($d = 1; $d <= $daysInMonth; $d++)
                @php $val = (float)($r['days'][$d] ?? 0); @endphp
                @if($sundays[$d])
                    <td style="{{ $sunC }}"></td>
                @elseif($val > 0)
                    <td style="{{ $paid }}">{{ $fmt($val) }}</td>
                @else
                    <td style="{{ $zero }}"></td>
                @endif
            @endfor

            <td style="{{ $grey }}">{{ number_format($r['days_paid']) }}</td>
            <td style="{{ $grey }}">{{ $fmt($r['total']) }}</td>

            @if($mode === 'Pago')
                <td style="{{ $grey }}">{{ number_format($r['debt_days']) }}</td>
                <td style="{{ $grey }}">{{ $fmt($r['debt_amount']) }}</td>
                <td style="{{ $grey }}">{{ number_format($r['real_debt_days']) }}</td>
                <td style="{{ $grey }}">{{ $fmt($r['real_debt_amount']) }}</td>
            @endif
        </tr>
    @endforeach

    {{-- Footer --}}
    <tr>
        <td colspan="3" style="{{ $ftr }}">Totales por día (S/)</td>
        @for($d = 1; $d <= $daysInMonth; $d++)
            <td style="{{ $ftr }}">{{ $fmt($totalsPerDay[$d] ?? 0) }}</td>
        @endfor
        <td style="{{ $ftr }}">{{ number_format($sumDaysPaid) }}</td>
        <td style="{{ $ftr }}">{{ $fmt($grandTotal) }}</td>
        @if($mode === 'Pago')
            <td style="{{ $ftr }}">{{ number_format($sumDebtDays) }}</td>
            <td style="{{ $ftr }}">{{ $fmt($sumDebtAmount) }}</td>
            <td style="{{ $ftr }}">{{ number_format($sumRealDebtDays) }}</td>
            <td style="{{ $ftr }}">{{ $fmt($sumRealDebtAmount) }}</td>
        @endif
    </tr>
</table>

</body>
</html>
