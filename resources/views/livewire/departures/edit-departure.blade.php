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

                    {{-- Alert de éxito (autocierre a los 3s) --}}
                    @if (session()->has('edit_success'))
                        <div id="success-alert" class="alert alert-success">
                            <strong>La salida se actualizó de forma exitosa.</strong>
                        </div>
                    @endif

                    {{-- Errores de validación --}}
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <strong>Revisa los siguientes errores:</strong>
                            <ul class="mb-0 mt-2 ps-3">
                                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                            </ul>
                        </div>
                    @endif

                    <div id="edit-departure-form" class="row">
                        <div class="col-md-2 col-12">
                            <div class="mb-3">
                                <label for="dep_plate_edit" class="form-label">Placa</label>
                                <input id="dep_plate_edit"
                                       type="text"
                                       class="form-control form-control-sm input-uppercase"
                                       placeholder="ABC123"
                                       wire:model.defer="plate"
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
                            <div class="mb-3">
                                <label class="form-label">Salida (S/)</label>
                                <input type="number" step="0.01" class="form-control form-control-sm"
                                       wire:model.defer="price" min="1" inputmode="decimal">
                                @error('price') <span class="title-modules">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-2 col-12">
                            <div class="mb-3">
                                <label class="form-label">Pasajeros</label>
                                <input type="number" class="form-control form-control-sm"
                                       wire:model.defer="passenger" min="1"
                                       inputmode="numeric" pattern="[0-9]*">
                                @error('passenger') <span class="title-modules">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-2 col-12">
                            <div class="mb-3">
                                <label class="form-label">Pasaje (S/)</label>
                                <input type="number" step="0.01" class="form-control form-control-sm"
                                       wire:model.defer="passage" min="1" inputmode="decimal">
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
                            Guardar
                        </button>
                        <a href="{{ route('departures.index') }}" class="btn btn-sm btn-primary">Volver</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Overlay de carga (igual estilo) --}}
    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="update">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
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
                el.addEventListener('input', handler);
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
