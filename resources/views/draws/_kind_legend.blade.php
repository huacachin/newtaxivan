{{-- Leyenda de colores del motivo según el tipo de bloqueo (DrawBlock::KIND_COLORS) y, en el
     historial ($withSanction), el de las sanciones. Estilos en línea: también se usa fuera de
     Sorteos (ficha del vehículo). --}}
@php
    $legend = collect(\App\Models\DrawBlock::KIND_NAMES)->map(fn (string $name, string $kind) => [$name, \App\Models\DrawBlock::KIND_COLORS[$kind]]);
    if ($withSanction ?? false) {
        $legend->put('sanction', [\App\Models\DrawSanction::LABEL, \App\Models\DrawSanction::COLOR]);
    }
@endphp
<div class="d-flex flex-wrap align-items-center gap-3 small mb-2">
    <span class="text-muted">Color del motivo según el tipo:</span>
    @foreach($legend as [$legendName, $legendColor])
        <span class="fw-semibold" style="color: {{ $legendColor }};">
            <span style="display: inline-block; width: 12px; height: 12px; border-radius: 3px; vertical-align: -1px; margin-right: 4px; background: {{ $legendColor }};"></span>{{ $legendName }}
        </span>
    @endforeach
</div>
