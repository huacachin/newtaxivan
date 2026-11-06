{{-- Leyenda de colores del motivo según el tipo de bloqueo (DrawBlock::KIND_COLORS).
     Estilos en línea: también se usa fuera de Sorteos (ficha del vehículo). --}}
<div class="d-flex flex-wrap align-items-center gap-3 small mb-2">
    <span class="text-muted">Color del motivo según el tipo:</span>
    @foreach(\App\Models\DrawBlock::KIND_LABELS as $kind => $kindLabel)
        <span class="fw-semibold" style="color: {{ \App\Models\DrawBlock::KIND_COLORS[$kind] }};">
            <span style="display: inline-block; width: 12px; height: 12px; border-radius: 3px; vertical-align: -1px; margin-right: 4px; background: {{ \App\Models\DrawBlock::KIND_COLORS[$kind] }};"></span>{{ $kindLabel }}
        </span>
    @endforeach
</div>
