{{-- Historial de sanciones de una placa en Sorteos (DrawBlock::historyFor): cada bloqueo con
     su periodo, motivo, quién lo puso, sus días desbloqueados y, si se levantó, quién, cuándo
     y por qué. Se usa en el modal de Sorteos y en la ficha del vehículo. --}}
{{-- El tema corta las palabras dentro de las celdas; aquí no --}}
<style>.dr-sanctions th, .dr-sanctions td { word-break: normal; overflow-wrap: normal; }</style>
<p class="small text-muted mb-2">
    {{ $history->count() }} {{ $history->count() === 1 ? 'sanción registrada' : 'sanciones registradas' }}, de la más reciente a la más antigua.
</p>
<div class="table-responsive">
    <table class="table table-sm table-bordered mb-0 small dr-sanctions">
        <thead>
        <tr><th>Registrada</th><th>Tipo</th><th>Periodo</th><th>Motivo</th><th>Estado</th></tr>
        </thead>
        <tbody>
        @forelse($history as $sanction)
            @php $status = $sanction->status(); @endphp
            <tr wire:key="sanction-{{ $sanction->id }}">
                <td style="white-space: nowrap;">
                    {{ $sanction->created_at->format('d/m/Y H:i') }}
                    <div class="text-muted">{{ $sanction->blockedBy?->name ?? '—' }}</div>
                </td>
                <td>{{ \App\Models\DrawBlock::KIND_LABELS[$sanction->kind] ?? $sanction->kind }}</td>
                <td style="white-space: normal;">
                    {{ $sanction->periodLabel() }}
                    @foreach($sanction->exceptions as $exception)
                        <div class="text-success">
                            <i class="ti ti-lock-open"></i> {{ \App\Models\DrawBlock::dayLabel($exception->date->toDateString()) }} desbloqueado: {{ $exception->reason }}
                            <span class="text-muted">({{ $exception->createdBy?->name ?? '—' }}, {{ $exception->created_at->format('d/m H:i') }})</span>
                        </div>
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
                        <div class="text-muted">{{ $sanction->unblocked_at->format('d/m/Y H:i') }} · {{ $sanction->unblockedBy?->name ?? '—' }}</div>
                        @if($sanction->unblock_reason)
                            <div>{{ $sanction->unblock_reason }}</div>
                        @endif
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted">Esta placa no tiene sanciones registradas.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
