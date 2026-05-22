<div class="audit-snapshot-banner mb-3">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <i class="ti ti-alert-octagon audit-snapshot-banner__icon"></i>
        <strong>Registro eliminado.</strong>
        <span>
            Eliminado el
            <strong>{{ $log->created_at->format('d/m/Y H:i:s') }}</strong>
            por <strong>{{ $log->user_name ?? '—' }}</strong>{{ $log->user_role ? ' (' . __('roles.' . $log->user_role) . ')' : '' }}.
        </span>
        <span class="text-muted">Solo informativo.</span>
    </div>
</div>
