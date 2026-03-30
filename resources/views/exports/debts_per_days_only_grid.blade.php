<html>
<head>
<meta charset="UTF-8">
</head>
<body topmargin="0" leftmargin="0" rightmargin="0" bottommargin="0">
<table cellspacing="0" border="1">
    <thead>
    <tr><td colspan="{{ 4 + count($days) + 4 }}" align="center" style="font-weight:bold;color:red;font-size:11pt;">REPORTE DE RETRASO {{ strtoupper($monthLabel) }}</td></tr>
    <tr>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Item</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Cod</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Placa</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Condicion</b></th>
        @foreach($days as $d)
            <th bgcolor="#2874A6" align="center" style="color:white;{{ $d['isSunday'] ? 'background:red;' : '' }}"><b>{{ $d['n'] }}</b></th>
        @endforeach
        <th bgcolor="#2874A6" align="center" style="color:white;" colspan="2"><b>Total Pagos</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;" colspan="2"><b>Total Deuda</b></th>
    </tr>
    </thead>
    <tbody>
    @php
        $sumPaidDays = 0; $sumPaidAmount = 0;
        $sumDebtDays = 0; $sumDebtAmount = 0;
    @endphp
    @foreach($rows as $r)
        <tr>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $r['item'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $r['cod'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $r['plate'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $r['condition'] }}</td>

            @foreach($r['cells'] as $cell)
                <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $cell['txt'] }}</td>
            @endforeach

            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $r['paid_days'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ number_format($r['paid_amount'], 2) }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $r['debt_days'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ number_format($r['debt_amount'], 2) }}</td>

            @php
                $sumPaidDays += $r['paid_days'];
                $sumPaidAmount += $r['paid_amount'];
                $sumDebtDays += $r['debt_days'];
                $sumDebtAmount += $r['debt_amount'];
            @endphp
        </tr>
    @endforeach
    <tr>
        <td colspan="{{ 4 + count($days) }}" align="center" style="background:#CEE7FF;"><b>Total</b></td>
        <td style="background:#CEE7FF;">{{ number_format($sumPaidDays, 2) }}</td>
        <td align="center" style="background:#CEE7FF;">{{ number_format($sumPaidAmount, 2) }}</td>
        <td align="center" style="background:#CEE7FF;">{{ $sumDebtDays }}</td>
        <td align="center" style="background:#CEE7FF;">{{ number_format($sumDebtAmount, 2) }}</td>
    </tr>
    </tbody>
</table>
</body>
</html>
