<html>
<head><meta charset="UTF-8"></head>
<body>
<table cellspacing="0" border="1" style="border-collapse:collapse;">
    <thead>
    <tr><td colspan="10" align="center" style="font-weight:bold;color:red;font-size:11pt;">PAGO</td></tr>
    <tr>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Nº</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Placa</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Serie</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Fecha Registro</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Fecha Pago</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Hora</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Tipo</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Sucursal</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Usuario</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>S/</b></th>
    </tr>
    </thead>
    <tbody>
    @php $total = 0; @endphp
    @foreach($rows as $row)
        @php $total += (float) $row['amount']; @endphp
        <tr>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['item'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['plate'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['serie'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['date_register'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['date_payment'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['hour'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;{{ strtolower($row['type']) === 'retraso' ? 'color:red;' : '' }}">{{ $row['type'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['headquarter'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['user'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ number_format((float) $row['amount'], 2) }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
    <tr>
        <td colspan="9" align="center" style="font-weight:bold;background:#CEE7FF;">TOTAL</td>
        <td align="center" style="font-weight:bold;background:#CEE7FF;">{{ number_format($total, 2) }}</td>
    </tr>
    </tfoot>
</table>
</body>
</html>
