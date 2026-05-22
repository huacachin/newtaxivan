@push('datepicker_css')
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>
@endpush

<div class="container-fluid">
    @switch($moduleLabel)

        @case('Vehículos')
            <div class="row">
                <div class="col-sm-6">
                    <h4 class="main-title title-modules">VEHICULO: REGISTRO ELIMINADO</h4>
                </div>
                <div class="col-sm-6 mt-sm-2">
                    <ul class="breadcrumb breadcrumb-start float-sm-end">
                        <li class="d-flex">
                            <i class="ti ti-settings f-s-16"></i>
                            <a href="{{ route('settings.vehicles.index') }}" class="f-s-14 d-flex gap-2">
                                <span class="d-none d-md-block">Vehículos</span>
                            </a>
                        </li>
                        <li class="d-flex active">
                            <a href="#" class="f-s-14">Eliminado #{{ $log->record_id }}</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
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

                    <fieldset disabled class="audit-snapshot-readonly">
                        @include('livewire.vehicles._form')
                    </fieldset>

                    <div class="mt-3 d-flex gap-2 justify-content-end">
                        <a href="{{ route('audit.logs.index') }}" class="btn btn-sm btn-secondary">
                            <i class="ti ti-arrow-back-up"></i> Regresar
                        </a>
                    </div>
                </div>
            </div>
            @break

        @case('Conductores')
            <div class="row">
                <div class="col-sm-6">
                    <h4 class="main-title title-modules">CONDUCTOR: REGISTRO ELIMINADO</h4>
                </div>
                <div class="col-sm-6 mt-sm-2">
                    <ul class="breadcrumb breadcrumb-start float-sm-end">
                        <li class="d-flex">
                            <i class="ti ti-settings f-s-16"></i>
                            <a href="{{ route('settings.drivers.index') }}" class="f-s-14 d-flex gap-2">
                                <span class="d-none d-md-block">Conductores</span>
                            </a>
                        </li>
                        <li class="d-flex active">
                            <a href="#" class="f-s-14">Eliminado #{{ $log->record_id }}</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
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

                    <fieldset disabled class="audit-snapshot-readonly">
                        @php $highlightExpiration = false; @endphp
                        @include('livewire.drivers._form')
                    </fieldset>

                    <div class="mt-3 d-flex gap-2 justify-content-end">
                        <a href="{{ route('audit.logs.index') }}" class="btn btn-sm btn-secondary">
                            <i class="ti ti-arrow-back-up"></i> Regresar
                        </a>
                    </div>
                </div>
            </div>
            @break

        @case('Propietarios')
            <div class="row">
                <div class="col-sm-6">
                    <h4 class="main-title title-modules">PROPIETARIO: REGISTRO ELIMINADO</h4>
                </div>
                <div class="col-sm-6 mt-sm-2">
                    <ul class="breadcrumb breadcrumb-start float-sm-end">
                        <li class="d-flex">
                            <i class="ti ti-settings f-s-16"></i>
                            <a href="{{ route('settings.owners.index') }}" class="f-s-14 d-flex gap-2">
                                <span class="d-none d-md-block">Propietarios</span>
                            </a>
                        </li>
                        <li class="d-flex active">
                            <a href="#" class="f-s-14">Eliminado #{{ $log->record_id }}</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
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

                    <fieldset disabled class="audit-snapshot-readonly">
                        @php $highlightExpiration = false; @endphp
                        @include('livewire.owners._form')
                    </fieldset>

                    <div class="mt-3 d-flex gap-2 justify-content-end">
                        <a href="{{ route('audit.logs.index') }}" class="btn btn-sm btn-secondary">
                            <i class="ti ti-arrow-back-up"></i> Regresar
                        </a>
                    </div>
                </div>
            </div>
            @break

        @case('Conceptos')
            <div class="row">
                <div class="col-sm-6">
                    <h4 class="main-title title-modules">CONCEPTOS : REGISTRO ELIMINADO</h4>
                </div>
                <div class="col-sm-6 mt-sm-2">
                    <ul class="breadcrumb breadcrumb-start float-sm-end">
                        <li class="d-flex">
                            <i class="ti ti-settings f-s-16"></i>
                            <a href="{{ route('settings.concepts.index') }}" class="f-s-14 d-flex gap-2">
                                <span class="d-none d-md-block">Conceptos</span>
                            </a>
                        </li>
                        <li class="d-flex active">
                            <span class="f-s-14">Eliminado #{{ $log->record_id }}</span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="row table-section">
                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-body">
                            @include('partials._audit-snapshot-bar', ['log' => $log])
                            <fieldset disabled class="audit-snapshot-readonly">
                                <div class="row">
                                    <div class="col-md-auto col-sm-12">
                                        <div class="mb-3">
                                            <label class="form-label">Orden (*)</label>
                                            <input type="number" class="form-control form-control-sm" value="{{ $data['sort_order'] ?? '' }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Nombre del Concepto (*)</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $data['name'] ?? '' }}">
                                        </div>
                                    </div>
                                    <div class="col-md-auto">
                                        <div class="mb-3">
                                            <label class="form-label">Estado (*)</label>
                                            <select class="form-select form-select-sm">
                                                <option value="inactive" {{ ($data['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Cancelado</option>
                                                <option value="active" {{ ($data['status'] ?? '') === 'active' ? 'selected' : '' }}>Vigente</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-auto">
                                        <div class="mb-3">
                                            <label class="form-label">Tipo (*)</label>
                                            <select class="form-select form-select-sm">
                                                <option value="ingreso" {{ ($data['type'] ?? '') === 'ingreso' ? 'selected' : '' }}>Ingreso</option>
                                                <option value="egreso" {{ ($data['type'] ?? '') === 'egreso' ? 'selected' : '' }}>Egreso</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </fieldset>
                            <div class="d-flex gap-2">
                                <a href="{{ route('audit.logs.index') }}" class="btn btn-sm btn-secondary">
                                    <i class="ti ti-arrow-back-up"></i> Regresar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @break

        @case('Sucursales')
            <div class="row">
                <div class="col-sm-6">
                    <h4 class="main-title title-modules">SUCURSALES : REGISTRO ELIMINADO</h4>
                </div>
                <div class="col-sm-6 mt-sm-2">
                    <ul class="breadcrumb breadcrumb-start float-sm-end">
                        <li class="d-flex">
                            <i class="ti ti-settings f-s-16"></i>
                            <a href="{{ route('settings.headquarters.index') }}" class="f-s-14 d-flex gap-2">
                                <span class="d-none d-md-block">Sucursales</span>
                            </a>
                        </li>
                        <li class="d-flex active">
                            <span class="f-s-14">Eliminado #{{ $log->record_id }}</span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="row table-section">
                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-body">
                            @include('partials._audit-snapshot-bar', ['log' => $log])
                            <fieldset disabled class="audit-snapshot-readonly">
                                <div class="row">
                                    <div class="col-md-auto">
                                        <div class="mb-3">
                                            <label class="form-label">Orden (*)</label>
                                            <input type="number" class="form-control form-control-sm" value="{{ $data['sort_order'] ?? '' }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Nombre (*)</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $data['name'] ?? '' }}">
                                        </div>
                                    </div>
                                    <div class="col-md-auto">
                                        <div class="mb-3">
                                            <label class="form-label">Estado (*)</label>
                                            <select class="form-select form-select-sm">
                                                <option value="active" {{ ($data['status'] ?? '') === 'active' ? 'selected' : '' }}>Activo</option>
                                                <option value="inactive" {{ ($data['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Inactivo</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </fieldset>
                            <div class="d-flex gap-2">
                                <a href="{{ route('audit.logs.index') }}" class="btn btn-sm btn-secondary">
                                    <i class="ti ti-arrow-back-up"></i> Regresar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @break

        @case('Salidas')
            <div class="row">
                <div class="col-sm-6">
                    <h4 class="main-title title-modules">SALIDA: REGISTRO ELIMINADO</h4>
                </div>
                <div class="col-sm-6 mt-sm-2">
                    <ul class="breadcrumb breadcrumb-start float-sm-end">
                        <li class="d-flex">
                            <i class="ti ti-door-exit f-s-16"></i>
                            <a href="{{ route('departures.index') }}" class="f-s-14 d-flex gap-2">
                                <span class="d-none d-md-block">Salidas</span>
                            </a>
                        </li>
                        <li class="d-flex active">
                            <span class="f-s-14">Eliminado #{{ $log->record_id }}</span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="row table-section">
                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-body">
                            @include('partials._audit-snapshot-bar', ['log' => $log])
                            <fieldset disabled class="audit-snapshot-readonly">
                                <div class="row">
                                    <div class="col-md-2 col-12">
                                        <div class="mb-3">
                                            <label class="form-label">Placa</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $data['legacy_plate'] ?? '' }}">
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-12">
                                        <div class="mb-3">
                                            <label class="form-label">Fecha</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $this->valueFor('date', $data['date'] ?? null) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-12">
                                        <div class="mb-3">
                                            <label class="form-label">Hora</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $data['hour'] ?? '' }}">
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-12">
                                        <div class="mb-3">
                                            <label class="form-label">Sucursal</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $this->valueFor('headquarter_id', $data['headquarter_id'] ?? null) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-12">
                                        <div class="mb-3">
                                            <label class="form-label">Salida (S/)</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $data['price'] ?? '' }}">
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-12">
                                        <div class="mb-3">
                                            <label class="form-label">Pasajeros</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $data['passenger'] ?? '' }}">
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-12">
                                        <div class="mb-3">
                                            <label class="form-label">Pasaje (S/)</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $data['passage'] ?? '' }}">
                                        </div>
                                    </div>
                                </div>
                            </fieldset>
                            <div class="d-flex gap-2">
                                <a href="{{ route('audit.logs.index') }}" class="btn btn-sm btn-secondary">
                                    <i class="ti ti-arrow-back-up"></i> Regresar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @break

        @case('Pagos')
            <div class="row">
                <div class="col-sm-6">
                    <h4 class="main-title title-modules">PAGOS : REGISTRO ELIMINADO</h4>
                </div>
                <div class="col-sm-6 mt-sm-2">
                    <ul class="breadcrumb breadcrumb-start float-sm-end">
                        <li class="d-flex">
                            <i class="ti ti-currency-dollar f-s-16"></i>
                            <a href="{{ route('payments.index') }}" class="f-s-14 d-flex gap-2">
                                <span class="d-none d-md-block">Pagos</span>
                            </a>
                        </li>
                        <li class="d-flex active">
                            <span class="f-s-14">Eliminado #{{ $log->record_id }}</span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="row table-section">
                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-body">
                            @include('partials._audit-snapshot-bar', ['log' => $log])
                            <fieldset disabled class="audit-snapshot-readonly">
                                <div class="row">
                                    <div class="col-md-auto col-sm-12">
                                        <div class="mb-3">
                                            <label class="form-label">Placa (*)</label>
                                            <input type="text" class="form-control form-control-sm input-uppercase" value="{{ $data['legacy_plate'] ?? '' }}">
                                        </div>
                                    </div>
                                    <div class="col-md-auto">
                                        <div class="mb-3">
                                            <label class="form-label">Serie (*)</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $data['serie'] ?? '' }}">
                                        </div>
                                    </div>
                                    <div class="col-md-auto">
                                        <div class="mb-3">
                                            <label class="form-label">Fecha Reg. (*)</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $this->valueFor('date_register', $data['date_register'] ?? null) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-auto">
                                        <div class="mb-3">
                                            <label class="form-label">Fecha Pago (*)</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $this->valueFor('date_payment', $data['date_payment'] ?? null) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-auto">
                                        <div class="mb-3">
                                            <label class="form-label">Tipo (*)</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $data['type'] ?? '' }}">
                                        </div>
                                    </div>
                                    <div class="col-md-auto">
                                        <div class="mb-3">
                                            <label class="form-label">Monto (*)</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $data['amount'] ?? '' }}">
                                        </div>
                                    </div>
                                    <div class="col-md-auto">
                                        <div class="mb-3">
                                            <label class="form-label">Sucursal (*)</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $this->valueFor('headquarter_id', $data['headquarter_id'] ?? null) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-auto">
                                        <div class="mb-3">
                                            <label class="form-label">Usuario</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $this->valueFor('user_id', $data['user_id'] ?? null) }}">
                                        </div>
                                    </div>
                                </div>
                            </fieldset>
                            <div class="d-flex gap-2">
                                <a href="{{ route('audit.logs.index') }}" class="btn btn-sm btn-secondary">
                                    <i class="ti ti-arrow-back-up"></i> Regresar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @break

        @case('Ingresos')
            <div class="row">
                <div class="col-sm-6">
                    <h4 class="main-title title-modules">INGRESO: REGISTRO ELIMINADO</h4>
                </div>
                <div class="col-sm-6 mt-sm-2">
                    <ul class="breadcrumb breadcrumb-start float-sm-end">
                        <li class="d-flex">
                            <i class="ti ti-cash f-s-16"></i>
                            <a href="{{ route('cash.incomes') }}" class="f-s-14 d-flex gap-2">
                                <span class="d-none d-md-block">Ingresos</span>
                            </a>
                        </li>
                        <li class="d-flex active">
                            <span class="f-s-14">Eliminado #{{ $log->record_id }}</span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="row table-section">
                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-body">
                            @include('partials._audit-snapshot-bar', ['log' => $log])
                            <fieldset disabled class="audit-snapshot-readonly">
                                <div class="row">
                                    <div class="col-md-auto col-sm-12">
                                        <div class="mb-3">
                                            <label class="form-label">Fecha (*)</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $this->valueFor('date', $data['date'] ?? null) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-auto">
                                        <div class="mb-3">
                                            <label class="form-label">Monto (*)</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $data['total'] ?? '' }}">
                                        </div>
                                    </div>
                                    <div class="col-md-auto">
                                        <div class="mb-3">
                                            <label class="form-label">A (*)</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $data['reason'] ?? '' }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Motivo (*)</label>
                                            <textarea class="form-control form-control-sm" rows="2">{{ $data['detail'] ?? '' }}</textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-auto">
                                        <div class="mb-3">
                                            <label class="form-label">Usuario</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $this->valueFor('user_id', $data['user_id'] ?? null) }}">
                                        </div>
                                    </div>
                                    <div class="w-100"></div>
                                    <div class="col-md-10">
                                        <div class="mb-3">
                                            <label class="form-label">Comprobantes (imágenes)</label>
                                            <div class="audit-snapshot-photos-notice">
                                                📷 Comprobantes eliminados en cascada (no disponibles)
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </fieldset>
                            <div class="d-flex gap-2">
                                <a href="{{ route('audit.logs.index') }}" class="btn btn-sm btn-secondary">
                                    <i class="ti ti-arrow-back-up"></i> Regresar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @break

        @case('Egresos')
            <div class="row">
                <div class="col-sm-6">
                    <h4 class="main-title title-modules">EGRESO: REGISTRO ELIMINADO</h4>
                </div>
                <div class="col-sm-6 mt-sm-2">
                    <ul class="breadcrumb breadcrumb-start float-sm-end">
                        <li class="d-flex">
                            <i class="ti ti-cash-banknote-off f-s-16"></i>
                            <a href="{{ route('cash.expenses') }}" class="f-s-14 d-flex gap-2">
                                <span class="d-none d-md-block">Egresos</span>
                            </a>
                        </li>
                        <li class="d-flex active">
                            <span class="f-s-14">Eliminado #{{ $log->record_id }}</span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="row table-section">
                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-body">
                            @include('partials._audit-snapshot-bar', ['log' => $log])
                            <fieldset disabled class="audit-snapshot-readonly">
                                <div class="row">
                                    <div class="col-md-auto col-sm-12">
                                        <div class="mb-3">
                                            <label class="form-label">Fecha (*)</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $this->valueFor('date', $data['date'] ?? null) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-auto">
                                        <div class="mb-3">
                                            <label class="form-label">Tipo de egreso (*)</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $data['kind'] ?? '' }}">
                                        </div>
                                    </div>
                                    <div class="col-md-auto">
                                        <div class="mb-3">
                                            <label class="form-label">A (*)</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $data['reason'] ?? '' }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Motivo / Detalle (*)</label>
                                            <textarea class="form-control form-control-sm" rows="2">{{ $data['detail'] ?? '' }}</textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-auto">
                                        <div class="mb-3">
                                            <label class="form-label">Monto (S/) (*)</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $data['total'] ?? '' }}">
                                        </div>
                                    </div>
                                    <div class="col-md-auto">
                                        <div class="mb-3">
                                            <label class="form-label">Sucursal</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $this->valueFor('headquarter_id', $data['headquarter_id'] ?? null) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-auto">
                                        <div class="mb-3">
                                            <label class="form-label">Responsable</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $this->valueFor('user_id', $data['user_id'] ?? null) }}">
                                        </div>
                                    </div>
                                    <div class="w-100"></div>
                                    <div class="col-md-10">
                                        <div class="mb-3">
                                            <label class="form-label">Comprobantes (imágenes)</label>
                                            <div class="audit-snapshot-photos-notice">
                                                📷 Comprobantes eliminados en cascada (no disponibles)
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </fieldset>
                            <div class="d-flex gap-2">
                                <a href="{{ route('audit.logs.index') }}" class="btn btn-sm btn-secondary">
                                    <i class="ti ti-arrow-back-up"></i> Regresar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @break

        @case('Usuarios')
            <div class="row">
                <div class="col-sm-6">
                    <h4 class="main-title title-modules">USUARIO: REGISTRO ELIMINADO</h4>
                </div>
                <div class="col-sm-6 mt-sm-2">
                    <ul class="breadcrumb breadcrumb-start float-sm-end">
                        <li class="d-flex">
                            <i class="ti ti-settings f-s-16"></i>
                            <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Configuración</span></a>
                        </li>
                        <li class="d-flex">
                            <a href="{{ route('settings.users.index') }}" class="f-s-14">Usuarios</a>
                        </li>
                        <li class="d-flex active">
                            <span class="f-s-14">Eliminado #{{ $log->record_id }}</span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    @include('partials._audit-snapshot-bar', ['log' => $log])
                    <fieldset disabled class="audit-snapshot-readonly">
                        <div class="perm-grid form-two-cols">
                            <div class="perm-row">
                                <div class="perm-col-title">Nombre</div>
                                <div class="perm-col-controls">
                                    <input type="text" class="form-control form-control-sm" value="{{ $data['name'] ?? '' }}">
                                </div>
                            </div>
                            <div class="perm-row">
                                <div class="perm-col-title">Usuario</div>
                                <div class="perm-col-controls">
                                    <input type="text" class="form-control form-control-sm" value="{{ $data['username'] ?? '' }}">
                                </div>
                            </div>
                            <div class="perm-row">
                                <div class="perm-col-title">Contraseña</div>
                                <div class="perm-col-controls">
                                    <input type="text" class="form-control form-control-sm audit-snapshot-masked" value="•••••• (enmascarado)">
                                </div>
                            </div>
                            <div class="perm-row">
                                <div class="perm-col-title">Email</div>
                                <div class="perm-col-controls">
                                    <input type="email" class="form-control form-control-sm" value="{{ $data['email'] ?? '' }}">
                                </div>
                            </div>
                            <div class="perm-row">
                                <div class="perm-col-title">Tipo Documento</div>
                                <div class="perm-col-controls">
                                    <input type="text" class="form-control form-control-sm" value="{{ strtoupper($data['document_type'] ?? '') }}">
                                </div>
                            </div>
                            <div class="perm-row">
                                <div class="perm-col-title">N° Documento</div>
                                <div class="perm-col-controls">
                                    <input type="text" class="form-control form-control-sm" value="{{ $data['document_number'] ?? '' }}">
                                </div>
                            </div>
                            <div class="perm-row">
                                <div class="perm-col-title">Teléfono</div>
                                <div class="perm-col-controls">
                                    <input type="text" class="form-control form-control-sm" value="{{ $data['phone'] ?? '' }}">
                                </div>
                            </div>
                            <div class="perm-row">
                                <div class="perm-col-title">Orden</div>
                                <div class="perm-col-controls">
                                    <input type="number" class="form-control form-control-sm" value="{{ $data['sort_order'] ?? '' }}">
                                </div>
                            </div>
                            <div class="perm-row span-2">
                                <div class="perm-col-title">Sucursal primaria</div>
                                <div class="perm-col-controls">
                                    <input type="text" class="form-control form-control-sm" value="{{ $this->valueFor('headquarter_id', $data['headquarter_id'] ?? null) }}">
                                </div>
                            </div>
                            <div class="perm-row span-2">
                                <div class="perm-col-title">Estado</div>
                                <div class="perm-col-controls">
                                    <input type="text" class="form-control form-control-sm" value="{{ $data['status'] ?? '' }}">
                                </div>
                            </div>
                        </div>
                    </fieldset>
                    <div class="mt-2 d-flex gap-2">
                        <a class="btn btn-secondary btn-sm" href="{{ route('audit.logs.index') }}">
                            <i class="ti ti-arrow-back-up"></i> Regresar
                        </a>
                    </div>
                </div>
            </div>
            @break

        @default
            {{-- Fallback final por si aparece algun modulo nuevo no listado. --}}
            <div class="row">
                <div class="col-sm-6">
                    <h4 class="main-title title-modules">
                        {{ mb_strtoupper($moduleLabel) }}: REGISTRO ELIMINADO
                    </h4>
                </div>
                <div class="col-sm-6 mt-sm-2">
                    <ul class="breadcrumb breadcrumb-start float-sm-end">
                        <li class="d-flex">
                            <i class="ti ti-clipboard-list f-s-16"></i>
                            <a href="{{ route('audit.logs.index') }}" class="f-s-14 d-flex gap-2">
                                <span class="d-none d-md-block">Auditoría</span>
                            </a>
                        </li>
                        <li class="d-flex active">
                            <a href="#" class="f-s-14">Eliminado #{{ $log->record_id }}</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
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

                    @if(empty($data))
                        <div class="text-muted py-3">No hay datos para mostrar.</div>
                    @else
                        <div class="row g-3 audit-snapshot-readonly">
                            @foreach($data as $field => $value)
                                @php
                                    $long = $this->isLongText($field, $value);
                                    $colClass = $long ? 'col-12' : 'col-12 col-md-4';
                                    $valStr = $this->valueFor($field, $value);
                                    $isMasked = $valStr === '***';
                                    $isImage = in_array($field, ['image_path', 'photo', 'image'], true);
                                @endphp
                                <div class="{{ $colClass }}">
                                    <label class="form-label">{{ $this->labelFor($field) }}</label>
                                    @if($long)
                                        <textarea class="form-control form-control-sm" rows="3" disabled>{{ $valStr }}</textarea>
                                    @elseif($isMasked)
                                        <input type="text" class="form-control form-control-sm audit-snapshot-masked"
                                               value="•••••• (enmascarado)" disabled>
                                    @elseif($isImage)
                                        <input type="text" class="form-control form-control-sm"
                                               value="(imagen eliminada en cascada)" disabled>
                                    @else
                                        <input type="text" class="form-control form-control-sm"
                                               value="{{ $valStr }}" disabled>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-3 d-flex gap-2 justify-content-end">
                        <a href="{{ route('audit.logs.index') }}" class="btn btn-sm btn-secondary">
                            <i class="ti ti-arrow-back-up"></i> Regresar
                        </a>
                    </div>
                </div>
            </div>
    @endswitch
</div>

@push('datepicker_js')
    <script>
        // En snapshot no inicializamos datepicker (inputs estan disabled), pero
        // jQuery/jQuery UI quedaron cargados para que los inputs text se rendericen
        // igual que en el edit.
    </script>
@endpush
