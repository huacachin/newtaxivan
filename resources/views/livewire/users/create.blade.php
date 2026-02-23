<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6"><h4 class="main-title title-modules">AGREGAR USUARIO</h4></div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Configuración</span></a>
                </li>
                <li class="d-flex"><a href="{{ route('settings.users.index') }}" class="f-s-14">Usuarios</a></li>
                <li class="d-flex active"><a class="f-s-14">Agregar</a></li>
            </ul>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="perm-grid form-two-cols">
                {{-- Nombre --}}
                <div class="perm-row">
                    <div class="perm-col-title">Nombre</div>
                    <div class="perm-col-controls">
                        <input type="text" class="form-control form-control-sm" placeholder="Ingresar nombre" wire:model.live="name">
                        @error('name') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Usuario --}}
                <div class="perm-row">
                    <div class="perm-col-title">Usuario</div>
                    <div class="perm-col-controls">
                        <input type="text" class="form-control form-control-sm" placeholder="Ingresar usuario" wire:model="username">
                        @error('username') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Contraseña --}}
                <div class="perm-row">
                    <div class="perm-col-title">Contraseña</div>
                    <div class="perm-col-controls">
                        <input type="text" class="form-control form-control-sm" placeholder="Ingresar contraseña" wire:model.live="pwd">
                        @error('pwd') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Email --}}
                <div class="perm-row">
                    <div class="perm-col-title">Email</div>
                    <div class="perm-col-controls">
                        <input type="email" class="form-control form-control-sm" placeholder="Ingresar email" wire:model="email">
                        @error('email') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Tipo Documento --}}
                <div class="perm-row">
                    <div class="perm-col-title">Tipo Documento</div>
                    <div class="perm-col-controls">
                        <select class="form-control form-control-sm" wire:model="document_type">
                            <option value="dni">DNI</option>
                            <option value="ruc">RUC</option>
                            <option value="ce">CE</option>
                        </select>
                        @error('document_type') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- N° Documento --}}
                <div class="perm-row">
                    <div class="perm-col-title">N° Documento</div>
                    <div class="perm-col-controls">
                        <input type="text" class="form-control form-control-sm" placeholder="Ingresar número" wire:model="document_number">
                        @error('document_number') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Teléfono --}}
                <div class="perm-row">
                    <div class="perm-col-title">Teléfono</div>
                    <div class="perm-col-controls">
                        <input type="text" class="form-control form-control-sm" placeholder="Ingresar teléfono" wire:model="phone">
                        @error('phone') <span class="title-modules small">{{ $message }}</span> @enderror
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
                                               name="default_hq_add"
                                               value="{{ $h->id }}" wire:model="defaultHeadquarter">
                                        <small>Primaria</small>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('selectedHeadquarters') <span class="title-modules d-block small mt-1">{{ $message }}</span> @enderror
                        @error('defaultHeadquarter')  <span class="title-modules d-block small mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Rol --}}
                <div class="perm-row span-2">
                    <div class="perm-col-title">Rol</div>
                    <div class="perm-col-controls">
                        <div class="perm-chips">
                            @forelse($roles as $r)
                                <label class="chip-radio" title="{{ __('roles.'.$r->name) }}">
                                    <input type="radio" class="form-check-input"
                                           name="role_single_add" value="{{ $r->id }}" wire:model="selectedRoleId">
                                    <span>{{ __('roles.'.$r->name) }}</span>
                                </label>
                            @empty
                                <span class="text-warning small">No hay roles definidos.</span>
                            @endforelse
                        </div>
                        @error('selectedRoleId') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="mt-2 d-flex gap-2">
                <button class="btn btn-primary btn-sm" wire:click="save">Guardar</button>
                <a class="btn btn-secondary btn-sm" href="{{ route('settings.users.index') }}">Volver</a>
            </div>
        </div>
    </div>
</div>
