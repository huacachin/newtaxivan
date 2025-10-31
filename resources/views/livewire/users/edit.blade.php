@push('styles')
    <style>
        table { border-collapse: collapse; width: 100%; }
        th,td{ padding:1px !important; font-size:10px !important; text-align:center !important; vertical-align:middle; }
        .btn, input,select { font-size:10px !important; }

        .perm-grid { display:flex; flex-direction:column; gap:6px; }
        .perm-row{
            display:grid; grid-template-columns:180px 1fr; gap:8px; align-items:center;
            padding:6px 8px; border-radius:8px; background:#fff; border:1px solid #eee; margin-bottom:6px;
        }
        .perm-col-title{ font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .perm-col-controls{ display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
        .perm-chips{ display:flex; gap:6px; flex-wrap:wrap; }
        .chip-radio,.chip-hq,.chip-check{
            display:inline-flex; align-items:center; gap:6px; padding:4px 8px; border:1px solid #e5e7eb; border-radius:999px; background:#fff; cursor:pointer;
        }
        .chip-hq .hq-primary{ display:inline-flex; align-items:center; gap:4px; padding-left:6px; margin-left:6px; border-left:1px dashed #e5e7eb; font-size:11px; }
        .chip-hq.is-default{ border-color:#60a5fa; background:#f0f9ff; }
        .chip-hq.is-selected{ border-color:#a7f3d0; background:#ecfdf5; }

        /* 2 columnas en móvil */
        .form-two-cols{ display:grid; grid-template-columns:1fr 1fr; gap:6px; }
        .form-two-cols .span-2{ grid-column:1 / -1; }

        @media (max-width:576px){
            .perm-row{ grid-template-columns:1fr; padding:6px; }
            .perm-col-title{ margin-bottom:2px; }
            .chip-radio,.chip-hq,.chip-check{ padding:3px 6px; }
        }
    </style>
@endpush

<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6"><h4 class="main-title">Editar Usuario</h4></div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Configuración</span></a>
                </li>
                <li class="d-flex"><a href="{{ route('settings.users.index') }}" class="f-s-14">Usuarios</a></li>
                <li class="d-flex active"><a class="f-s-14">Editar</a></li>
            </ul>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-2">
            <div class="perm-grid form-two-cols">
                {{-- Nombre --}}
                <div class="perm-row">
                    <div class="perm-col-title">Nombre</div>
                    <div class="perm-col-controls">
                        <input type="text" class="form-control" placeholder="Ingresar nombre" wire:model.live="name">
                        @error('name') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Usuario --}}
                <div class="perm-row">
                    <div class="perm-col-title">Usuario</div>
                    <div class="perm-col-controls">
                        <input type="text" class="form-control" placeholder="Ingresar usuario" wire:model="username">
                        @error('username') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Contraseña (opcional) --}}
                <div class="perm-row">
                    <div class="perm-col-title">Contraseña</div>
                    <div class="perm-col-controls">
                        <input type="text" class="form-control" placeholder="Nueva contraseña (opcional)" wire:model.live="pwd">
                        @error('pwd') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Email --}}
                <div class="perm-row">
                    <div class="perm-col-title">Email</div>
                    <div class="perm-col-controls">
                        <input type="email" class="form-control" placeholder="Ingresar email" wire:model="email">
                        @error('email') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Tipo Documento --}}
                <div class="perm-row">
                    <div class="perm-col-title">Tipo Documento</div>
                    <div class="perm-col-controls">
                        <select class="form-select" wire:model="document_type">
                            <option value="dni">DNI</option>
                            <option value="ruc">RUC</option>
                            <option value="ce">CE</option>
                        </select>
                        @error('document_type') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- N° Documento --}}
                <div class="perm-row">
                    <div class="perm-col-title">N° Documento</div>
                    <div class="perm-col-controls">
                        <input type="text" class="form-control" placeholder="Ingresar número" wire:model="document_number">
                        @error('document_number') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Teléfono --}}
                <div class="perm-row">
                    <div class="perm-col-title">Teléfono</div>
                    <div class="perm-col-controls">
                        <input type="text" class="form-control" placeholder="Ingresar teléfono" wire:model="phone">
                        @error('phone') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Sedes --}}
                <div class="perm-row span-2">
                    <div class="perm-col-title">Sucursales</div>
                    <div class="perm-col-controls">
                        <div class="perm-chips">
                            @foreach($headquartes as $h)
                                @php
                                    $isSelected = in_array($h->id, (array)$selectedHeadquarters, true);
                                    $isDefault  = (int)$defaultHeadquarter === (int)$h->id;
                                @endphp
                                <label class="chip-hq {{ $isSelected ? 'is-selected' : '' }} {{ $isDefault ? 'is-default' : '' }}">
                                    <input class="form-check-input" type="checkbox"
                                           value="{{ $h->id }}" wire:model="selectedHeadquarters">
                                    <span>{{ $h->name }}</span>
                                    <span class="hq-primary" title="Marcar como sede primaria">
                                        <input class="form-check-input" type="radio"
                                               name="default_hq_edit"
                                               value="{{ $h->id }}" wire:model="defaultHeadquarter">
                                        <small>Primaria</small>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('selectedHeadquarters') <span class="text-danger d-block small mt-1">{{ $message }}</span> @enderror
                        @error('defaultHeadquarter')  <span class="text-danger d-block small mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Rol --}}
                <div class="perm-row span-2">
                    <div class="perm-col-title">Rol</div>
                    <div class="perm-col-controls">
                        <div class="perm-chips">
                            @forelse($roles as $r)
                                <label class="chip-radio" title="{{ $r->name }}">
                                    <input type="radio" class="form-check-input"
                                           name="role_single_edit" value="{{ $r->id }}" wire:model="selectedRoleId">
                                    <span>{{ $r->name }}</span>
                                </label>
                            @empty
                                <span class="text-warning small">No hay roles definidos.</span>
                            @endforelse
                        </div>
                        @error('selectedRoleId') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="mt-2 d-flex gap-2">
                <button class="btn btn-light-primary btn-sm" wire:click="update">Actualizar</button>
                <a class="btn btn-secondary btn-sm" href="{{ route('settings.users.index') }}">Volver</a>
            </div>
        </div>
    </div>
</div>
