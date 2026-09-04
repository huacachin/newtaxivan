@push('datepicker_css')
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>
@endpush
<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">PAGOS : ACTUALIZAR</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-currency-dollar f-s-16"></i>
                    <a href="{{ route('payments.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Pagos</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <span class="f-s-14">Editar</span>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">

                    {{-- Errores de validación --}}
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <strong>Revisa los siguientes errores:</strong>
                            <ul class="mb-0 mt-2 ps-3">
                                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                            </ul>
                        </div>
                    @endif

                    <div id="edit-payment-form" class="row">

                        {{-- Placa --}}
                        <div class="col-md-auto col-sm-12">
                            <div class="mb-3">
                                <label for="pay_plate_edit" class="form-label">Placa (*)</label>
                                <input id="pay_plate_edit" type="text" class="form-control form-control-sm input-uppercase" placeholder="ABC123"
                                       wire:model.live.debounce.300ms="plate"
                                       autocapitalize="characters"
                                       data-upper-plate>
                                @error('plate') <span class="title-modules">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Serie --}}
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">Serie (*)</label>
                                <input type="text" class="form-control form-control-sm" wire:model.defer="serie">
                                @error('serie') <span class="title-modules">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Fecha Registro --}}
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">Fecha Reg. (*)</label>
                                <input id="pay_date_register" type="text" class="form-control form-control-sm input-readonly"
                                       wire:model.defer="date_register" readonly>
                                @error('date_register') <span class="title-modules">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Fecha Pago --}}
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">Fecha Pago (*)</label>
                                <input id="pay_date_payment" type="text"
                                       class="form-control form-control-sm {{ $type_form !== 'RETRASO' ? 'input-readonly' : '' }}"
                                       wire:model.defer="date_payment"
                                       @if($type_form !== 'RETRASO') readonly @endif>
                                @error('date_payment') <span class="title-modules">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Tipo --}}
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">Tipo (*)</label>
                                <select class="form-select form-select-sm" wire:model.live="type_form">
                                    <option value="">Seleccionar</option>
                                    <option value="PAGO">Pago</option>
                                    <option value="DEUDA">Deuda</option>
                                    <option value="RETRASO">Retraso</option>
                                </select>
                                @error('type_form') <span class="title-modules">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Monto --}}
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">Monto (*)</label>
                                <div x-data="numericChips({
                                    storageKey: 'payments.amount',
                                    server: () => $wire.amountSuggestions,
                                    decimals: 2
                                })">
                                    <input type="text" inputmode="decimal" class="form-control form-control-sm"
                                           x-ref="input"
                                           name="payment_amount" autocomplete="on"
                                           wire:model.defer="amount"
                                           @if($type_form !== 'DEUDA' && !is_null($detected_cost)) readonly @endif>
                                    @if($type_form === 'DEUDA' || is_null($detected_cost))
                                        <div class="num-chips" x-show="suggestions.length" x-cloak>
                                            <template x-for="(s, i) in suggestions" :key="s.value">
                                                <button type="button" :class="badgeClass(s.source)"
                                                        :title="`${s.hint} (Alt+${i+1})`"
                                                        @click="pick(s.value)" x-text="formatted(s.value)"></button>
                                            </template>
                                        </div>
                                    @endif
                                </div>
                                @error('amount') <span class="title-modules">{{ $message }}</span> @enderror

                                @if($type_form === 'DEUDA')
                                    @if(!is_null($pending_debt))
                                        <small class="{{ $pending_debt > 0 ? 'text-muted' : 'text-warning' }}">
                                            Deuda pendiente mes anterior: S/ {{ number_format($pending_debt, 2) }}
                                        </small>
                                    @endif
                                @else
                                    @if(!is_null($detected_cost))
                                        <small class="text-muted">
                                            Costo detectado: S/ {{ number_format($detected_cost, 2) }}
                                        </small>
                                    @else
                                        <small class="text-warning">
                                            Sin costo para {{ $date_register }} / "{{ $plate }}".
                                        </small>
                                    @endif
                                @endif
                            </div>
                        </div>

                        {{-- Sucursal --}}
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">Sucursal (*)</label>
                                <select class="form-select form-select-sm" wire:model.live="headquarter_id_form">
                                    <option value="">Seleccionar</option>
                                    @foreach($headquarters as $hq)
                                        <option value="{{ $hq->id }}">{{ $hq->name }}</option>
                                    @endforeach
                                </select>
                                @error('headquarter_id_form') <span class="title-modules">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Hora (oculta, se envía al guardar) --}}
                        <input type="hidden" wire:model.defer="hour">

                        {{-- Usuario (solo lectura) --}}
                        <div class="w-100"></div>
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">Usuario (*)</label>
                                <input type="text" class="form-control form-control-sm input-readonly"
                                       value="{{ auth()->user()->name }}" readonly>
                            </div>
                        </div>

                        {{-- Geolocalización (oculto) --}}
                        <div class="col-md-6 input-offscreen">
                            <div class="mb-3">
                                <label class="form-label visually-hidden">Latitud</label>
                                <input id="pay_lat_edit" type="text" class="form-control form-control-sm" wire:model.defer="latitude" readonly>
                            </div>
                        </div>
                        <div class="col-md-6 input-offscreen">
                            <div class="mb-3">
                                <label class="form-label visually-hidden">Longitud</label>
                                <input id="pay_lng_edit" type="text" class="form-control form-control-sm" wire:model.defer="longitude" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary" wire:click="update" wire:loading.attr="disabled">
                            <span wire:loading wire:target="update" class="spinner-border spinner-border-sm"></span>
                            <span wire:loading.remove wire:target="update">Guardar cambios</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" wire:click="questionDelete({{ $paymentId }})">Eliminar</button>
                        <a href="{{ route('payments.index') }}" class="btn btn-sm btn-secondary">Volver</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Overlay de carga --}}
</div>

@push('datepicker_js')
    <script>
        $( function() {
            var wire = @this;
            initLivewireDatepicker([
                ['#pay_date_register', 'date_register'],
                ['#pay_date_payment',  'date_payment'],
            ], wire);
        });
    </script>
@endpush

@push('scripts')
    <script>
        (function () {
            // ---- Forzar mayúsculas reales en placa ----
            function forceUppercase(el) {
                if (!el) return;
                const start = el.selectionStart, end = el.selectionEnd;
                const upper = (el.value || '').toUpperCase();
                if (el.value !== upper) {
                    el.value = upper;
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    try { el.setSelectionRange(start, end); } catch (e) {}
                }
            }
            function bindUpperFor(el) {
                if (!el || el.__upperBound) return;
                const handler = () => forceUppercase(el);
                el.addEventListener('input', handler);
                el.addEventListener('change', handler);
                el.addEventListener('paste', () => setTimeout(handler, 0));
                el.addEventListener('blur', handler);
                el.__upperBound = true;
                handler();
            }
            function initUpper() {
                document.querySelectorAll('[data-upper-plate]').forEach(bindUpperFor);
            }
            document.addEventListener('DOMContentLoaded', initUpper);

            // ---- Geolocalización al cargar pantalla ----
            function fillGeo(latId, lngId) {
                if (!navigator.geolocation) return;
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        const lat = pos.coords.latitude.toFixed(6);
                        const lng = pos.coords.longitude.toFixed(6);
                        const latInput = document.getElementById(latId);
                        const lngInput = document.getElementById(lngId);
                        if (latInput && lngInput) {
                            latInput.value = lat;
                            lngInput.value = lng;
                            latInput.dispatchEvent(new Event('input', { bubbles: true }));
                            lngInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    },
                    () => {},
                    { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
                );
            }
            document.addEventListener('DOMContentLoaded', function () {
                fillGeo('pay_lat_edit','pay_lng_edit');
                const plate = document.getElementById('pay_plate_edit');
                if (plate) plate.focus();
            });
        })();
    </script>
@endpush
