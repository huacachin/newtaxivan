@push('styles')
    <style>
        /* puedes reusar el mismo bloque de estilos de create */
        .perm-grid{ display:flex; flex-direction:column; gap:6px; }
        .perm-row{ display:grid; grid-template-columns:180px 1fr; gap:8px; align-items:center; padding:6px 8px; border-radius:8px; background:#fff; border:1px solid #eee; margin-bottom:6px; }
        .perm-col-title{ font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .perm-col-controls{ display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
        .perm-chips{ display:flex; gap:6px; flex-wrap:wrap; }
        .chip-radio,.chip-check{ display:inline-flex; align-items:center; gap:6px; padding:4px 8px; border:1px solid #e5e7eb; border-radius:999px; background:#fff; }
        @media (max-width:576px){ .perm-row{ grid-template-columns:1fr; } }
    </style>
@endpush

<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">ROL & PERMISOS</h4>
            @if($permsUserName)
                <small class="text-muted d-block">Usuario: {{ $permsUserName }}</small>
            @endif
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Configuración</span></a>
                </li>
                <li class="d-flex"><a href="{{ route('settings.users.index') }}" class="f-s-14">Usuarios</a></li>
                <li class="d-flex active"><a class="f-s-14">Permisos</a></li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-body">

            {{-- Rol --}}
            <div class="perm-row">
                <div class="perm-col-title">Rol</div>
                <div class="perm-col-controls">
                    <div class="perm-chips">
                        @forelse($roles as $r)
                            <label class="chip-radio" title="{{ __('roles.'.$r->name) }}">
                                <input type="radio" class="form-check-input" name="role_single_perms"
                                       value="{{ $r->id }}" wire:model="selectedRoleId">
                                <span>{{ __('roles.'.$r->name) }}</span>
                            </label>
                        @empty
                            <span class="text-warning small">No hay roles definidos.</span>
                        @endforelse
                    </div>
                    @error('selectedRoleId') <span class="title-modules small">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Permisos --}}
            <div class="perm-grid">
                @foreach($aclGroups as $groupKey => $group)
                    @php
                        $count   = count($group['items'] ?? []);
                        $compact = ($group['type'] === 'single') || ($count === 1);
                        $only    = $group['items'][0] ?? null;
                    @endphp

                    @if($compact && $only)
                        <div class="perm-row">
                            <div class="perm-col-title">{{ __('perms.'.$group['title']) }}</div>
                            <div class="perm-col-controls">
                                <label class="chip-check" title="{{ $only['key'] }}">
                                    <input class="form-check-input" type="checkbox"
                                           value="{{ $only['key'] }}"
                                           wire:model="selectedPermissionNames">
                                    <span>{{ __('perms.'.$group['title']) }}</span>
                                </label>
                            </div>
                        </div>
                    @else
                        <div class="perm-row">
                            <div class="perm-col-title d-flex align-items-center gap-2">
                                <span>{{ __('perms.'.$group['title']) }}</span>
                                <a href="javascript:void(0)" class="action-icon" wire:click="selectGroup('{{ $groupKey }}')" title="Marcar todo">
                                    <i class="ti ti-square-check"></i>
                                </a>
                                <a href="javascript:void(0)" class="action-icon" wire:click="deselectGroup('{{ $groupKey }}')" title="Desmarcar">
                                    <i class="ti ti-square-x"></i>
                                </a>
                            </div>
                            <div class="perm-col-controls">
                                <div class="perm-chips">
                                    @foreach($group['items'] as $it)
                                        <label class="chip-check" title="{{ $it['key'] }}">
                                            <input class="form-check-input" type="checkbox"
                                                   value="{{ $it['key'] }}"
                                                   wire:model="selectedPermissionNames">
                                            <span>{{ $it['label'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="mt-2 d-flex gap-2">
                <button class="btn btn-sm btn-primary" wire:click="savePerms">Guardar</button>
                <a class="btn btn-sm btn-secondary" href="{{ route('settings.users.index') }}">Volver</a>
            </div>
        </div>
    </div>
</div>
