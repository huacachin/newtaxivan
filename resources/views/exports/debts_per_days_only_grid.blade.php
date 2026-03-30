<html>
<head>
<meta charset="UTF-8">
<style>
    table { border-collapse: collapse; border: 1px solid #000; }
    th, td { border: 1px dotted #000; border-left: 1px solid #000; border-right: 1px solid #000; text-align: center; vertical-align: middle; font-size: 10pt; }
    th { background-color: #2874A6; color: white; font-weight: bold; border: 1px solid #000; }
    .sun { background-color: red; }
    .sun-cell { background-color: white; }
    .paid { color: black; }
    .nopay { color: #FF8C00; }
    .freq { color: blue; font-weight: bold; }
    .footer { background-color: #CEE7FF; font-weight: bold; }
    .title { color: red; font-weight: bold; font-size: 11pt; }
</style>
</head>
<body>
<div class="title">DEUDA POR DÍA – {{ $monthLabel }}</div>
<br>
<table cellspacing="0" border="1">
    <thead>
    <tr>
        <th>Item</th>
        <th>Cod</th>
        <th>Placa</th>
        <th>Cond.</th>
        @foreach($days as $d)
            <th @if($d['isSunday']) class="sun" @endif>{{ $d['n'] }}</th>
        @endforeach
        <th colspan="2">Total Pagos</th>
        <th colspan="2">Total Deuda</th>
    </tr>
    </thead>
    <tbody>
    @php
        $sumPaidDays = 0; $sumPaidAmount = 0;
        $sumDebtDays = 0; $sumDebtAmount = 0;
        $dayTotals = [];
    @endphp
    @foreach($rows as $r)
        <tr>
            <td>{{ $r['item'] }}</td>
            <td>{{ $r['cod'] }}</td>
            <td>{{ $r['plate'] }}</td>
            <td>{{ $r['condition'] }}</td>

            @foreach($r['cells'] as $ci => $cell)
                @php
                    $dayKey = $days[$ci]['d'] ?? '';
                    if (!isset($dayTotals[$dayKey])) $dayTotals[$dayKey] = 0;
                @endphp
                @if($cell['type'] === 'sun')
                    <td class="sun-cell"></td>
                @elseif($cell['type'] === 'paid')
                    <td class="paid">{{ $cell['txt'] }}</td>
                @elseif($cell['type'] === 'nopay')
                    <td class="nopay">{{ $cell['txt'] }}</td>
                @elseif($cell['type'] === 'freq')
                    <td class="freq">{{ $cell['txt'] }}</td>
                @else
                    <td>{{ $cell['txt'] }}</td>
                @endif
            @endforeach

            <td>{{ $r['paid_days'] }}</td>
            <td>{{ number_format($r['paid_amount'], 2) }}</td>
            <td>{{ $r['debt_days'] }}</td>
            <td>{{ number_format($r['debt_amount'], 2) }}</td>

            @php
                $sumPaidDays += $r['paid_days'];
                $sumPaidAmount += $r['paid_amount'];
                $sumDebtDays += $r['debt_days'];
                $sumDebtAmount += $r['debt_amount'];
            @endphp
        </tr>
    @endforeach
    <tr class="footer">
        <td></td>
        <td></td>
        <td><b>Total</b></td>
        <td></td>
        @foreach($days as $d)
            <td></td>
        @endforeach
        <td>{{ $sumPaidDays }}</td>
        <td>{{ number_format($sumPaidAmount, 2) }}</td>
        <td>{{ $sumDebtDays }}</td>
        <td>{{ number_format($sumDebtAmount, 2) }}</td>
    </tr>
    </tbody>
</table>
</body>
</html>
