<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Editar Pago</h4>
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

                    {{-- Alert de éxito (autocierre 3s) --}}
                    @if (session()->has('edit_success'))
                        <div id="success-alert" class="alert alert-success">
                            <strong>El pago se actualizó de forma exitosa.</strong>
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

                    <div id="edit-payment-form" class="row">
                        <div class="col-md-4 col-6">
                            <div class="mb-3">
                                <label for="pay_plate_edit" class="form-label">Placa</label>
                                <input id="pay_plate_edit" type="text" class="form-control form-control-sm" placeholder="ABC-123"
                                       wire:model.live.debounce.300ms="plate"
                                       style="text-transform: uppercase;"
                                       autocapitalize="characters"
                                       data-upper-plate>
                                @error('plate') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-4 col-6">
                            <div class="mb-3">
                                <label class="form-label">Serie</label>
                                <input type="text" class="form-control form-control-sm" wire:model.defer="serie">
                                @error('serie') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-4 col-6">
                            <div class="mb-3">
                                <label class="form-label">Sucursal</label>
                                <select class="form-control form-control-sm" wire:model.live="headquarter_id_form">
                                    <option value="">Seleccionar</option>
                                    @foreach($headquarters as $hq)
                                        <option value="{{ $hq->id }}">{{ $hq->name }}</option>
                                    @endforeach
                                </select>
                                @error('headquarter_id_form') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-4 col-6">
                            <div class="mb-3">
                                <label class="form-label">Fecha Registro</label>
                                <input type="date" class="form-control form-control-sm" wire:model.live="date_register" readonly>
                                @error('date_register') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-4 col-6">
                            <div class="mb-3">
                                <label class="form-label">Fecha Pago</label>
                                <input type="date" class="form-control form-control-sm"
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

                        <div class="col-md-4 col-6">
                            <div class="mb-3">
                                <label class="form-label">Hora</label>
                                <input type="time" class="form-control form-control-sm" wire:model.defer="hour">
                                @error('hour') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="col-md-4 col-6">
                            <div class="mb-3">
                                <label class="form-label">Tipo</label>
                                <select class="form-control form-control-sm" wire:model.live="type_form">
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
                                <input type="number" step="0.01" min="0.01" class="form-control form-control-sm"
                                       wire:model.defer="amount"
                                       @if($type_form !== 'DEUDA' && !is_null($detected_cost)) readonly @endif
                                       inputmode="decimal">
                                @error('amount') <span class="text-danger">{{ $message }}</span> @enderror

                                @if($type_form === 'DEUDA')
                                    @if(!is_null($pending_debt))
                                        <small class="{{ $pending_debt > 0 ? 'text-muted' : 'text-warning' }}">
                                            Deuda pendiente mes anterior: S/ {{ number_format($pending_debt, 2) }}
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

                        {{-- Geolocalización (oculto) --}}
                        <div class="col-md-6" style="position:absolute; left:-9999px;">
                            <div class="mb-3">
                                <label class="form-label visually-hidden">Latitud</label>
                                <input id="pay_lat_edit" type="text" class="form-control form-control-sm" wire:model.defer="latitude" readonly>
                            </div>
                        </div>
                        <div class="col-md-6" style="position:absolute; left:-9999px;">
                            <div class="mb-3">
                                <label class="form-label visually-hidden">Longitud</label>
                                <input id="pay_lng_edit" type="text" class="form-control form-control-sm" wire:model.defer="longitude" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary" wire:click="update" wire:loading.attr="disabled">
                            Guardar cambios
                        </button>
                        <a href="{{ route('payments.index') }}" class="btn btn-sm btn-primary">Volver</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Overlay de carga --}}
    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="update">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>

@push('styles')
    <style>
        input,button{
            font-size: 10px;
        }

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
