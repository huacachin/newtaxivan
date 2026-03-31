@push('datepicker_css')
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>
@endpush

<div class="container-fluid">
    <style>
        tr.row-retraso td, tr.row-retraso th,
        tr.row-deuda td, tr.row-deuda th { color: #cc0000 !important; font-weight: 600; }
    </style>
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">LISTADO GENERAL DE PAGOS</h4>
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

    @if(session('payment_success'))
    <div class="alert alert-success alert-dismissible fade show py-2 mb-2" role="alert">
        <i class="ti ti-circle-check me-1"></i> {{ session('payment_success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
    @endif

    @if(session('payment_error'))
    <div class="alert alert-danger alert-dismissible fade show py-2 mb-2" role="alert">
        <i class="ti ti-alert-circle me-1"></i> {{ session('payment_error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
    @endif

    <div class="row table-section">

        <!-- Tabla -->
        <div class="col-xl-12">
            <div class="card shadow-sm">



                <div class="card-body">
                    {{-- ===== Fila 1: radios de filtro ===== --}}


                    {{-- ===== Fila 2: Buscar + Fechas + Sucursal + Tipo + Aplicar ===== --}}
                    <div class="row g-2 align-items-end flex-wrap mb-2">

                        <div class="col-auto">
                            <div class="row g-2 mb-2">
                                <div class="col-12">
                                    <div class="d-flex flex-wrap align-items-center f-s-11">
                                        <span class="small text-muted">Buscar:</span>

                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input mg-e-4"
                                                   type="radio"
                                                   name="rbFilterPay"
                                                   id="rbPlatePay"
                                                   value="1"
                                                   wire:model="filter">
                                            <label class="form-check-label " for="rbPlatePay">Placa</label>
                                        </div>

                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input mg-e-4"
                                                   type="radio"
                                                   name="rbFilterPay"
                                                   id="rbUserPay"
                                                   value="2"
                                                   wire:model="filter">
                                            <label class="form-check-label" for="rbUserPay">Usuario</label>
                                        </div>

                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input  mg-e-4"
                                                   type="radio"
                                                   name="rbFilterPay"
                                                   id="rbSeriePay"
                                                   value="3"
                                                   wire:model="filter">
                                            <label class="form-check-label" for="rbSeriePay">Serie</label>
                                        </div>

                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input mg-e-4"
                                                   type="radio"
                                                   name="rbFilterPay"
                                                   id="rbMixedPay"
                                                   value=""
                                                   wire:model="filter">
                                            <label class="form-check-label" for="rbMixedPay">Todos</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <input type="search"
                                   class="form-control form-control-sm"
                                   placeholder="@switch($filter)
                                    @case('1') Buscar por placa... @break
                                    @case('2') Buscar por usuario... @break
                                    @case('3') Buscar por serie... @break
                                    @default Buscar...
                                @endswitch"
                                   aria-label="Buscar"
                                   wire:model="search">
                        </div>

                        <div class="col-auto">
                            <label class="form-label mb-1">Fecha Inicio</label>
                            <input type="text" id="date_start" class="form-control form-control-sm" wire:model.defer="ui_date_start">
                        </div>

                        <div class="col-auto">
                            <label class="form-label mb-1">Fecha Fin</label>
                            <input type="text" id="date_end" class="form-control form-control-sm" wire:model.defer="ui_date_end">
                        </div>

                        <div class="col-auto">
                            <label class="form-label mb-1">Sucursal</label>
                            <select class="form-select form-select-sm"
                                    wire:model="headquarter_id"
                                    aria-label="Selecciona sucursal"
                                    wire:key="hq-select">
                                <option value="">Todos</option>
                                @foreach($headquarters as $h)
                                    <option value="{{ $h->id }}">{{ $h->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-auto">
                            <label class="form-label mb-1">Tipo</label>
                            <select class="form-select form-select-sm"
                                    wire:model="type"
                                    aria-label="Selecciona tipo">
                                <option value="">Todos</option>
                                <option value="PAGO">Pago</option>
                                <option value="DEUDA">Deuda</option>
                                <option value="RETRASO">Retraso</option>
                            </select>
                        </div>

                    </div>

                    {{-- ===== Acciones (tal cual las tenías) ===== --}}
                    <div class="row g-2 my-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-sm btn-search"
                                        wire:click="applyDate"
                                       
                                        wire:target="applyDate">
                                    <i class="ti ti-search f-s-12"></i>
                                </button>
                                <a class="btn btn-sm btn-success" href="{{ route('payments.add') }}" target="_blank">
                                    <i class="ti ti-square-plus f-s-12"></i> Nuevo
                                </a>
                                <a href="{{ route('payments.daily') }}" class="btn btn-sm btn-primary">
                                    <i class="ti ti-report-analytics f-s-12"></i> Diario
                                </a>
                                <a href="{{ route('payments.monthly') }}" class="btn btn-sm btn-primary">
                                    <i class="ti ti-report-analytics f-s-12"></i> Mensual
                                </a>
                                <a href="{{ route('payments.stats') }}" class="btn btn-sm btn-primary">
                                    <i class="ti ti-report-analytics f-s-12"></i> Estadis.
                                </a>

                                <button class="btn btn-sm btn-primary" wire:click="export">
                                    <i class="ti ti-file-analytics f-s-12"></i>
                                </button>


                                <button class="btn btn-sm btn-primary" id="down">
                                    <i class="fa-solid fa-angle-down"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="text-center bg-primary">
                            <tr>
                                <th></th>
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

                            <tbody>
                            @forelse($payments as $p)
                                <tr @class(['row-retraso' => $p->type === 'RETRASO', 'row-deuda' => $p->type === 'DEUDA'])>
                                    <td width="50">
                                        @if($p->canBeEditedBy(auth()->user()))
                                        <a href="{{ route('payments.edit', $p->id) }}">
                                            <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"></i>
                                        </a>
                                        @endif
                                    </td>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $p->legacy_plate }}</td>
                                    <td>{{ $p->serie }}</td>
                                    <td>{{ optional($p->date_register)->format('Y-m-d') }}</td>
                                    <td>{{ optional($p->date_payment)->format('Y-m-d') }}</td>
                                    <td>{{ $p->hour }}</td>
                                    <td>{{ $p->type }}</td>
                                    <td>{{ $p->headquarter->name ?? '-' }}</td>
                                    <td>{{ $p->user->username ?? '-' }}</td>
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
                                    <td colspan="12" class="text-muted">No se encontraron resultados</td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="fw-semibold bg-primary">
                            <tr>
                                <th colspan="10">Total general:</th>
                                <th>{{ number_format($total_general, 2) }}</th>
                                <th></th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

@push('datepicker_js')
    <script>
        $( function() {
            var wire = @this;
            initLivewireDatepicker([
                ['#date_start', 'ui_date_start'],
                ['#date_end',   'ui_date_end'],
            ], wire);

            window.addEventListener('restore-payment-dates', function (e) {
                var d = (e.detail && e.detail[0]) ? e.detail[0] : (e.detail || {});
                var today = $.datepicker.formatDate('yy-mm-dd', new Date());
                var ds = d.date_start || today;
                var de = d.date_end   || today;
                $('#date_start').val(ds);
                $('#date_end').val(de);
            });
        });
    </script>
@endpush

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
                if (!navigator.geolocation) { Swal.fire({icon:'warning',title:'Ubicación requerida',text:'Activa tu ubicación para poder agregar',confirmButtonColor:'#3085d6'}); return; }
                navigator.geolocation.getCurrentPosition(
                    (pos) => setGeoOnComponent(pos.coords.latitude, pos.coords.longitude),
                    () => { Swal.fire({icon:'warning',title:'Ubicación requerida',text:'Activa tu ubicación para poder agregar',confirmButtonColor:'#3085d6'}); },
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
