@push('styles')
    <style>
        /* Encabezado y pie oscuros */
        .tableFixHead thead th {
            position: sticky; top: 0; z-index: 2;
            background-color: #009BDC !important;
            color: #fff !important;
            vertical-align: middle;
        }
        .tableFixHead tfoot th,
        .tableFixHead tfoot td {
            background-color: #009BDC !important;
            color: #fff !important;
        }

        /* Ajuste al mínimo del ancho: que no rompa líneas y se contraiga al contenido */
        .tableFixHead table.table th,
        .tableFixHead table.table td {
            white-space: nowrap;
        }

        /* Zebra + hover suave */
        .tableFixHead tbody tr:hover {
            background: #f8fafc;
        }

        /* Inputs con icono alineado */
        .app-icon-form .ti { position: absolute; right: .75rem; top: 50%; transform: translateY(-50%); }

        /* Botones iguales al resto del sistema (ya usas .btn-primary) */

        .screen-overlay {
            position: fixed;
            inset: 0;                 /* full viewport */
            display: none;            /* Livewire lo pondrá en flex */
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,.35);
            backdrop-filter: blur(2px);
            z-index: 2000;            /* sobre modals/backdrops de Bootstrap */
            pointer-events: all;      /* bloquea clics */
        }
    </style>
@endpush

<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Pagos</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-currency-dollar f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Pagos</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Listar</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">

        <!-- Tabla -->
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-header">
                    <div class="row g-3">
                        <div class="col-xl-9 col-md-7">
                            <label class="form-label">Buscar</label>
                            <form class="app-form app-icon-form" action="#">
                                <div class="position-relative">
                                    <input type="search" class="form-control" placeholder="Buscar..."
                                           aria-label="Buscar" wire:model.live="search">
                                    <i class="ti ti-search text-dark"></i>
                                </div>
                            </form>
                        </div>
                        <div class="col-xl-3 col-md-5">
                            <label class="form-label">Filtro</label>
                            <select class="form-select" aria-label="Selecciona item a filtrar" wire:model.live="filter">
                                <option value="">Seleccione un filtro</option>
                                <option value="1">Placa</option>
                                <option value="2">Usuario</option>
                                <option value="3">Serie</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-xl-3 col-md-3">
                            <label class="form-label">Fecha Inicio</label>
                            <input type="date" class="form-control" wire:model="date_start">
                        </div>
                        <div class="col-xl-3 col-md-3">
                            <label class="form-label">Fecha Fin</label>
                            <input type="date" class="form-control" wire:model="date_end">
                        </div>
                        <div class="col-xl-2 col-md-2">
                            <label class="form-label">Sucursal</label>
                            <select class="form-select" wire:model.live="headquarter_id" aria-label="Selecciona sucursal">
                                <option value="">Todos</option>
                                @foreach($headquarters as $h)
                                    <option value="{{ $h->id }}">{{ $h->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-2">
                            <label class="form-label">Tipo</label>
                            <select class="form-select" wire:model.live="type" aria-label="Selecciona tipo">
                                <option value="">Todos</option>
                                <option value="PAGO">Pago</option>
                                <option value="DEUDA">Deuda</option>
                                <option value="RETRASO">Retraso</option>
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-2 d-flex align-items-end">
                            <button class="btn btn-primary w-100" wire:click="applyDate">
                                Buscar
                            </button>
                        </div>
                    </div>
                    <div class="row g-2 mt-2">
                        @role('admin')
                        <div class="col-xl-2 col-md-4">
                            <button class="btn btn-primary w-100" wire:click="daily">
                                <i class="ti ti-report-analytics f-s-16"></i> Diario
                            </button>
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <button class="btn btn-primary w-100" wire:click="monthly">
                                <i class="ti ti-report-analytics f-s-16"></i> Mensual
                            </button>
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <button class="btn btn-primary w-100" wire:click="stats">
                                <i class="ti ti-report-analytics f-s-16"></i> Estadis.
                            </button>
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <button class="btn btn-primary w-100" wire:click="export">
                                <i class="ti ti-file-analytics f-s-16"></i> Exportar
                            </button>
                        </div>
                        @endrole
                        <div class="col-xl-2 col-md-4">
                            <button class="btn btn-primary w-100" wire:click="openAddModal">
                                <i class="ti ti-square-plus f-s-16"></i> Nuevo
                            </button>
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <button class="btn btn-primary w-100" id="down">
                                <i class="ti ti-square-chevrons-down f-s-17"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive tableFixHead">
                        <table class="table table-sm table-bordered table-striped table-hover align-middle">
                            <thead class="text-center">
                            <tr>
                                <th>Acción</th>
                                <th>Ítem</th>
                                <th>Placa</th>
                                <th>Serie</th>
                                <th>Fecha Registro</th>
                                <th>Fecha Pago</th>
                                <th>Hora</th>
                                <th>Tipo</th>
                                <th>Sucursal</th>
                                <th>Usuario</th>
                                <th>S/.</th>
                                <th>Map</th>
                            </tr>
                            </thead>

                            <tbody class="text-center">
                            @forelse($payments as $p)
                                <tr>
                                    <td>
                                        <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"
                                           wire:click="openEditModal({{ $p->id }})"></i>
                                    </td>
                                    <td>{{ $loop->iteration }}</td>
                                    <td class="text-nowrap">{{ $p->legacy_plate }}</td>
                                    <td>{{ $p->serie }}</td>
                                    <td>{{ optional($p->date_register)->format('Y-m-d') }}</td>
                                    <td>{{ optional($p->date_payment)->format('Y-m-d') }}</td>
                                    <td>{{ $p->hour }}</td>
                                    <td>{{ $p->type }}</td>
                                    <td>{{ $p->headquarter->name ?? '-' }}</td>
                                    <td>{{ $p->user->name ?? '-' }}</td>
                                    <td class="text-end">{{ number_format($p->amount, 2) }}</td>
                                    <td>
                                        @if(!empty($p->latitude) && !empty($p->longitude))
                                            <a href="https://maps.google.com/?q={{ $p->latitude }},{{ $p->longitude }}"
                                               target="_blank" class="text-decoration-underline">🌍</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="py-4 text-muted">No se encontraron resultados</td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="text-center fw-semibold">
                            <tr>
                                <th colspan="10" class="text-end">Total general:</th>
                                <th class="text-end">{{ number_format($total_general, 2) }}</th>
                                <th></th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal: Agregar Pago --}}
        <div class="modal fade" id="modalAddPayment" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Agregar Pago</h5>
                        <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <strong>Revisa los siguientes errores:</strong>
                                <ul class="mb-0 mt-2 ps-3">
                                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="row">
                            {{-- === Campos iguales a tu versión original === --}}
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="pay_plate" class="form-label">Placa</label>
                                    <input id="pay_plate" type="text" class="form-control" placeholder="ABC-123"
                                           wire:model.live.debounce.300ms="plate">
                                    @error('plate') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Serie</label>
                                    <input type="text" class="form-control" wire:model.defer="serie">
                                    @error('serie') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Sucursal</label>
                                    <select class="form-select" wire:model.live="headquarter_id_form">
                                        <option value="">Seleccionar</option>
                                        @foreach($headquarters as $hq)
                                            <option value="{{ $hq->id }}">{{ $hq->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('headquarter_id_form') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Fecha Registro</label>
                                    <input type="date" class="form-control" wire:model.live="date_register" readonly>
                                    @error('date_register') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Fecha Pago</label>
                                    <input type="date" class="form-control"
                                           wire:model.live="date_payment"
                                           @if($type_form === 'PAGO')
                                               readonly
                                           min="{{ now()->toDateString() }}"
                                           max="{{ now()->toDateString() }}"
                                           style="background:#eee; pointer-events:none;"
                                        @endif
                                    >
                                    @error('date_payment') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Hora</label>
                                    <input type="time" class="form-control" wire:model.defer="hour">
                                    @error('hour') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Tipo</label>
                                    <select class="form-select" wire:model.live="type_form">
                                        <option value="">Seleccionar</option>
                                        <option value="PAGO">Pago</option>
                                        <option value="DEUDA">Deuda</option>
                                        <option value="RETRASO">Retraso</option>
                                    </select>
                                    @error('type_form') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Monto (S/)</label>
                                    <input type="number" step="0.01" min="0.01" class="form-control"
                                           wire:model.defer="amount"
                                           @if($type_form !== 'DEUDA' && !is_null($detected_cost)) readonly @endif>
                                    @error('amount') <span class="text-danger">{{ $message }}</span> @enderror

                                    @if($type_form === 'DEUDA')
                                        @if(!is_null($pending_debt))
                                            <small class="{{ $pending_debt > 0 ? 'text-muted' : 'text-warning' }}">
                                                Deuda pendiente total: S/ {{ number_format($pending_debt, 2) }}
                                            </small>
                                        @endif
                                    @else
                                        @if(!is_null($detected_cost))
                                            <small class="text-muted">
                                                Costo detectado: S/ {{ number_format($detected_cost, 2) }} — Fecha: {{ $date_register }}
                                            </small>
                                        @else
                                            <small class="text-warning">
                                                No hay costo configurado para {{ $date_register }} y placa “{{ $plate }}”.
                                            </small>
                                        @endif
                                    @endif
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light-primary" wire:click="save">Agregar</button>
                        <button type="button" class="btn btn-light-secondary" wire:click="cancelAdd" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal: Editar Pago (idéntico a tu versión, conservando estilos) --}}
        <div class="modal fade" id="modalEditPayment" aria-hidden="true" tabindex="-1" data-bs-backdrop="static" wire:ignore.self>
            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar Pago</h5>
                        <button type="button" class="btn-close m-0 fs-5" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <strong>Revisa los siguientes errores:</strong>
                                <ul class="mb-0 mt-2 ps-3">
                                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="row">
                            {{-- mismos campos/validaciones que tu original --}}
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="pay_plate_edit" class="form-label">Placa</label>
                                    <input id="pay_plate" type="text" class="form-control" placeholder="ABC-123"
                                           wire:model.live.debounce.300ms="plate">
                                    @error('plate') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Serie</label>
                                    <input type="text" class="form-control" wire:model.defer="serie">
                                    @error('serie') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Sucursal</label>
                                    <select class="form-select" wire:model.live="headquarter_id_form">
                                        <option value="">Seleccionar</option>
                                        @foreach($headquarters as $hq)
                                            <option value="{{ $hq->id }}">{{ $hq->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('headquarter_id_form') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Fecha Registro</label>
                                    <input type="date" class="form-control" wire:model.live="date_register" readonly>
                                    @error('date_register') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Fecha Pago</label>
                                    <input type="date" class="form-control"
                                           wire:model.live="date_payment"
                                           @if($type_form === 'PAGO')
                                               readonly
                                           min="{{ now()->toDateString() }}"
                                           max="{{ now()->toDateString() }}"
                                           style="background:#eee; pointer-events:none;"
                                        @endif
                                    >
                                    @error('date_payment') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Hora</label>
                                    <input type="time" class="form-control" wire:model.defer="hour">
                                    @error('hour') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Tipo</label>
                                    <select class="form-select" wire:model.live="type_form">
                                        <option value="">Seleccionar</option>
                                        <option value="PAGO">Pago</option>
                                        <option value="DEUDA">Deuda</option>
                                        <option value="RETRASO">Retraso</option>
                                    </select>
                                    @error('type_form') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Monto (S/)</label>
                                    <input type="number" step="0.01" min="0.01" class="form-control"
                                           wire:model.defer="amount"
                                           @if($type_form !== 'DEUDA' && !is_null($detected_cost)) readonly @endif>
                                    @error('amount') <span class="text-danger">{{ $message }}</span> @enderror

                                    @if($type_form === 'DEUDA')
                                        @if(!is_null($pending_debt))
                                            <small class="{{ $pending_debt > 0 ? 'text-muted' : 'text-warning' }}">
                                                Deuda pendiente mes anterior ({{ \Carbon\Carbon::now()->subMonth()->format('Y-m') }}):
                                                S/ {{ number_format($pending_debt, 2) }}
                                            </small>
                                        @endif
                                    @else
                                        @if(!is_null($detected_cost))
                                            <small class="text-muted">
                                                Costo detectado: S/ {{ number_format($detected_cost, 2) }} — Fecha: {{ $date_register }}
                                            </small>
                                        @else
                                            <small class="text-warning">
                                                No hay costo configurado para {{ $date_register }} y placa “{{ $plate }}”.
                                            </small>
                                        @endif
                                    @endif
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light-primary" wire:click="update">Editar</button>
                        <button type="button" class="btn btn-light-secondary" wire:click="cancelEdit" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="applyDate,export,save,update,daily,monthly,stats">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            function setGeoOnComponent(lat, lng) {
                const opened = document.querySelector('.modal.show'); if (!opened) return;
                const compEl = opened.closest('[wire\\:id]'); if (!compEl) return;
                const comp   = Livewire.find(compEl.getAttribute('wire:id')); if (!comp) return;
                comp.set('latitude',  Number(lat.toFixed(6)));
                comp.set('longitude', Number(lng.toFixed(6)));
            }
            function getGeoAndSet() {
                if (!navigator.geolocation) return;
                navigator.geolocation.getCurrentPosition(
                    (pos) => setGeoOnComponent(pos.coords.latitude, pos.coords.longitude),
                    () => {},
                    { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
                );
            }
            document.addEventListener('shown.bs.modal', function (e) {
                const id = e.target?.id || '';
                if (id === 'modalAddPayment' || id === 'modalEditPayment') getGeoAndSet();
            });
        })();

        document.addEventListener('hidden.bs.modal', function (e) {
            const id = e.target?.id || '';
            if (id === 'modalAddPayment')  { Livewire.dispatch('call', { method: 'cancelAdd'  }); }
            if (id === 'modalEditPayment') { Livewire.dispatch('call', { method: 'cancelEdit' }); }
        });
    </script>
@endpush
