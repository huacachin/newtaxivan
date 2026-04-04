@php
    $ds  = 'border:1px dotted #808080;border-left:1px solid #000;border-right:1px solid #000;text-align:center;vertical-align:middle;font-size:9pt;white-space:nowrap;';
    $hdr = 'background:#2874A6;color:white;font-weight:bold;text-align:center;vertical-align:middle;font-size:9pt;border:1px solid #000;white-space:nowrap;';
    $ftr = 'background:#CEE7FF;font-weight:bold;text-align:center;vertical-align:middle;font-size:9pt;border:1px solid #000;white-space:nowrap;';
@endphp
<html>
<head><meta charset="UTF-8"></head>
<body topmargin="0" leftmargin="0" rightmargin="0" bottommargin="0">

<table cellspacing="0" border="1" style="border-collapse:collapse;">
    <col width="30">
    <col width="80">
    <col width="50">
    <col width="60">
    <col width="60">

    <tr>
        <td colspan="5" align="center" style="font-weight:bold;font-size:10pt;">
            <b style="color:red">Costo por placa - {{ $monthName }} {{ $year }}</b> ·
            <b>Placas:</b> <b style="color:red">{{ $result->count() }}</b> ·
            <b>Total S/:</b> <b style="color:red">{{ number_format($result->sum('amount'), 2) }}</b>
        </td>
    </tr>
    <tr>
        <th style="{{ $hdr }}">Nº</th>
        <th style="{{ $hdr }}">Placa</th>
        <th style="{{ $hdr }}">Mes</th>
        <th style="{{ $hdr }}">Año</th>
        <th style="{{ $hdr }}">S/</th>
    </tr>

    @forelse($result as $item)
        <tr>
            <td style="{{ $ds }}">{{ $loop->iteration }}</td>
            <td style="{{ $ds }}">{{ $item->plate }}</td>
            <td style="{{ $ds }}">{{ $monthName }}</td>
            <td style="{{ $ds }}">{{ $item->year }}</td>
            <td style="{{ $ds }}">{{ number_format($item->amount, 2) }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="5" style="{{ $ds }}">No se encontraron resultados</td>
        </tr>
    @endforelse

    <tfoot>
    <tr>
        <td colspan="3" style="{{ $ftr }}">TOTAL</td>
        <td style="{{ $ftr }}">{{ $result->count() }}</td>
        <td style="{{ $ftr }}">{{ number_format($result->sum('amount'), 2) }}</td>
    </tr>
    </tfoot>
</table>

</body>
</html>
