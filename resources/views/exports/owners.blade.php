<html>
<head><meta charset="UTF-8"></head>
<body>
<table cellspacing="0" border="1" style="border-collapse:collapse;">
    <thead>
    <tr><td colspan="6" align="center" style="font-weight:bold;color:red;font-size:11pt;">LISTADO GENERAL DE PROPIETARIO ( {{ $countActive }} )</td></tr>
    <tr>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Nº</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Cod</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Placa</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Nombre</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>N&deg; Documento</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Tel&eacute;fono</b></th>
    </tr>
    </thead>
    <tbody>
    @foreach($activeRows as $row)
        <tr>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;font-weight:bold;">{{ $row[0] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;font-weight:bold;">{{ $row[1] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row[2] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row[3] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row[4] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row[5] }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
    <tr style="background:#CEE7FF">
        <td colspan="5" align="center" style="font-weight:bold;">TOTAL ACTIVOS</td>
        <td align="center" style="font-weight:bold;">{{ $countActive }}</td>
    </tr>
    </tfoot>
</table>

<br>
<table cellspacing="0" border="1" style="border-collapse:collapse;">
    <thead>
    <tr><td colspan="6" align="center" style="font-weight:bold;color:red;font-size:10pt;">PROPIETARIOS LIBRES</td></tr>
    <tr>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Nº</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Cod</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Placa</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Nombre</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>N&deg; Documento</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Tel&eacute;fono</b></th>
    </tr>
    </thead>
    <tbody>
    @forelse($freeRows as $row)
        <tr>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;font-weight:bold;">{{ $row[0] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;font-weight:bold;">{{ $row[1] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row[2] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row[3] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row[4] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row[5] }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="6" align="center" style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">No hay propietarios libres para los filtros actuales.</td>
        </tr>
    @endforelse
    </tbody>
    <tfoot>
    <tr style="background:#CEE7FF">
        <td colspan="5" align="center" style="font-weight:bold;">TOTAL LIBRES</td>
        <td align="center" style="font-weight:bold;">{{ $countFree }}</td>
    </tr>
    </tfoot>
</table>
</body>
</html>
