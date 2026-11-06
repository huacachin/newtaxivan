{{-- Un día desbloqueado dentro de un bloqueo (DrawBlockException): qué día, quién, cuándo y por qué.
     Se usa en la lista de Placas bloqueadas y en el historial de sanciones. --}}
<div class="text-success mt-1">
    <i class="ti ti-lock-open"></i> <b>{{ \App\Models\DrawBlock::dayLabel($exception->date->toDateString()) }}</b> desbloqueado
    por <b>{{ $exception->createdBy?->name ?? '—' }}</b>
    <span class="text-muted">el {{ $exception->created_at->format('d/m/Y H:i') }}</span>: {{ $exception->reason }}
</div>
