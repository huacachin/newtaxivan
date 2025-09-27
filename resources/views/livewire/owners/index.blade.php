@push('styles')
    <style>
        /* Tabla compacta */
        .compact-table-xxs{
            font-size:12px; line-height:1.12; table-layout:fixed;
        }
        .compact-table-xxs th,.compact-table-xxs td{
            padding:.22rem .35rem; white-space:nowrap; vertical-align:middle; text-align:center;
        }

        /* Encabezado y pie oscuros para TODAS las tablas de esta vista */
        .table-section .table thead th {
            background-color:#009BDC !important;
            color:#fff !important;
            vertical-align:middle;
        }
        .table-section .table tfoot th,
        .table-section .table tfoot td {
            background-color:#009BDC !important;
            color:#fff !important;
        }

        /* Propietarios libres con gris más fuerte */
        .support-table tbody td {
            background-color:#e5e7eb !important;
        }
        .support-table tbody tr:hover td {
            background-color:#d1d5db !important;
        }
        .support-table.table-striped tbody tr:nth-of-type(odd) td {
            background-color:#e5e7eb !important;
        }
    </style>
@endpush

<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Propietarios</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Configuración</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Propietarios</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <!-- Filtros / acciones -->
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-xl-5 col-md-6">
                            <form class="app-form app-icon-form" action="#">
                                <div class="position-relative">
                                    <input type="search" class="form-control" placeholder="Buscar..."
                                           aria-label="Buscar" wire:model.live="search">
                                    <i class="ti ti-search text-dark"></i>
                                </div>
                            </form>
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <select class="form-select" aria-label="Selecciona item a filtrar" wire:model.live="filter">
                                <option value="plate">Placa</option>
                                <option value="name">Nombre</option>
                                <option value="code">Código</option>
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <button class="btn btn-primary w-100" wire:click="openAddModal">
                                <i class="ti ti-square-plus f-s-17"></i> Nuevo
                            </button>
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <button class="btn btn-primary w-100" wire:click="export">
                                <i class="ti ti-file-analytics f-s-17"></i> Exportar
                            </button>
                        </div>
                        <div class="col-xl-1 col-md-4">
                            <button id="down" class="btn btn-primary w-100">
                                <i class="ti ti-square-chevrons-down f-s-17"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla principal: Propietarios -->
        <div class="col-xl-12">
            <div class="card">
                <div class="card-header">
                    <h5>Total propietarios: {{ $owners->count() }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped table-hover compact-table-xxs">
                            <thead class="text-center">
                            <tr>
                                <th scope="col">Id</th>
                                <th scope="col">Placa</th>
                                <th scope="col">Nombre/Empresa</th>
                                <th scope="col">DNI/RUC</th>
                                <th scope="col">Cel.</th>
                                <th scope="col">Acción</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if($owners->count() > 0)
                                @foreach ($owners as $owner)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $owner->plate }}</td>
                                        <td class="text-start">{{ $owner->name }}</td>
                                        <td>{{ $owner->document_number }}</td>
                                        <td>{{ $owner->phone }}</td>
                                        <td width="10" class="text-center">
                                            <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"
                                               wire:click="openEditModal({{ $owner->id }})"></i>
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="6" class="text-center">No se encontrarón resultados</td>
                                </tr>
                            @endif
                            </tbody>
                            <tfoot>
                            <tr>
                                <td colspan="6" class="text-center">Propietarios: {{ $owners->count() }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla secundaria: Propietarios libres (gris más fuerte) -->
        <div class="col-xl-12">
            <div class="card">
                <div class="card-header">
                    <h5>Propietarios Libres: {{ $ownersFree->count() }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped table-hover compact-table-xxs support-table">
                            <thead class="text-center">
                            <tr>
                                <th scope="col">Id</th>
                                <th scope="col">Nombre/Empresa</th>
                                <th scope="col">DNI/RUC</th>
                                <th scope="col">Cel.</th>
                                <th scope="col">Acción</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($ownersFree as $owner)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td class="text-start">{{ $owner->name }}</td>
                                    <td>{{ $owner->document_number }}</td>
                                    <td>{{ $owner->phone }}</td>
                                    <td width="10" class="text-center">
                                        <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"
                                           wire:click="openEditModal({{ $owner->id }})"></i>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center">No se encontrarón resultados</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot>
                            <tr>
                                <td colspan="5" class="text-center">Libres: {{ $ownersFree->count() }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Modal: Agregar --}}
    <div class="modal fade" id="modalAddOwner" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Propietario</h5>
                    <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        {{-- Nombre / Tipo doc --}}
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nombres</label>
                                <input id="name" type="text" class="form-control" placeholder="Ingresar nombres y apellidos" wire:model="name">
                                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="document_type" class="form-label">Tipo de documento</label>
                                <select class="form-select" id="document_type" wire:model="document_type">
                                    <option value="">Seleccionar</option>
                                    <option value="dni">DNI</option>
                                    <option value="ruc">RUC</option>
                                </select>
                                @error('document_type') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        {{-- Doc / Vencimiento --}}
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="document_number" class="form-label">Número de documento</label>
                                <input type="text" class="form-control" placeholder="Ingresar número de documento" wire:model="document_number">
                                @error('document_number') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="document_expiration_date" class="form-label">Doc F.Vencimiento</label>
                                <input type="date" class="form-control" wire:model="document_expiration_date">
                                @error('document_expiration_date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        {{-- Nacimiento / Distrito --}}
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="birthdate" class="form-label">Fecha Nacimiento</label>
                                <input type="date" class="form-control" wire:model="birthdate">
                                @error('birthdate') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="district" class="form-label">Distrito</label>
                                <input type="text" class="form-control" placeholder="Ingresar distrito" wire:model="district">
                                @error('district') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        {{-- Dirección --}}
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="address" class="form-label">Dirección</label>
                                <input type="text" class="form-control" placeholder="Ingresar dirección" wire:model="address">
                                @error('address') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        {{-- Tel / Email --}}
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Teléfono</label>
                                <input id="phone" type="text" class="form-control" placeholder="Ingresar teléfono" wire:model="phone">
                                @error('phone') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input id="email" type="email" class="form-control" placeholder="Ingresar email" wire:model="email">
                                @error('email') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-primary" wire:click="save">Agregar</button>
                    <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: Editar --}}
    <div class="modal fade" id="modalEditOwner" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Propietario</h5>
                    <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        {{-- Nombre / Tipo doc --}}
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name_edit" class="form-label">Nombres</label>
                                <input id="name_edit" type="text" class="form-control" placeholder="Ingresar nombres y apellidos" wire:model="name">
                                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="document_type_edit" class="form-label">Tipo de documento</label>
                                <select class="form-select" id="document_type_edit" wire:model="document_type">
                                    <option value="">Seleccionar</option>
                                    <option value="dni">DNI</option>
                                    <option value="ruc">RUC</option>
                                </select>
                                @error('document_type') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        {{-- Doc / Vencimiento --}}
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="document_number_edit" class="form-label">Número de documento</label>
                                <input id="document_number_edit" type="text" class="form-control" placeholder="Ingresar número de documento" wire:model="document_number">
                                @error('document_number') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="document_expiration_date_edit" class="form-label">Doc F.Vencimiento</label>
                                <input id="document_expiration_date_edit" type="date" class="form-control" wire:model="document_expiration_date">
                                @error('document_expiration_date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        {{-- Nacimiento / Distrito --}}
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="birthdate_edit" class="form-label">Fecha Nacimiento</label>
                                <input id="birthdate_edit" type="date" class="form-control" wire:model="birthdate">
                                @error('birthdate') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="district_edit" class="form-label">Distrito</label>
                                <input id="district_edit" type="text" class="form-control" placeholder="Ingresar distrito" wire:model="district">
                                @error('district') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        {{-- Dirección --}}
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="address_edit" class="form-label">Dirección</label>
                                <input id="address_edit" type="text" class="form-control" placeholder="Ingresar dirección" wire:model="address">
                                @error('address') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        {{-- Tel / Email --}}
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone_edit" class="form-label">Teléfono</label>
                                <input id="phone_edit" type="text" class="form-control" placeholder="Ingresar teléfono" wire:model="phone">
                                @error('phone') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email_edit" class="form-label">Email</label>
                                <input id="email_edit" type="email" class="form-control" placeholder="Ingresar email" wire:model="email">
                                @error('email') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-primary" wire:click="update">Editar</button>
                    <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function(){
            const downBtn = document.getElementById('down');
            downBtn?.addEventListener('click', function(e){
                e.preventDefault();
                window.scrollTo({ top: document.body.scrollHeight, behavior:'smooth' });
            });
        })();
    </script>
@endpush
