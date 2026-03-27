{{-- resources/views/livewire/users/index.blade.php --}}
<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">LISTADO GENERAL DE USUARIOS</h4>
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
                                   @if(auth()->user()->isDirector())
                                   <a class="btn btn-sm btn-primary flex-shrink-0"
                                      href="{{ route('settings.users.create') }}" target="_blank">
                                       <i class="ti ti-square-plus f-s-12"></i> Nuevo
                                   </a>
                                   @endif

                                   <button class="btn btn-sm btn-primary flex-shrink-0"
                                           wire:click="export">
                                       <i class="ti ti-file-analytics f-s-12"></i> Exportar
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
                                            @if(in_array(optional($user->roles->first())->name, ['director','gerente']))
                                                —
                                            @else
                                                {{ $user->headquarters->pluck('name')->implode(', ') ?: '—' }}
                                                @if($user->headquarter)
                                                    <br><small class="text-muted">Primaria: {{ $user->headquarter->name }}</small>
                                                @endif
                                            @endif
                                        </td>
                                        <td>{{ ($r = optional($user->roles->first())->name) ? __('roles.' . $r, [], 'es') : '—' }}</td>
                                        <td>
                                            <span class="badge bg-dark">
                                                {{ $user->permissions->count() }} permisos
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            @if(auth()->user()->canManageUser($user))
                                                {{-- Editar datos --}}
                                                <a class="btn btn-sm btn-outline-success me-1"
                                                   title="Editar datos"
                                                   href="{{ route('settings.users.edit', $user->id) }}">
                                                    <i class="ti ti-edit"></i>
                                                </a>

                                                {{-- Permisos (solo Director) --}}
                                                @if(auth()->user()->isDirector())
                                                <a class="btn btn-sm btn-outline-dark"
                                                   title="Permisos"
                                                   href="{{ route('settings.users.perms', $user->id) }}" target="_blank">
                                                    <i class="ti ti-shield-lock"></i>
                                                </a>
                                                @endif

                                                <button class="btn btn-sm btn-outline-danger ms-1"
                                                        title="Desactivar usuario"
                                                        wire:click="questionDelete({{ $user->id }})">
                                                    <i class="ti ti-trash"></i>
                                                </button>
                                            @endif
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
         wire:target="save,update,export">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>
