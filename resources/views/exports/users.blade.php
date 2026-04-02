<html>
<head><meta charset="UTF-8"></head>
<body>
<table cellspacing="0" border="1" style="border-collapse:collapse;">
    <thead>
    <tr><td colspan="6" align="center" style="font-weight:bold;color:red;font-size:11pt;">LISTADO GENERAL DE USUARIO</td></tr>
    <tr>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Nº</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Nombres</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Telefono</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Sedes</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Sede Primaria</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Nivel</b></th>
    </tr>
    </thead>
    <tbody>
    @foreach($rows as $row)
        <tr>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['item'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:left;vertical-align:middle;">{{ $row['name'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['phone'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['sedes'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['sede_primaria'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['nivel'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
