<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">REGISTRO DE AUDITORÍA</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-clipboard-list f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Auditoría</span></a>
                </li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-body pb-2">

            <!-- Filtros -->
            <div class="row g-2 mb-3">
                <div class="col-md-2">
                    <select class="form-select form-select-sm" wire:model="module">
                        <option value="">Todos los módulos</option>
                        @foreach($modules as $m)
                            <option value="{{ $m }}">{{ $m }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm" wire:model="action">
                        <option value="">Todas las acciones</option>
                        <option value="created">Creación</option>
                        <option value="updated">Edición</option>
                        <option value="deleted">Eliminación</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm" wire:model="userId">
                        <option value="">Todos los usuarios</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control form-control-sm" wire:model="dateFrom">
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control form-control-sm" wire:model="dateTo">
                </div>
                <div class="col-md-1">
                    <button class="btn btn-sm btn-primary w-100" wire:click="search">Buscar</button>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-sm btn-secondary w-100" wire:click="clearFilters">Limpiar</button>
                </div>
            </div>

            <!-- Tabla -->
            <div class="table-responsive tableFixHead">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="bg-primary">
                        <tr>
                            <th>Item</th>
                            <th>Fecha/Hora</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th></th>
                            <th>Módulo</th>
                            <th>Registro</th>
                            <th>Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td class="text-nowrap">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                                <td>{{ $log->user_name ?? '—' }}</td>
                                <td>{{ $log->user_role ? __('roles.' . $log->user_role) : '—' }}</td>
                                <td>
                                    @if($log->action === 'created')
                                        <span class="badge bg-success">Creación</span>
                                    @elseif($log->action === 'updated')
                                        <span class="badge bg-warning text-dark">Edición</span>
                                    @elseif($log->action === 'deleted')
                                        <span class="badge bg-danger">Eliminación</span>
                                    @endif
                                </td>
                                <td>{{ $log->module }}</td>
                                <td>#{{ $log->record_id }}</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" wire:click="showDetail({{ $log->id }})">
                                        <i class="ti ti-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-muted py-4">No se encontraron registros de auditoría</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-2">
                {{ $logs->links() }}
            </div>
        </div>
    </div>

    <!-- Modal Detalle -->
    @if($detail)
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5)">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        Detalle de Auditoría —
                        @if($detail['action'] === 'created') <span class="text-success">Creación</span>
                        @elseif($detail['action'] === 'updated') <span class="text-warning">Edición</span>
                        @elseif($detail['action'] === 'deleted') <span class="text-danger">Eliminación</span>
                        @endif
                    </h5>
                    <button type="button" class="btn-close" wire:click="closeDetail"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <small class="text-muted">Usuario</small>
                            <div class="fw-semibold">{{ $detail['user_name'] ?? '—' }} ({{ $detail['user_role'] ? __('roles.' . $detail['user_role']) : '—' }})</div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Módulo</small>
                            <div class="fw-semibold">{{ $detail['module'] }}</div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Registro</small>
                            <div class="fw-semibold">#{{ $detail['record_id'] }}</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <small class="text-muted">Fecha</small>
                            <div>{{ \Carbon\Carbon::parse($detail['created_at'])->format('d/m/Y H:i:s') }}</div>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">IP</small>
                            <div>{{ $detail['ip_address'] ?? '—' }}</div>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Navegador</small>
                            <div class="text-truncate" title="{{ $detail['user_agent'] ?? '' }}">{{ \Illuminate\Support\Str::limit($detail['user_agent'] ?? '—', 50) }}</div>
                        </div>
                    </div>

                    <hr>

                    @if($detail['action'] === 'created')
                        <h6>Datos creados</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead><tr><th>Campo</th><th>Valor</th></tr></thead>
                                <tbody>
                                    @foreach($detail['new_data'] ?? [] as $field => $value)
                                        <tr @if($field === 'vehicle_id') style="background:#dc3545;color:#fff" @endif>
                                            <td class="fw-semibold">{{ $field }}</td>
                                            <td>{{ is_array($value) ? json_encode($value) : ($value ?? '—') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @elseif($detail['action'] === 'updated')
                        <h6>Campos modificados</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead><tr><th>Campo</th><th>Antes</th><th>Después</th></tr></thead>
                                <tbody>
                                    @foreach($detail['changed_fields'] ?? [] as $field)
                                        <tr @if($field === 'vehicle_id') style="background:#dc3545;color:#fff" @else class="table-warning" @endif>
                                            <td class="fw-semibold">{{ $field }}</td>
                                            <td>{{ is_array($detail['old_data'][$field] ?? null) ? json_encode($detail['old_data'][$field]) : ($detail['old_data'][$field] ?? '—') }}</td>
                                            <td>{{ is_array($detail['new_data'][$field] ?? null) ? json_encode($detail['new_data'][$field]) : ($detail['new_data'][$field] ?? '—') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @elseif($detail['action'] === 'deleted')
                        <h6>Datos eliminados</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead><tr><th>Campo</th><th>Valor</th></tr></thead>
                                <tbody>
                                    @foreach($detail['old_data'] ?? [] as $field => $value)
                                        <tr @if($field === 'vehicle_id') style="background:#dc3545;color:#fff" @endif>
                                            <td class="fw-semibold">{{ $field }}</td>
                                            <td>{{ is_array($value) ? json_encode($value) : ($value ?? '—') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button class="btn btn-sm btn-secondary" wire:click="closeDetail">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
