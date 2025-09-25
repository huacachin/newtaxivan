@php
    $title = 'Salidas';
    $range = ($filters['fromDate'] ?? '') . ' al ' . ($filters['toDate'] ?? '');
    $filterLabel = match((int)($filters['searchType'] ?? 1)) { 1=>'Placa',2=>'Usuario',3=>'Sucursal', default=>'Filtro' };
    $isGrouped = (bool)($filters['groupMode'] ?? false);
@endphp

<table>
    {{-- F1: Título --}}
    <tr>
        <td>{{ $title }}</td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td>
    </tr>

    {{-- F2: Subtítulo --}}
    <tr>
        <td>
            Rango: {{ $range }}
            @if(!empty($filters['searchText'])) — {{ $filterLabel }}: “{{ $filters['searchText'] }}” @endif
            — Modo: {{ $isGrouped ? 'Agrupado' : 'Detalle' }}
        </td>
        @for($i=0;$i<13;$i++) <td></td> @endfor
    </tr>

    {{-- F3: Blanco --}}
    <tr>@for($i=0;$i<14;$i++) <td></td> @endfor</tr>

    {{-- ===== Sección 1: Registrados ===== --}}
    {{-- F4: Título sección --}}
    <tr>
        <td>Salidas (vehículos registrados)</td>
        @for($i=0;$i<13;$i++) <td></td> @endfor
    </tr>

    {{-- F5: Header fila 1 (sin colspan) --}}
    <tr>
        <td>N°</td><td>Placa</td><td>Fecha</td>
        <td>Hora</td><td></td>
        <td>Sucursal</td><td>Usuario</td>
        <td>Empresa</td><td></td><td></td>
        <td>Vehículo</td><td></td><td></td>
        <td>Map</td>
    </tr>

    {{-- F6: Header fila 2 --}}
    <tr>
        <td></td><td></td><td></td>
        <td>Sal.</td><td>Frec.</td>
        <td></td><td></td>
        <td>Salida</td><td>T. S</td><td>S/</td>
        <td>P.</td><td>PJ</td><td>S/</td>
        <td></td>
    </tr>

    {{-- Body --}}
    @php($n=1)
    @forelse($rows as $r)
        <tr>
            <td>{{ $n++ }}</td>
            <td>{{ $r->plate }}</td>
            <td>{{ $r->date ?? '' }}</td>
            <td>{{ $isGrouped ? '-' : ($r->hour ?? '') }}</td>
            <td>{{ $isGrouped ? '-' : ($r->freq ?? '0:00:00') }}</td>
            <td>{{ $r->headquarter_name ?? '' }}</td>
            <td>{{ $r->user_name ?? '' }}</td>

            {{-- Empresa --}}
            <td>{{ (int)($r->times ?? 0) }}</td>
            <td>{{ (int)($r->times ?? 0) }}</td>
            <td>{{ (float)($r->price ?? 0) }}</td>

            {{-- Vehículo --}}
            <td>{{ (int)($r->passenger ?? 0) }}</td>
            <td>{{ (float)($r->passage ?? 0) }}</td>
            <td>{{ (float)($r->total_pasaje ?? 0) }}</td>

            {{-- Map --}}
            <td>
                @if(!empty($r->latitude) && !empty($r->longitude))
                    https://maps.google.com/?q={{ $r->latitude }},{{ $r->longitude }}
                @endif
            </td>
        </tr>
    @empty
        <tr>
            @for($i=0;$i<13;$i++) <td></td> @endfor
            <td>Sin registros</td>
        </tr>
    @endforelse

    {{-- Totales registradas --}}
    <tr>
        <td></td><td></td><td></td><td></td><td></td><td></td>
        <td style="text-align:right">TOTAL</td>
        <td>{{ (int)($totals->times_total ?? 0) }}</td>
        <td>{{ (int)($totals->times_total ?? 0) }}</td>
        <td>{{ (float)($totals->price_total ?? 0) }}</td>
        <td>{{ (int)($totals->passengers_total ?? 0) }}</td>
        <td>{{ (float)($totals->passage_total ?? 0) }}</td>
        <td>{{ (float)($totals->total_pasaje_total ?? 0) }}</td>
        <td></td>
    </tr>

    {{-- Blanco --}}
    <tr>@for($i=0;$i<14;$i++) <td></td> @endfor</tr>

    {{-- ===== Sección 2: Apoyo ===== --}}
    <tr>
        <td>Vehículos de apoyo</td>
        @for($i=0;$i<13;$i++) <td></td> @endfor
    </tr>

    {{-- Header 1 --}}
    <tr>
        <td>N°</td><td>Placa</td><td>Fecha</td>
        <td>Hora</td><td></td>
        <td>Sucursal</td><td>Usuario</td>
        <td>Empresa</td><td></td><td></td>
        <td>Vehículo</td><td></td><td></td>
        <td>Map</td>
    </tr>

    {{-- Header 2 --}}
    <tr>
        <td></td><td></td><td></td>
        <td>Sal.</td><td>Frec.</td>
        <td></td><td></td>
        <td>Salida</td><td>T. S</td><td>S/</td>
        <td>P.</td><td>PJ</td><td>S/</td>
        <td></td>
    </tr>

    {{-- Body apoyo --}}
    @php($m=1)
    @forelse($supportRows as $r)
        <tr>
            <td>{{ $m++ }}</td>
            <td>{{ $r->plate }}</td>
            <td>{{ $r->date ?? '' }}</td>
            <td>{{ $isGrouped ? '-' : ($r->hour ?? '') }}</td>
            <td>{{ $isGrouped ? '-' : ($r->freq ?? '0:00:00') }}</td>
            <td>{{ $r->headquarter_name ?? '' }}</td>
            <td>{{ $r->user_name ?? '' }}</td>

            <td>{{ (int)($r->times ?? 0) }}</td>
            <td>{{ (int)($r->times ?? 0) }}</td>
            <td>{{ (float)($r->price ?? 0) }}</td>

            <td>{{ (int)($r->passenger ?? 0) }}</td>
            <td>{{ (float)($r->passage ?? 0) }}</td>
            <td>{{ (float)($r->total_pasaje ?? 0) }}</td>

            <td>
                @if(!empty($r->latitude) && !empty($r->longitude))
                    https://maps.google.com/?q={{ $r->latitude }},{{ $r->longitude }}
                @endif
            </td>
        </tr>
    @empty
        <tr>
            @for($i=0;$i<13;$i++) <td></td> @endfor
            <td>Sin registros</td>
        </tr>
    @endforelse

    {{-- Totales apoyo --}}
    <tr>
        <td></td><td></td><td></td><td></td><td></td><td></td>
        <td style="text-align:right">TOTAL</td>
        <td>{{ (int)($supTotals->times_total ?? 0) }}</td>
        <td>{{ (int)($supTotals->times_total ?? 0) }}</td>
        <td>{{ (float)($supTotals->price_total ?? 0) }}</td>
        <td>{{ (int)($supTotals->passengers_total ?? 0) }}</td>
        <td>{{ (float)($supTotals->passage_total ?? 0) }}</td>
        <td>{{ (float)($supTotals->total_pasaje_total ?? 0) }}</td>
        <td></td>
    </tr>

    {{-- Blanco --}}
    <tr>@for($i=0;$i<14;$i++) <td></td> @endfor</tr>

    {{-- Total General --}}
    <tr>
        <td></td><td></td><td></td><td></td><td></td><td></td>
        <td style="text-align:right">TOTAL GENERAL</td>
        <td>{{ (int)($grand->times_total ?? 0) }}</td>
        <td>{{ (int)($grand->times_total ?? 0) }}</td>
        <td>{{ (float)($grand->price_total ?? 0) }}</td>
        <td>{{ (int)($grand->passengers_total ?? 0) }}</td>
        <td>{{ (float)($grand->passage_total ?? 0) }}</td>
        <td>{{ (float)($grand->total_pasaje_total ?? 0) }}</td>
        <td></td>
    </tr>
</table>
