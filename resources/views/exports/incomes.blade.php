<html>
<head><meta charset="UTF-8"></head>
<body>
<table cellspacing="0" border="1" style="border-collapse:collapse;">
    <thead>
    <tr><td colspan="6" align="center" style="font-weight:bold;color:red;font-size:11pt;">LISTADO GENERAL DE INGRESOS</td></tr>
    <tr>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Nº</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Fecha</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Respons.</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>A</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Motivo</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>S/.</b></th>
    </tr>
    </thead>
    <tbody>
    @php $total = 0; @endphp
    @foreach($rows as $row)
        @php $total += (float) $row['total']; @endphp
        <tr>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;font-weight:bold;">{{ $row['item'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['date'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['user'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['a'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['motivo'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ number_format((float) $row['total'], 2) }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
    <tr style="background:#CEE7FF">
        <td colspan="3"></td>
        <td colspan="2" align="center" style="font-weight:bold;">Total</td>
        <td align="center" style="font-weight:bold;">{{ number_format($total, 2) }}</td>
    </tr>
    </tfoot>
</table>
</body>
</html>
