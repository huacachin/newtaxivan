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
