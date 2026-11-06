{{-- Historial de sanciones de una placa en Sorteos (DrawBlock::historyFor): cada bloqueo con
     su periodo, motivo, quién lo puso y cuándo, sus días desbloqueados y, si se levantó, quién,
     cuándo y por qué. Arriba, la placa con su Nº en la tabla de Sorteos ($rowNumber, si se sabe)
     y su COD. Se usa en el modal de Sorteos y en la ficha del vehículo. --}}
{{-- El tema corta las palabras dentro de las celdas; aquí no --}}
<style>.dr-sanctions th, .dr-sanctions td { word-break: normal; overflow-wrap: normal; } .dr-sanctions .dr-date { white-space: nowrap !important; }</style>
<div class="d-flex flex-wrap align-items-center gap-2 mb-2 small">
    @isset($rowNumber)
        <span class="badge bg-light text-dark border">Nº {{ $rowNumber }}</span>
    @endisset
    <span class="badge bg-light text-dark border">COD {{ $vehicle->sort_order ?? '—' }}</span>
    <span class="badge bg-dark">{{ $vehicle->plate }}</span>
    @if($vehicle->driver?->name)
        <span class="text-muted">{{ $vehicle->driver->name }}</span>
    @endif
    <span class="text-muted ms-auto">
        {{ $history->count() }} {{ $history->count() === 1 ? 'sanción registrada' : 'sanciones registradas' }}, de la más reciente a la más antigua.
    </span>
</div>
<div class="table-responsive">
    <table class="table table-sm table-bordered mb-0 small dr-sanctions">
        <thead>
        <tr><th>Fecha y hora</th><th>Usuario</th><th>Tipo</th><th>Periodo</th><th>Motivo</th><th>Estado</th></tr>
        </thead>
        <tbody>
        @forelse($history as $sanction)
            @php $status = $sanction->status(); @endphp
            <tr wire:key="sanction-{{ $sanction->id }}">
                <td class="dr-date">{{ $sanction->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ \App\Models\DrawBlock::userLabel($sanction->blockedBy) }}</td>
                <td>{{ \App\Models\DrawBlock::KIND_LABELS[$sanction->kind] ?? $sanction->kind }}</td>
                <td style="white-space: normal;">
                    {{ $sanction->periodLabel() }}
                    @foreach($sanction->exceptions as $exception)
                        @include('draws._unblocked_day', ['exception' => $exception])
                    @endforeach
                </td>
                <td style="white-space: normal;">{{ $sanction->reason }}</td>
                <td style="white-space: normal;">
                    @if($status === \App\Models\DrawBlock::STATUS_ACTIVE)
                        <span class="badge bg-danger">Vigente</span>
                    @elseif($status === \App\Models\DrawBlock::STATUS_EXPIRED)
                        <span class="badge bg-secondary">Cumplida</span>
                    @else
                        <span class="badge bg-light text-dark border">Levantada</span>
                        <div class="text-muted">{{ $sanction->unblocked_at->format('d/m/Y H:i') }} · {{ \App\Models\DrawBlock::userLabel($sanction->unblockedBy) }}</div>
                        @if($sanction->unblock_reason)
                            <div>{{ $sanction->unblock_reason }}</div>
                        @endif
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted">Esta placa no tiene sanciones registradas.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
