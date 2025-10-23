{{-- resources/views/livewire/departures/add-departure.blade.php --}}
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Agregar salida</h4>
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
                    <span class="f-s-14">Agregar</span>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">


                    @if (session()->has('add_success'))
                        <div id="success-alert" class="alert alert-success">
                            <strong>La salida se agrego de forma exitosa.</strong>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <strong>Revisa los siguientes errores:</strong>
                            <ul class="mb-0 mt-2 ps-3">
                                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-md-4 col-6">
                            <div class="mb-3">
                                <label for="dep_plate" class="form-label">Placa</label>
                                <input id="dep_plate"
                                       type="text"
                                       class="form-control"
                                       placeholder="ABC-123"
                                       wire:model.defer="plate"
                                       style="text-transform: uppercase;"
                                       autocapitalize="characters"
                                       data-upper-plate>
                                @error('plate') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-4 col-6">
                            <div class="mb-3">
                                <label class="form-label">Fecha</label>
                                <input type="date" class="form-control" wire:model.defer="date" readonly>
                                @error('date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-4 col-6">
                            <div class="mb-3">
                                <label class="form-label">Sucursal</label>
                                <select class="form-select" wire:model.defer="headquarter_id">
                                    <option value="">Seleccionar</option>
                                    @foreach($listHeadquarters as $hq)
                                        <option value="{{ $hq->id }}">{{ $hq->name }}</option>
                                    @endforeach
                                </select>
                                @error('headquarter_id') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-4 col-6">
                            <div class="mb-3">
                                <label class="form-label">Precio (S/)</label>
                                <input type="number" step="0.01" min="1" class="form-control"
                                       wire:model.defer="price" inputmode="decimal">
                                @error('price') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-4 col-6">
                            <div class="mb-3">
                                <label class="form-label">Pasajeros</label>
                                <input type="number" class="form-control"
                                       wire:model.defer="passenger" min="1"
                                       inputmode="numeric" pattern="[0-9]*">
                                @error('passenger') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-4 col-6">
                            <div class="mb-3">
                                <label class="form-label">Pasaje (S/)</label>
                                <input type="number" class="form-control"
                                       wire:model.defer="passage" step="0.01" min="1"
                                       inputmode="decimal">
                                @error('passage') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Geolocalización (igual que en el modal, oculto) --}}
                        <div class="col-md-6" style="position:absolute; left:-9999px;">
                            <div class="mb-3">
                                <label class="form-label visually-hidden">Latitud</label>
                                <input id="dep_lat_add" type="text" class="form-control" wire:model.defer="latitude" readonly>
                            </div>
                        </div>
                        <div class="col-md-6" style="position:absolute; left:-9999px;">
                            <div class="mb-3">
                                <label class="form-label visually-hidden">Longitud</label>
                                <input id="dep_lng_add" type="text" class="form-control" wire:model.defer="longitude" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled">
                            Agregar
                        </button>
                        <a href="{{ route('departures.index') }}" class="btn btn-primary">Volver</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Overlay de carga (mismo estilo que en index) --}}
    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="save">
        <div>
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>

@push('styles')
    <style>
        .screen-overlay {
            position: fixed; inset: 0; display: none;
            align-items: center; justify-content: center;
            background: rgba(0,0,0,.35); backdrop-filter: blur(2px);
            z-index: 2000; pointer-events: all;
        }
    </style>
@endpush

@push('scripts')
    <script>
        (function () {
            // Timer global para evitar múltiples timeouts al re-renderizar
            let successTimer = null;

            function armAutoHide() {
                const alertBox = document.getElementById('success-alert');
                if (!alertBox) return;

                // Si ya hay un timer previo, lo limpiamos
                if (successTimer) {
                    clearTimeout(successTimer);
                    successTimer = null;
                }

                successTimer = setTimeout(() => {
                    // Oculta usando clase de Bootstrap (y fallback por si acaso)
                    alertBox.classList.add('d-none');
                    alertBox.style.display = 'none';
                    successTimer = null;
                }, 5000); // 3 segundos
            }

            // 1) Al cargar la página
            document.addEventListener('DOMContentLoaded', armAutoHide);

            // 2) Si Livewire re-renderiza y el alert vuelve a aparecer,
            //    lo detectamos mediante un MutationObserver robusto.
            const mo = new MutationObserver((mutations) => {
                for (const m of mutations) {
                    if (m.type === 'childList') {
                        // ¿apareció el alert?
                        if (document.getElementById('success-alert')) {
                            armAutoHide();
                            break;
                        }
                    } else if (m.type === 'attributes' && m.target.id === 'success-alert') {
                        armAutoHide();
                        break;
                    }
                }
            });
            mo.observe(document.body, { childList: true, subtree: true, attributes: true });

            // 3) (Opcional) hooks de Livewire v3 si están disponibles
            document.addEventListener('livewire:navigated', armAutoHide);
            document.addEventListener('livewire:initialized', armAutoHide);

            // Mantén tu geo + focus inicial si lo usas
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
                fillGeo('dep_lat_add','dep_lng_add');
                const plate = document.getElementById('dep_plate');
                if (plate) plate.focus();
            });
        })();

        (function () {
            // Convierte a mayúsculas preservando el cursor
            function forceUppercase(el) {
                if (!el) return;
                const start = el.selectionStart, end = el.selectionEnd;
                const upper = (el.value || '').toUpperCase();
                if (el.value !== upper) {
                    el.value = upper;
                    // Notifica a Livewire del cambio
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    // Restaura posición del cursor
                    try { el.setSelectionRange(start, end); } catch (e) {}
                }
            }

            // Enlaza eventos relevantes
            function bindUpperFor(el) {
                if (!el || el.__upperBound) return;
                const handler = () => forceUppercase(el);
                el.addEventListener('input', handler);
                el.addEventListener('change', handler);
                el.addEventListener('paste', () => setTimeout(handler, 0)); // tras pegar
                el.addEventListener('blur', handler);
                el.__upperBound = true;
                // Convierte de inmediato por si viene prellenado
                handler();
            }

            // Escanea y enlaza
            function initUpper() {
                document.querySelectorAll('[data-upper-plate]').forEach(bindUpperFor);
            }

            // Al cargar
            document.addEventListener('DOMContentLoaded', initUpper);

            // Re-engancha tras re-render de Livewire (v3)
            document.addEventListener('livewire:initialized', () => {
                initUpper();
                // En v3, cada navegación/render dispara este evento
                document.addEventListener('livewire:navigated', initUpper);
            });

            // Fallback robusto: observar el DOM por si el input aparece luego
            const mo = new MutationObserver(() => initUpper());
            mo.observe(document.body, { childList: true, subtree: true });
        })();
    </script>
@endpush

