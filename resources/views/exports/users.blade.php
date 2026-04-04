@php
    $ds  = 'border:1px dotted #808080;border-left:1px solid #000;border-right:1px solid #000;text-align:center;vertical-align:middle;font-size:9pt;white-space:nowrap;';
    $hdr = 'background:#2874A6;color:white;font-weight:bold;text-align:center;vertical-align:middle;font-size:9pt;border:1px solid #000;white-space:nowrap;';
    $ftr = 'background:#CEE7FF;font-weight:bold;text-align:center;vertical-align:middle;font-size:9pt;border:1px solid #000;white-space:nowrap;';
@endphp
<html>
<head><meta charset="UTF-8"></head>
<body topmargin="0" leftmargin="0" rightmargin="0" bottommargin="0">

<table cellspacing="0" border="1" style="border-collapse:collapse;">

    <tr>
        <td colspan="7" align="center" style="font-weight:bold;font-size:10pt;">
            <b style="color:red">Usuarios ({{ $total }})</b>
        </td>
    </tr>
    <tr>
        <th style="{{ $hdr }}">Nº</th>
        <th style="{{ $hdr }}">Nombres</th>
        <th style="{{ $hdr }}">Usuario</th>
        <th style="{{ $hdr }}">Teléfono</th>
        <th style="{{ $hdr }}">Sede</th>
        <th style="{{ $hdr }}">Rol</th>
        <th style="{{ $hdr }}">Permisos</th>
    </tr>

    @forelse($rows as $row)
        <tr>
            <td style="{{ $ds }}">{{ $row['item'] }}</td>
            <td style="{{ $ds }}text-align:left;">{{ $row['name'] }}</td>
            <td style="{{ $ds }}">{{ $row['username'] }}</td>
            <td style="{{ $ds }}">{{ $row['phone'] }}</td>
            <td style="{{ $ds }}">{{ $row['sede'] }}</td>
            <td style="{{ $ds }}">{{ $row['rol'] }}</td>
            <td style="{{ $ds }}">{{ $row['permisos'] }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="7" style="{{ $ds }}">No se encontraron resultados</td>
        </tr>
    @endforelse

    <tfoot>
    <tr>
        <td colspan="6" style="{{ $ftr }}">TOTAL</td>
        <td style="{{ $ftr }}">{{ $total }}</td>
    </tr>
    </tfoot>
</table>

</body>
</html>
