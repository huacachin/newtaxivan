<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">AGREGAR PAGO</h4>
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
                    <span class="f-s-14">Agregar</span>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">

                    {{-- Alert de éxito (autocierre 3s) --}}
                    @if (session()->has('add_success'))
                        <div id="success-alert" class="alert alert-success">
                            <strong>El pago se agregó de forma exitosa.</strong>
                        </div>
                    @endif

                    {{-- Errores --}}
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <strong>Revisa los siguientes errores:</strong>
                            <ul class="mb-0 mt-2 ps-3">
                                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Formulario en grilla: escritorio 4 columnas (Placa, Serie, Fechas / Tipo, Sucursal,
                         Monto doble); celular de a pares y el Monto a todo el ancho. Debajo, a todo el
                         ancho, el aviso de deuda o la elección del mes (Deuda). Los campos son fijos y
                         solo su contenido es condicional, para que el morph de Livewire no los corra. --}}
                    <style>
                        .pay-add { max-width: 940px; }
                        .pay-add__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 14px; }
                        .pay-add__field { min-width: 0; }
                        .pay-add__field .form-label { margin-bottom: 4px; }
                        .pay-add__field--amount { grid-column: 1 / -1; }
                        .pay-add__note { display: block; margin-top: 4px; font-size: 12px; line-height: 1.35; }
                        .pay-add__debt { margin-top: 14px; }
                        .pay-add__debt .debt-alert { margin-bottom: 0; }
                        .pay-add__debt .debt-months { margin-top: 0; }
                        .pay-add__actions { margin-top: 16px; }
                        @media (min-width: 768px) {
                            .pay-add__grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
                            .pay-add__field--amount { grid-column: span 2; }
                        }
                    </style>

                    <div id="add-payment-form" class="pay-add">
                        <div class="pay-add__grid">
                            <div class="pay-add__field">
                                <label for="pay_plate" class="form-label">Placa</label>
                                <input id="pay_plate" type="text" class="form-control form-control-sm input-uppercase" placeholder="ABC123"
                                       wire:model.live.debounce.300ms="plate"
                                       autocapitalize="characters"
                                       data-upper-plate>
                                @error('plate') <span class="title-modules pay-add__note">{{ $message }}</span> @enderror
                            </div>

                            <div class="pay-add__field">
                                <label for="pay_serie" class="form-label">Serie</label>
                                <input id="pay_serie" type="text" class="form-control form-control-sm" wire:model.defer="serie">
                                @error('serie') <span class="title-modules pay-add__note">{{ $message }}</span> @enderror
                            </div>

                            <div class="pay-add__field">
                                <label class="form-label">Fecha Registro</label>
                                <input type="date" class="form-control form-control-sm" wire:model.live="date_register" readonly>
                                @error('date_register') <span class="title-modules pay-add__note">{{ $message }}</span> @enderror
                            </div>

                            <div class="pay-add__field">
                                <label class="form-label">Fecha Pago</label>
                                <input type="date" class="form-control form-control-sm {{ $type_form !== 'RETRASO' ? 'input-readonly' : '' }}"
                                       wire:model.live="date_payment"
                                       @if($type_form !== 'RETRASO')
                                           readonly
                                       @endif
                                >
                                @error('date_payment') <span class="title-modules pay-add__note">{{ $message }}</span> @enderror
                            </div>

                            <div class="pay-add__field">
                                <label class="form-label">Tipo</label>
                                <select class="form-select form-select-sm" wire:model.live="type_form">
                                    <option value="">Seleccionar</option>
                                    <option value="PAGO">Pago</option>
                                    <option value="DEUDA">Deuda</option>
                                    <option value="RETRASO">Retraso</option>
                                </select>
                                @error('type_form') <span class="title-modules pay-add__note">{{ $message }}</span> @enderror
                            </div>

                            <div class="pay-add__field">
                                <label class="form-label">Sucursal</label>
                                <select class="form-select form-select-sm" wire:model.live="headquarter_id_form">
                                    <option value="">Seleccionar</option>
                                    @foreach($headquarters as $hq)
                                        <option value="{{ $hq->id }}">{{ $hq->name }}</option>
                                    @endforeach
                                </select>
                                @error('headquarter_id_form') <span class="title-modules pay-add__note">{{ $message }}</span> @enderror
                            </div>

                            <div class="pay-add__field pay-add__field--amount">
                                <label class="form-label">Monto (S/)</label>
                                <div x-data="numericChips({
                                    storageKey: 'payments.amount',
                                    server: () => $wire.amountSuggestions,
                                    decimals: 2
                                })">
                                    <input type="text" inputmode="decimal" class="form-control form-control-sm"
                                           x-ref="input"
                                           name="payment_amount" autocomplete="on"
                                           wire:model.defer="amount"
                                           @if($type_form !== 'DEUDA' && !is_null($detected_cost)) readonly @endif
                                           placeholder="0.00">
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
                                @error('amount') <span class="title-modules pay-add__note">{{ $message }}</span> @enderror
                                @if($type_form !== 'DEUDA')
                                    @if(!is_null($detected_cost))
                                        <small class="text-muted pay-add__note">Costo detectado: S/ {{ number_format($detected_cost, 2) }} — Fecha: {{ $date_register }}</small>
                                    @else
                                        <small class="text-warning pay-add__note">No hay costo configurado para {{ $date_register }} y placa “{{ $plate }}”.</small>
                                    @endif
                                @endif
                            </div>
                        </div>

                        {{-- Deuda: con Pago o Retraso, el aviso; con Deuda, la elección del mes. El
                             contenedor va SIEMPRE: sin los marcadores de morph de Livewire un @if entre
                             campos los corría al redibujar (el select de Tipo quedaba como el de Sucursal). --}}
                        <div class="pay-add__debt" wire:key="pay-debt">
                            @if($type_form === 'DEUDA')
                                @include('livewire.payments._debt-months')
                            @elseif(($pending_debt ?? 0) > 0)
                                @include('livewire.payments._debt-warning')
                            @endif
                        </div>

                        {{-- Geolocalización (oculto) --}}
                        <div class="input-offscreen">
                            <label class="form-label visually-hidden">Latitud</label>
                            <input id="pay_lat_add" type="text" class="form-control form-control-sm" wire:model.defer="latitude" readonly>
                        </div>
                        <div class="input-offscreen">
                            <label class="form-label visually-hidden">Longitud</label>
                            <input id="pay_lng_add" type="text" class="form-control form-control-sm" wire:model.defer="longitude" readonly>
                        </div>
                    </div>

                    <div class="d-flex gap-2 pay-add__actions">
                        <button type="button" class="btn btn-sm btn-primary" wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading wire:target="save" class="spinner-border spinner-border-sm"></span>
                            <span wire:loading.remove wire:target="save">Agregar</span>
                        </button>
                        <a href="{{ route('payments.index') }}" class="btn btn-sm btn-primary">Volver</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Overlay de carga --}}
</div>

@push('scripts')
    <script>
        (function () {
            // ---- Alert autocierre a los 3s ----
            let successTimer = null;
            function armAutoHide() {
                const alertBox = document.getElementById('success-alert');
                if (!alertBox) return;
                if (successTimer) { clearTimeout(successTimer); successTimer = null; }
                successTimer = setTimeout(() => {
                    alertBox.classList.add('d-none');
                    alertBox.style.display = 'none';
                    successTimer = null;
                }, 3000);
            }
            document.addEventListener('DOMContentLoaded', armAutoHide);
            const mo = new MutationObserver(() => {
                if (document.getElementById('success-alert')) armAutoHide();
            });
            mo.observe(document.body, { childList: true, subtree: true });

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
                // OJO: sin listener de 'input'. Mutar el valor en pleno tecleo rompe la
                // composicion de los teclados moviles (Android duplica los caracteres).
                // Mientras se escribe, .input-uppercase ya lo muestra en mayusculas por CSS
                // y el servidor normaliza con strtoupper; el valor real se corrige al salir
                // del campo, al pegar o en change.
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
                fillGeo('pay_lat_add','pay_lng_add');
                const plate = document.getElementById('pay_plate');
                if (plate) plate.focus();
            });
        })();
    </script>
@endpush
