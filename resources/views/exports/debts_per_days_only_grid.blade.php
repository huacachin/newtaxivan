<table>
    <thead>
    <tr>
        <th>Item</th>
        <th>Cod</th>
        <th>Placa</th>
        <th>Cond.</th>
        @foreach($days as $d)
            <th>{{ $d['n'] }}</th>
        @endforeach
        <th>P días</th>
        <th>P S/</th>
        <th>D días</th>
        <th>D S/</th>
    </tr>
    </thead>
    <tbody>
    @foreach($rows as $r)
        <tr>
            <td>{{ $r['item'] }}</td>
            <td>{{ $r['cod'] }}</td>
            <td>{{ $r['plate'] }}</td>
            <td>{{ $r['condition'] }}</td>

            @foreach($r['cells'] as $cell)
                <td>{{ $cell['txt'] }}</td>
            @endforeach

            <td>{{ $r['paid_days'] }}</td>
            <td>{{ $r['paid_amount'] }}</td>
            <td>{{ $r['debt_days'] }}</td>
            <td>{{ $r['debt_amount'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
