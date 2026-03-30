<html>
<head><meta charset="UTF-8"></head>
<body>
<center><b style="color:red; font-size:11pt;">REPORTE DE GASTOS</b></center><br>
<table cellspacing="0" border="1" style="border-collapse:collapse;">
    <thead>
    <tr>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Item</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Fecha</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>A (Raz&oacute;n)</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Motivo (Detalle)</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Total</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Usuario</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Responsable</b></th>
    </tr>
    </thead>
    <tbody>
    @php $total = 0; @endphp
    @foreach($rows as $row)
        @php $total += (float) $row['total']; @endphp
        <tr>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['item'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['date'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['reason'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['detail'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ number_format((float) $row['total'], 2) }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['user'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['in_charge'] }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
    <tr style="background:#CEE7FF">
        <td colspan="4" align="center" style="font-weight:bold;">Total</td>
        <td align="center" style="font-weight:bold;">{{ number_format($total, 2) }}</td>
        <td colspan="2"></td>
    </tr>
    </tfoot>
</table>
</body>
</html>
