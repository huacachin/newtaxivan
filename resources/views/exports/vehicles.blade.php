<html>
<head><meta charset="UTF-8"></head>
<body>
<table cellspacing="0" border="1" style="border-collapse:collapse;">
    <thead>
    <tr>
        <td colspan="{{ ($showTermination ?? false) ? 13 : 12 }}" align="center" style="font-weight:bold;font-size:10pt;">
            <b>Total de vehículos </b> <b style="color:red" >{{ $stats['total'] }}</b> -
            <b>D2:</b> <b style="color:red">{{ $stats['d2'] }}</b> ·
            <b>Gas:</b> <b style="color:red">{{ $stats['gas'] }}</b> ·
            <b>V.T:</b> <b style="color:red">{{ $stats['vt'] }}</b> ·
            <b>V.Q.N.T:</b> <b style="color:red">{{ $stats['vqnt'] }}</b> ·
            <b>Propietario:</b> <b style="color:red">{{ $stats['prop'] }}</b> ·
            <b>Conductor:</b> <b style="color:red">{{ $stats['cond'] }}</b>
        </td>
    </tr>
    <tr>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Nº</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Cod</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Placa</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Marca</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>A&ntilde;o</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Categor&iacute;a</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Propietario</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Conductor</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Modalidad</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Comb.</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Condici&oacute;n</b></th>
        <th bgcolor="#2874A6" align="center" style="color:white;"><b>Empresa Afil.</b></th>
        @if($showTermination ?? false)
            <th bgcolor="#2874A6" align="center" style="color:white;"><b>Fecha Cese</b></th>
        @endif
    </tr>
    </thead>
    <tbody>
    @foreach($rows as $row)
        <tr>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['item'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['cod'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['plate'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['brand'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['year'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['class'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['owner'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['driver'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['type'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['fuel'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['condition'] }}</td>
            <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['company'] }}</td>
            @if($showTermination ?? false)
                <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;">{{ $row['termination'] }}</td>
            @endif
        </tr>
    @endforeach
    </tbody>
    <tfoot>
    <tr>
        <td colspan="{{ ($showTermination ?? false) ? 12 : 11 }}" bgcolor="#CEE7FF" align="center" style="font-weight:bold;">TOTAL VEH&Iacute;CULOS</td>
        <td bgcolor="#CEE7FF" align="center" style="font-weight:bold;">{{ count($rows) }}</td>
    </tr>
    </tfoot>
</table>
</body>
</html>
