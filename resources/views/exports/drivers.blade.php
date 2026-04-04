<html>
<head><meta charset="UTF-8"></head>
<body>
<table cellspacing="0" border="1" style="border-collapse:collapse;">
    <thead>
    <tr>
        <td colspan="9" align="center" style="font-weight:bold;font-size:10pt;">
            <b>Total de conductores</b> <b style="color:red">{{ $totalActive + $totalFree }}</b>-
            <b>Conductores:</b> <b style="color:red">{{ $totalActive }}</b> ·
            <b>Libres:</b> <b style="color:red">{{ $totalFree }}</b>
        </td>
    </tr>
    <tr>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Nº</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Cod</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Placa</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Nombre</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Dni</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>I.Contrato</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>F.Contrato</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Tel&eacute;fono</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Estado</b></th>
    </tr>
    </thead>
    <tbody>
    @foreach($activeRows as $row)
        <tr>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;font-weight:bold;">{{ $row['item'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;font-weight:bold;">{{ $row['cod'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['plates'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['name'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['document'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['contract_start'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['contract_end'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['phone'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['condition'] }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
    <tr style="background:#CEE7FF">
        <td colspan="8" align="center" style="font-weight:bold;">TOTAL ACTIVOS</td>
        <td align="center" style="font-weight:bold;">{{ $totalActive }}</td>
    </tr>
    </tfoot>
</table>

<br>
<table cellspacing="0" border="1" style="border-collapse:collapse;">
    <thead>
    <tr><td colspan="9" align="center" style="font-weight:bold;color:red;font-size:10pt;">CONDUCTORES LIBRES</td></tr>
    <tr>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Nº</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Cod</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Placa</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Nombre</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>N&deg; Documento</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>I.Contrato</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>F.Contrato</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Tel&eacute;fono</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Condici&oacute;n</b></th>
    </tr>
    </thead>
    <tbody>
    @forelse($freeRows as $row)
        <tr>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;font-weight:bold;">{{ $row['item'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;font-weight:bold;">{{ $row['cod'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['plates'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['name'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['document'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['contract_start'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['contract_end'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['phone'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['condition'] }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="9" align="center" style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">Sin conductores libres.</td>
        </tr>
    @endforelse
    </tbody>
    <tfoot>
    <tr style="background:#CEE7FF">
        <td colspan="8" align="center" style="font-weight:bold;">TOTAL LIBRES</td>
        <td align="center" style="font-weight:bold;">{{ $totalFree }}</td>
    </tr>
    </tfoot>
</table>
</body>
</html>
