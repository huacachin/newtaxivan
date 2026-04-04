@php
    $ds  = 'border:1px dotted #808080;border-left:1px solid #000;border-right:1px solid #000;text-align:center;vertical-align:middle;font-size:9pt;white-space:nowrap;';
    $hdr = 'background:#2874A6;color:white;font-weight:bold;text-align:center;vertical-align:middle;font-size:9pt;border:1px solid #000;white-space:nowrap;';
    $ftr = 'background:#CEE7FF;font-weight:bold;text-align:center;vertical-align:middle;font-size:9pt;border:1px solid #000;white-space:nowrap;';
    $months = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];

    $years = $result->pluck('year')->unique()->sort()->reverse()->values();
    $yearColors = [];
    foreach ($years as $i => $y) {
        $yearColors[$y] = $i % 2 === 0 ? '#000' : 'red';
    }
@endphp
<html>
<head><meta charset="UTF-8"></head>
<body topmargin="0" leftmargin="0" rightmargin="0" bottommargin="0">

<table cellspacing="0" border="1" style="border-collapse:collapse;">
    <col width="30">
    <col width="30">
    <col width="90">
    <col width="50">
    <col width="50">
    <col width="60">

    <tr>
        <td colspan="6" align="center" style="font-weight:bold;font-size:10pt;">
            <b style="color:red">Costo por placa ({{ $result->count() }})</b>
        </td>
    </tr>
    <tr>
        <th style="{{ $hdr }}">Nº</th>
        <th style="{{ $hdr }}">M</th>
        <th style="{{ $hdr }}">Mes</th>
        <th style="{{ $hdr }}">Año</th>
        <th style="{{ $hdr }}">Placas</th>
        <th style="{{ $hdr }}">S/</th>
    </tr>

    @forelse($result as $item)
        <tr>
            <td style="{{ $ds }}">{{ $loop->iteration }}</td>
            <td style="{{ $ds }}color:{{ $yearColors[$item->year] ?? '#000' }};font-weight:bold;">{{ str_pad($item->month, 2, '0', STR_PAD_LEFT) }}</td>
            <td style="{{ $ds }}">{{ $months[$item->month] ?? $item->month }}</td>
            <td style="{{ $ds }}color:{{ $yearColors[$item->year] ?? '#000' }};font-weight:bold;">{{ $item->year }}</td>
            <td style="{{ $ds }}">{{ $item->plates }}</td>
            <td style="{{ $ds }}">{{ number_format($item->amount, 2) }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="6" style="{{ $ds }}">No se encontraron resultados</td>
        </tr>
    @endforelse

    <tfoot>
    <tr>
        <td colspan="5" style="{{ $ftr }}">TOTAL</td>
        <td style="{{ $ftr }}">{{ $result->count() }}</td>
    </tr>
    </tfoot>
</table>

</body>
</html>
