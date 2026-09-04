@push('datepicker_css')
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>
@endpush
<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">EDITAR SALIDA</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-door-exit f-s-16"></i>
                    <a href="{{ route('departures.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Salidas</span>
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

                    <div id="edit-departure-form" class="row" @if(!$plateExists) style="color:red;font-weight:bold" @endif>
                    @if(!$plateExists)
                    <style>#edit-departure-form input[type=text],#edit-departure-form input[type=number],#edit-departure-form input[type=date],#edit-departure-form select,#edit-departure-form textarea,#edit-departure-form label,#edit-departure-form input::placeholder,#edit-departure-form textarea::placeholder{color:red !important;font-weight:bold !important;}</style>
                    @endif
                        <div class="col-md-2 col-12">
                            <div class="mb-3">
                                <label for="dep_plate_edit" class="form-label">Placa</label>
                                <input id="dep_plate_edit"
                                       type="text"
                                       class="form-control form-control-sm input-uppercase"
                                       placeholder="ABC123"
                                       wire:model.live.debounce.300ms="plate"
                                       autocapitalize="characters"
                                       data-upper-plate>
                                @error('plate') <span class="title-modules">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-2 col-12">
                            <div class="mb-3">
                                <label class="form-label">Fecha</label>
                                <input id="date" type="text" class="form-control form-control-sm" wire:model.defer="date">
                                @error('date') <span class="title-modules">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-2 col-12">
                            <div class="mb-3">
                                <label class="form-label">Sucursal</label>
                                <select class="form-select form-select-sm" wire:model.defer="headquarter_id">
                                    <option value="">Seleccionar</option>
                                    @foreach($listHeadquarters as $hq)
                                        <option value="{{ $hq->id }}">{{ $hq->name }}</option>
                                    @endforeach
                                </select>
                                @error('headquarter_id') <span class="title-modules">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-2 col-12">
                            <div class="mb-3" x-data="numericChips({ storageKey: 'departures.price', decimals: 2 })">
                                <label class="form-label">Salida (S/)</label>
                                <input type="text" inputmode="decimal" class="form-control form-control-sm"
                                       x-ref="input"
                                       name="departure_price" autocomplete="on"
                                       wire:model.defer="price">
                                <div class="num-chips" x-show="suggestions.length" x-cloak>
                                    <template x-for="(s, i) in suggestions" :key="s.value">
                                        <button type="button" :class="badgeClass(s.source)"
                                                :title="`${s.hint} (Alt+${i+1})`"
                                                @click="pick(s.value)" x-text="formatted(s.value)"></button>
                                    </template>
                                </div>
                                @error('price') <span class="title-modules">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-2 col-12">
                            <div class="mb-3" x-data="numericChips({
                                storageKey: 'departures.passenger',
                                server: () => $wire.passengerSuggestions,
                                decimals: 0
                            })">
                                <label class="form-label">Pasajeros</label>
                                <input type="text" inputmode="numeric" class="form-control form-control-sm"
                                       x-ref="input"
                                       name="departure_passenger" autocomplete="on"
                                       wire:model.defer="passenger">
                                <div class="num-chips" x-show="suggestions.length" x-cloak>
                                    <template x-for="(s, i) in suggestions" :key="s.value">
                                        <button type="button" :class="badgeClass(s.source)"
                                                :title="`${s.hint} (Alt+${i+1})`"
                                                @click="pick(s.value)" x-text="formatted(s.value)"></button>
                                    </template>
                                </div>
                                @error('passenger') <span class="title-modules">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-2 col-12">
                            <div class="mb-3" x-data="numericChips({
                                storageKey: 'departures.passage',
                                server: () => $wire.passageSuggestions,
                                decimals: 2
                            })">
                                <label class="form-label">Pasaje (S/)</label>
                                <input type="text" inputmode="decimal" class="form-control form-control-sm"
                                       x-ref="input"
                                       name="departure_passage" autocomplete="on"
                                       wire:model.defer="passage">
                                <div class="num-chips" x-show="suggestions.length" x-cloak>
                                    <template x-for="(s, i) in suggestions" :key="s.value">
                                        <button type="button" :class="badgeClass(s.source)"
                                                :title="`${s.hint} (Alt+${i+1})`"
                                                @click="pick(s.value)" x-text="formatted(s.value)"></button>
                                    </template>
                                </div>
                                @error('passage') <span class="title-modules">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Geolocalización (oculto) --}}
                        <div class="col-md-6 input-offscreen">
                            <div class="mb-3">
                                <label class="form-label visually-hidden">Latitud</label>
                                <input id="dep_lat_edit" type="text" class="form-control form-control-sm" wire:model.defer="latitude" readonly>
                            </div>
                        </div>
                        <div class="col-md-6 input-offscreen">
                            <div class="mb-3">
                                <label class="form-label visually-hidden">Longitud</label>
                                <input id="dep_lng_edit" type="text" class="form-control form-control-sm" wire:model.defer="longitude" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary" wire:click="update" wire:loading.attr="disabled">
                            <span wire:loading wire:target="update" class="spinner-border spinner-border-sm"></span>
                            <span wire:loading.remove wire:target="update">Guardar</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" wire:click="questionDelete({{ $depId }})">Eliminar</button>
                        <a href="{{ route('departures.index') }}" class="btn btn-sm btn-secondary">Volver</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Overlay de carga (igual estilo) --}}
</div>

@push('datepicker_js')
    <script>
        $( function() {
            var wire = @this;
            initLivewireDatepicker([
                ['#date', 'date'],
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
                // OJO: sin listener de 'input'. Mutar el valor en pleno tecleo rompe la
                // composicion de los teclados moviles (Android duplica los caracteres).
                // Mientras se escribe, .input-uppercase ya lo muestra en mayusculas por CSS
                // y el servidor normaliza con strtoupper; el valor real se corrige al salir
                // del campo, al pegar o en change.
                el.addEventListener('change', handler);
                el.addEventListener('paste', () => setTimeout(handler, 0));
                el.addEventListener('blur', handler);
                el.__upperBound = true;
                handler(); // convertir de inmediato
            }
            function initUpper() {
                document.querySelectorAll('[data-upper-plate]').forEach(bindUpperFor);
            }
            document.addEventListener('DOMContentLoaded', initUpper);
            document.addEventListener('livewire:initialized', () => {
                initUpper();
                document.addEventListener('livewire:navigated', () => {
                    initUpper();
                    armAutoHide();
                });
            });
            const moUpper = new MutationObserver(initUpper);
            moUpper.observe(document.body, { childList: true, subtree: true });

            // ---- Geolocalización (igual que en modal editar) ----
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
                fillGeo('dep_lat_edit','dep_lng_edit');
                const plate = document.getElementById('dep_plate_edit');
                if (plate) plate.focus();
            });
        })();
    </script>
@endpush
