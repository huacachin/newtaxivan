{{-- Historial de sanciones de una placa en Sorteos: sus bloqueos (DrawBlock::historyFor, con
     periodo, motivo, quién y cuándo, días desbloqueados y si se levantó) y sus sanciones
     (DrawSanction::historyFor, con motivo, foto y si ya generaron una suspensión), todo junto
     del más nuevo al más viejo. Arriba, la placa con su COD; el Nº de cada fila es correlativo.
     Se usa en el modal de Sorteos (grilla y celular) y en la ficha del vehículo. --}}
@use('App\Models\DrawBlock')
@use('App\Models\DrawSanction')
@php
    $sanctions = DrawSanction::historyFor($vehicle->id);
    // Posición de cada sanción acumulada (1 de 2…), de la más vieja a la más nueva
    $pendingPosition = $sanctions->whereNull('draw_block_id')->sortBy('id')->values()->pluck('id')->flip()->map(fn (int $i) => $i + 1);
    // Juntas por fecha; en el mismo segundo, cada suspensión va justo encima de la sanción que la generó
    $triggeredBy = $sanctions->whereNotNull('draw_block_id')->groupBy('draw_block_id')->map(fn ($group) => $group->max('id'));
    $records = $history->map(fn (DrawBlock $block) => ['block' => $block, 'sanction' => null, 'at' => $block->created_at,
            'seq' => isset($triggeredBy[$block->id]) ? $triggeredBy[$block->id] * 2 + 1 : 0])
        ->concat($sanctions->map(fn (DrawSanction $sanction) => ['block' => null, 'sanction' => $sanction, 'at' => $sanction->created_at, 'seq' => $sanction->id * 2]))
        ->sort(fn (array $a, array $b) => [$b['at']->timestamp, $b['seq']] <=> [$a['at']->timestamp, $a['seq']])
        ->values();
@endphp
{{-- El tema corta las palabras dentro de las celdas; aquí no --}}
<style>.dr-sanctions th, .dr-sanctions td { word-break: normal; overflow-wrap: normal; } .dr-sanctions .dr-date { white-space: nowrap !important; }
    .dr-sanctions .dr-photo { display: inline-block; margin-top: 4px; } .dr-sanctions .dr-photo img { width: 72px; height: 54px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; }</style>
<div class="d-flex flex-wrap align-items-center gap-2 mb-2 small">
    <span class="badge bg-light text-dark border">COD {{ $vehicle->sort_order ?? '—' }}</span>
    <span class="badge bg-dark">{{ $vehicle->plate }}</span>
    @if($vehicle->driver?->name)
        <span class="text-muted" title="{{ $vehicle->driver->name }}">{{ \App\Services\DrawService::firstName($vehicle->driver->name) }}</span>
    @endif
    <span class="text-muted ms-auto">
        {{ $history->count() }} {{ $history->count() === 1 ? 'bloqueo' : 'bloqueos' }} y {{ $sanctions->count() }} {{ $sanctions->count() === 1 ? 'sanción' : 'sanciones' }}, de lo más reciente a lo más antiguo.
    </span>
</div>
@if($pendingPosition->isNotEmpty())
    <div class="alert alert-warning small py-2 mb-2">
        <i class="ti ti-alert-triangle"></i> Tiene <b>{{ $pendingPosition->count() }} de {{ DrawSanction::SANCTIONS_TO_SUSPEND }}</b> sanciones acumuladas:
        con {{ DrawSanction::SANCTIONS_TO_SUSPEND - $pendingPosition->count() }} más se suspende {{ DrawSanction::SUSPENSION_DAYS }} días de sorteo.
    </div>
@endif
@include('draws._kind_legend', ['withSanction' => true])
<div class="table-responsive">
    <table class="table table-sm table-bordered mb-0 small dr-sanctions">
        <thead>
        <tr><th>Nº</th><th>Fecha y hora</th><th>Usuario</th><th>Tipo</th><th>Periodo</th><th>Motivo</th><th>Estado</th></tr>
        </thead>
        <tbody>
        @forelse($records as $record)
            @if($sanction = $record['sanction'])
                <tr wire:key="draw-sanction-{{ $sanction->id }}">
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td class="dr-date">{{ $sanction->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ DrawBlock::userLabel($sanction->createdBy) }}</td>
                    <td><i class="ti ti-gavel"></i> {{ DrawSanction::LABEL }}</td>
                    <td style="white-space: normal;">
                        @if($sanction->block)
                            Generó la suspensión: {{ $sanction->block->periodLabel() }}
                        @else
                            —
                        @endif
                    </td>
                    <td style="white-space: normal;">
                        <span class="fw-semibold" style="color: {{ DrawSanction::COLOR }};">{{ $sanction->reason }}</span>
                        @if($url = $sanction->photoUrl())
                            <div><a href="{{ $url }}" target="_blank" rel="noopener" class="dr-photo" title="Ver foto"><img src="{{ $url }}" alt="Foto de la sanción" loading="lazy"></a></div>
                        @endif
                    </td>
                    <td style="white-space: normal;">
                        @if($sanction->draw_block_id)
                            <span class="badge" style="background: {{ DrawBlock::KIND_COLORS[DrawBlock::KIND_SUSPENSION] }};">Suspendida</span>
                        @elseif(isset($pendingPosition[$sanction->id]))
                            <span class="badge bg-warning text-dark">Acumulada {{ $pendingPosition[$sanction->id] }} de {{ DrawSanction::SANCTIONS_TO_SUSPEND }}</span>
                        @endif
                    </td>
                </tr>
            @else
                @php $block = $record['block']; $status = $block->status(); @endphp
                <tr wire:key="sanction-{{ $block->id }}">
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td class="dr-date">{{ $block->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ DrawBlock::userLabel($block->blockedBy) }}</td>
                    <td>{{ $block->kindName() }}</td>
                    <td style="white-space: normal;">
                        {{ $block->periodLabel() }}
                        @foreach($block->exceptions as $exception)
                            @include('draws._unblocked_day', ['exception' => $exception])
                        @endforeach
                    </td>
                    <td class="fw-semibold" style="white-space: normal; color: {{ $block->kindColor() }};">{{ $block->reason }}</td>
                    <td style="white-space: normal;">
                        @if($status === DrawBlock::STATUS_ACTIVE)
                            <span class="badge bg-danger">Vigente</span>
                        @elseif($status === DrawBlock::STATUS_EXPIRED)
                            <span class="badge bg-secondary">Cumplida</span>
                        @else
                            <span class="badge bg-light text-dark border">Levantada</span>
                            <div class="text-muted">{{ $block->unblocked_at->format('d/m/Y H:i') }} · {{ DrawBlock::userLabel($block->unblockedBy) }}</div>
                            @if($block->unblock_reason)
                                <div>{{ $block->unblock_reason }}</div>
                            @endif
                        @endif
                    </td>
                </tr>
            @endif
        @empty
            <tr><td colspan="7" class="text-center text-muted">Esta placa no tiene bloqueos ni sanciones registradas.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
