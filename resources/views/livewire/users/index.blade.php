{{-- resources/views/livewire/users/index.blade.php --}}
@push('styles')
    <style>
        /* ====== Estilos base tabla / botones ====== */
        table { border-collapse: collapse; width: 100%; }
        th,td{
            padding: 1px !important;
            font-size: 10px !important;
            text-align: center !important;
            vertical-align: middle;
        }
        .btn, input,select { font-size: 10px !important; }
        .screen-overlay {
            position: fixed; inset: 0;
            display: none; align-items: center; justify-content: center;
            background: rgba(0,0,0,.35); backdrop-filter: blur(2px);
            z-index: 2000; pointer-events: all;
        }

        /* ====== Utilidades compactas reutilizables (Agregar/Editar/Permisos) ====== */
        .perm-grid { display: flex; flex-direction: column; gap: 6px; }
        .perm-row {
            display: grid;
            grid-template-columns: 180px 1fr; /* título fijo | controles flex */
            gap: 8px;
            align-items: center;
            padding: 6px 8px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid #eee;
            margin-bottom: 6px;
        }
        .perm-col-title { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .perm-col-controls { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .perm-chips { display: flex; gap: 6px; flex-wrap: wrap; }

        .chip-check, .chip-radio, .chip-hq {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 8px;
            border: 1px solid #e5e7eb; border-radius: 999px;
            background: #fff; cursor: pointer; user-select: none;
        }
        .chip-check input, .chip-radio input, .chip-hq input { margin: 0; width: 14px; height: 14px; }
        .chip-check span, .chip-radio span, .chip-hq span { line-height: 1; }

        /* Sucursal: radio "Primaria" embebido */
        .chip-hq .hq-primary {
            display: inline-flex; align-items: center; gap: 4px;
            padding-left: 6px; margin-left: 6px;
            border-left: 1px dashed #e5e7eb;
            font-size: 11px;
        }
        .chip-hq.is-default { border-color: #60a5fa; background: #f0f9ff; }
        .chip-hq.is-selected { border-color: #a7f3d0; background: #ecfdf5; }

        /* ====== Modal Permisos: detalles visuales ====== */
        #modalPerms .modal-content { font-size: 12px; }
        #modalPerms .modal-header, #modalPerms .modal-footer { background: #fafafa; }
        #modalPerms .btn, #modalPerms input, #modalPerms label, #modalPerms small { font-size: 12px !important; }
        #modalPerms .form-check-input { width: 14px; height: 14px; margin-top: 0; }
        .action-icon { display: inline-flex; align-items: center; color: #6b7280; }
        .action-icon:hover { color: #111827; }

        /* === 2 columnas en móvil para AGREGAR/EDITAR === */
        #modalAddUser .form-two-cols,
        #modalEditUser .form-two-cols {
            display: grid;
            grid-template-columns: 1fr 1fr;   /* móvil: 2 columnas */
            gap: 6px;
        }
        #modalAddUser .form-two-cols .perm-row,
        #modalEditUser .form-two-cols .perm-row { margin: 0; }
        #modalAddUser .form-two-cols .span-2,
        #modalEditUser .form-two-cols .span-2 { grid-column: 1 / -1; }

        /* Inputs compactos en modales Add/Edit */
        #modalAddUser .form-control, #modalAddUser .form-select,
        #modalEditUser .form-control, #modalEditUser .form-select {
            padding: 4px 8px;
            min-height: 30px;
            font-size: 12px;
        }

        /* Responsive interno de cada fila */
        @media (max-width: 576px) {
            .perm-row { grid-template-columns: 1fr; padding: 6px; }
            .perm-col-title { margin-bottom: 2px; }
            .chip-check, .chip-radio, .chip-hq { padding: 3px 6px; }
        }

        /* Si quieres 3 columnas en desktop (opcional):
        @media (min-width: 992px) {
            #modalAddUser .form-two-cols,
            #modalEditUser .form-two-cols {
                grid-template-columns: 1fr 1fr 1fr;
            }
        }
        */
    </style>
@endpush

<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Usuarios</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Configuración</span></a>
                </li>
                <li class="d-flex active"><a href="#" class="f-s-14">Usuarios</a></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <!-- Tabla -->
        <div class="col-xl-12">
            <div class="card shadow-sm">


                <div class="card-body pb-2">

                       <h5>LISTADO DE USUARIOS</h5>
                       <div class="row my-2">
                           <div class="col-12">
                               <div class="d-flex flex-nowrap align-items-end gap-2 overflow-auto py-1">

                                   <!-- Input con ancho controlado (no full width) -->
                                   <div class="flex-shrink-0" style="width: 260px;">
                                       <input type="search"
                                              class="form-control form-control-sm"
                                              placeholder="Buscar..."
                                              aria-label="Buscar"
                                              wire:model.live="search">
                                   </div>

                                   <!-- Botón a la derecha -->
                                   <button class="btn btn-sm btn-primary flex-shrink-0"
                                           wire:click="openAddWindow">
                                       <i class="ti ti-square-plus f-s-12"></i> Nuevo
                                   </button>

                               </div>
                           </div>
                       </div>

                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>Id</th>
                                <th>Nombres</th>
                                <th>Usuario</th>
                                <th>Teléfono</th>
                                <th>Sede</th>
                                <th>Rol</th>
                                <th>Permisos</th>
                                <th>Acción</th>
                            </tr>
                            </thead>

                            <tbody>
                            @if($users->count() > 0)
                                @foreach($users as $user)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $user->name }}</td>
                                        <td>{{ $user->username }}</td>
                                        <td>{{ $user->phone ?: '—' }}</td>
                                        <td>
                                            {{ $user->headquarters->pluck('name')->implode(', ') ?: '—' }}
                                            @if($user->headquarter)
                                                <br><small class="text-muted">Primaria: {{ $user->headquarter->name }}</small>
                                            @endif
                                        </td>
                                        <td>{{ optional($user->roles->first())->name ?? '—' }}</td>
                                        <td>
                                            <span class="badge bg-dark">
                                                {{ $user->permissions->count() }} permisos
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            {{-- Editar datos --}}
                                            <button class="btn btn-sm btn-outline-success me-1"
                                                    title="Editar datos"
                                                    wire:click="openEditWindow({{ $user->id }})">
                                                <i class="ti ti-edit"></i>
                                            </button>

                                            {{-- Rol & Permisos (modal nuevo) --}}
                                            <button class="btn btn-sm btn-outline-dark"
                                                    title="Rol & Permisos"
                                                    wire:click="openPermsWindow({{ $user->id }})">
                                                <i class="ti ti-shield-lock"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="8" class="py-4 text-muted">No se encontraron resultados</td>
                                </tr>
                            @endif
                            </tbody>

                            <tfoot class="bg-primary">
                            <tr>
                                <td></td>
                                <td>TOTAL USUARIOS</td>
                                <td colspan="4"></td>
                                <td>{{ number_format($users->count()) }}</td>
                                <td></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="save,update">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>
