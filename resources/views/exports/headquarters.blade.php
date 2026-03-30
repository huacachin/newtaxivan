<html>
<head><meta charset="UTF-8"></head>
<body>
<center><b style="color:red; font-size:11pt;">SUCURSALES</b></center><br>
<table cellspacing="0" border="1" style="border-collapse:collapse;">
    <thead>
    <tr>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Item</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Orden</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Nombre</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Estado</b></th>
    </tr>
    </thead>
    <tbody>
    @foreach($rows as $row)
        <tr>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['item'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:left;vertical-align:middle;">{{ $row['sort_order'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['name'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['status'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
