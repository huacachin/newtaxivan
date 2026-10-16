<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6"><h4 class="main-title title-modules">EDITAR USUARIO</h4></div>
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

    <div class="card">
        <div class="card-body">
            @if(!$canFullEdit)
                <div class="alert alert-info py-2 mb-3">
                    <i class="ti ti-info-circle"></i>
                    Solo puedes modificar la contraseña{{ $editedUserRoleName === 'controlador' ? ' y las sucursales' : '' }}.
                </div>
            @endif

            <div class="perm-grid form-two-cols">
                {{-- Nombre --}}
                <div class="perm-row">
                    <div class="perm-col-title">Nombre</div>
                    <div class="perm-col-controls">
                        <input type="text" class="form-control form-control-sm" placeholder="Ingresar nombre"
                               wire:model.live="name" @if(!$canFullEdit) disabled style="opacity:.6" @endif>
                        @error('name') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Usuario --}}
                <div class="perm-row">
                    <div class="perm-col-title">Usuario</div>
                    <div class="perm-col-controls">
                        <input type="text" class="form-control form-control-sm" placeholder="Ingresar usuario"
                               wire:model="username" @if(!$canFullEdit) disabled style="opacity:.6" @endif>
                        @error('username') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Contraseña (opcional) --}}
                <div class="perm-row" x-data="{
                    show: false,
                    strength: 0,
                    strengthLabel: '',
                    strengthColor: '',
                    generate() {
                        const upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                        const lower = 'abcdefghijklmnopqrstuvwxyz';
                        const nums = '0123456789';
                        const syms = '!@#$%^&*_+-=';
                        const all = upper + lower + nums + syms;
                        let pwd = [
                            upper[Math.floor(Math.random() * upper.length)],
                            lower[Math.floor(Math.random() * lower.length)],
                            nums[Math.floor(Math.random() * nums.length)],
                            syms[Math.floor(Math.random() * syms.length)],
                        ];
                        for (let i = 4; i < 14; i++) pwd.push(all[Math.floor(Math.random() * all.length)]);
                        pwd = pwd.sort(() => Math.random() - 0.5).join('');
                        $wire.set('pwd', pwd);
                        this.show = true;
                        this.checkStrength(pwd);
                    },
                    checkStrength(val) {
                        let s = 0;
                        if (val.length >= 8) s++;
                        if (/[A-Z]/.test(val)) s++;
                        if (/[a-z]/.test(val)) s++;
                        if (/[0-9]/.test(val)) s++;
                        if (/[!@#$%^&*_+\-=]/.test(val)) s++;
                        this.strength = s;
                        if (s <= 2) { this.strengthLabel = 'Débil'; this.strengthColor = 'bg-danger'; }
                        else if (s <= 3) { this.strengthLabel = 'Media'; this.strengthColor = 'bg-warning'; }
                        else { this.strengthLabel = 'Fuerte'; this.strengthColor = 'bg-success'; }
                    }
                }" x-init="$watch('$wire.pwd', val => checkStrength(val || ''))">
                    <div class="perm-col-title">Contraseña</div>
                    <div class="perm-col-controls">
                        <div class="input-group input-group-sm">
                            <input :type="show ? 'text' : 'password'" class="form-control form-control-sm"
                                   placeholder="Nueva contraseña (opcional)" wire:model.live="pwd"
                                   @if(!$canFullEdit) readonly @endif>
                            <button class="btn btn-outline-secondary" type="button" @click="show = !show" title="Mostrar/ocultar">
                                <i class="ti" :class="show ? 'ti-eye-off' : 'ti-eye'"></i>
                            </button>
                            <button class="btn btn-outline-primary" type="button" @click="generate()" title="Generar contraseña segura">
                                <i class="ti ti-key"></i> Generar
                            </button>
                        </div>
                        <div class="mt-1" x-show="$wire.pwd && $wire.pwd.length > 0" x-cloak>
                            <div class="progress" style="height: 5px;">
                                <div class="progress-bar" :class="strengthColor" :style="'width:' + (strength * 20) + '%'"></div>
                            </div>
                            <small :class="strengthColor.replace('bg-','text-')" x-text="strengthLabel"></small>
                        </div>
                        @error('pwd') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Email --}}
                <div class="perm-row">
                    <div class="perm-col-title">Email</div>
                    <div class="perm-col-controls">
                        <input type="email" class="form-control form-control-sm" placeholder="Ingresar email"
                               wire:model="email" @if(!$canFullEdit) disabled style="opacity:.6" @endif>
                        @error('email') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Tipo Documento --}}
                <div class="perm-row">
                    <div class="perm-col-title">Tipo Documento</div>
                    <div class="perm-col-controls">
                        <select class="form-control form-control-sm" wire:model="document_type"
                                @if(!$canFullEdit) disabled style="opacity:.6" @endif>
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
                        <input type="text" class="form-control form-control-sm" placeholder="Ingresar número"
                               wire:model="document_number" @if(!$canFullEdit) disabled style="opacity:.6" @endif>
                        @error('document_number') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Teléfono --}}
                <div class="perm-row">
                    <div class="perm-col-title">Teléfono</div>
                    <div class="perm-col-controls">
                        <input type="text" class="form-control form-control-sm" placeholder="Ingresar teléfono"
                               wire:model="phone" @if(!$canFullEdit) disabled style="opacity:.6" @endif>
                        @error('phone') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Orden --}}
                <div class="perm-row">
                    <div class="perm-col-title">Orden</div>
                    <div class="perm-col-controls">
                        <input type="number" class="form-control form-control-sm" placeholder="0"
                               wire:model="sort_order" min="0" @if(!$canFullEdit) disabled style="opacity:.6" @endif>
                        @error('sort_order') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Sedes --}}
                @php
                    $selectedRoleName = $selectedRoleId ? collect($roles)->firstWhere('id', $selectedRoleId)?->name : null;
                    $isAdminRole = $selectedRoleName && in_array(mb_strtolower($selectedRoleName), ['director', 'gerente']);
                    $canEditHq = $canFullEdit || $editedUserRoleName === 'controlador';
                @endphp
                <div class="perm-row span-2" @if($isAdminRole) style="display:none" @endif>
                    <div class="perm-col-title">Sucursales</div>
                    <div class="perm-col-controls">
                        @if($isAdminRole)
                            <span class="text-muted small">Admin tiene acceso a todas las sedes automáticamente.</span>
                        @else
                            <div class="perm-chips" @if(!$canEditHq) style="opacity:.6; pointer-events:none" @endif>
                                @foreach($headquartes as $h)
                                    @php
                                        $isSelected = in_array($h->id, (array)$selectedHeadquarters, true);
                                        $isDefault  = (int)$defaultHeadquarter === (int)$h->id;
                                    @endphp
                                    <label class="chip-hq {{ $isSelected ? 'is-selected' : '' }} {{ $isDefault ? 'is-default' : '' }}">
                                        <input class="form-check-input" type="checkbox"
                                               value="{{ $h->id }}" wire:model.live="selectedHeadquarters"
                                               @if(!$canEditHq) disabled @endif>
                                        <span>{{ $h->name }}</span>
                                        <span class="hq-primary" title="Marcar como sede primaria">
                                            <input class="form-check-input" type="radio"
                                                   name="default_hq_edit"
                                                   value="{{ $h->id }}" wire:model.live="defaultHeadquarter"
                                                   @if(!$canEditHq) disabled @endif>
                                            <small>Primaria</small>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('selectedHeadquarters') <span class="title-modules d-block small mt-1">{{ $message }}</span> @enderror
                            @error('defaultHeadquarter')  <span class="title-modules d-block small mt-1">{{ $message }}</span> @enderror
                        @endif
                    </div>
                </div>

                {{-- Rol --}}
                <div class="perm-row span-2">
                    <div class="perm-col-title">Rol</div>
                    <div class="perm-col-controls">
                        <div class="perm-chips" @if(!$canFullEdit) style="opacity:.6; pointer-events:none" @endif>
                            @forelse($roles as $r)
                                <label class="chip-radio" title="{{ __('roles.'.$r->name) }}">
                                    <input type="radio" class="form-check-input"
                                           name="role_single_edit" value="{{ $r->id }}" wire:model="selectedRoleId"
                                           @if(!$canFullEdit) disabled @endif>
                                    <span>{{ __('roles.'.$r->name) }}</span>
                                </label>
                            @empty
                                <span class="text-warning small">No hay roles definidos.</span>
                            @endforelse
                        </div>
                        @error('selectedRoleId') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Dar o quitar el rol Director pide la clave de seguridad. El campo aparece (Alpine) solo
                     cuando el rol marcado cambia y uno de los dos es Director; el servidor la exige de todos
                     modos. Sin wire:model: va como parámetro y nunca vuelve al navegador. --}}
                @if($canFullEdit)
                    @php
                        $directorRoleId = (int) (collect($roles)->firstWhere('name', 'director')?->id ?? 0);
                        $controllerRoleId = (int) (collect($roles)->firstWhere('name', 'controlador')?->id ?? 0);
                        $originalRoleId = (int) (collect($roles)->firstWhere('name', $editedUserRoleName)?->id ?? 0);
                    @endphp
                    {{-- Cambiar de rol reinicia permisos y sucursales (ver update): se avisa antes de guardar. --}}
                    <div class="perm-row span-2" x-data x-cloak x-show.important="($wire.selectedRoleId ?? 0) != {{ $originalRoleId }}">
                        <div class="perm-col-title"><i class="ti ti-alert-triangle"></i> Cambio de rol</div>
                        <div class="perm-col-controls alert alert-warning py-2 mb-0 small">
                            <b>Cambiar el rol reinicia los permisos y las sucursales de {{ $user->username }}.</b>
                            <span x-show="($wire.selectedRoleId ?? 0) == {{ $directorRoleId }}">Como Director recibirá todos los permisos.</span>
                            <span x-show="($wire.selectedRoleId ?? 0) != {{ $directorRoleId }}">
                                Quedará <b>sin permisos</b> y no podrá entrar a ninguna pantalla: después de guardar, asígnaselos en Permisos<span x-show="($wire.selectedRoleId ?? 0) == {{ $controllerRoleId }}"> y vuelve a Editar para marcar sus sucursales (sin ellas no ve ninguna sede)</span>.
                            </span>
                        </div>
                    </div>

                    <div class="perm-row span-2" x-data x-cloak
                         x-show.important="($wire.selectedRoleId ?? 0) != {{ $originalRoleId }} && (($wire.selectedRoleId ?? 0) == {{ $directorRoleId }} || {{ $originalRoleId }} == {{ $directorRoleId }})">
                        <div class="perm-col-title"><i class="ti ti-shield-lock"></i> Clave de seguridad</div>
                        <div class="perm-col-controls">
                            <input type="password" class="form-control form-control-sm" id="director-key" name="director_key"
                                   autocomplete="one-time-code" placeholder="Clave para dar o quitar el rol Director"
                                   x-on:keydown.enter="$wire.update($event.target.value)">
                            <small class="text-muted d-block">Dar o quitar el rol Director requiere la clave de seguridad.</small>
                            @error('directorKey') <span class="title-modules small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                @endif
            </div>

            <div class="mt-2 d-flex gap-2">
                <button class="btn btn-primary btn-sm" x-on:click="$wire.update(document.getElementById('director-key')?.value ?? '')" wire:loading.attr="disabled"><span wire:loading wire:target="update" class="spinner-border spinner-border-sm"></span><span wire:loading.remove wire:target="update">Actualizar</span></button>
                <a class="btn btn-secondary btn-sm" href="{{ route('settings.users.index') }}">Volver</a>
            </div>
        </div>
    </div>
</div>
