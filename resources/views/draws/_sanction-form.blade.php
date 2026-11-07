{{-- Sancionar placas (Draws\Index::sanctionVehicles): se eligen las placas, el motivo y, si se
     quiere, una foto. Cada 2 sanciones acumuladas la placa se suspende sola 3 días de sorteo
     (sin domingos). Lo usan la grilla y el celular. La selección vive en Alpine; la foto sube
     con Livewire ($sanctionPhoto), achicada antes en el navegador (data-compress-image); mientras
     se procesa o sube, no se puede guardar (uploading).
     Requiere $canBlock, $sanctionable, $pendingSanctions, $suspensionPreview y $sanctionPhoto. --}}
@if($canBlock)
    @once
        @push('styles')
            <style>
                #sanctionModal .modal-body { background: #f4f7fb; }
                #sanctionModal .ds-step { font-size: 15px; font-weight: 700; margin: 14px 0 6px; color: #0f172a; }
                #sanctionModal .ds-step:first-of-type { margin-top: 0; }
                #sanctionModal .ds-input { font-size: 16px !important; border-radius: 12px; }
                #sanctionModal .ds-list { max-height: 38vh; overflow-y: auto; }
                #sanctionModal .ds-pick { display: flex; align-items: center; gap: 12px; padding: 9px 10px; border-bottom: 1px solid #f1f5f9; margin: 0; cursor: pointer; }
                #sanctionModal .ds-pick:has(input:checked) { background: #fff1f2; }
                #sanctionModal .ds-pick .form-check-input { width: 24px !important; height: 24px !important; margin: 0 !important; flex: none; }
                #sanctionModal .ds-cod { width: 30px; flex: none; color: #64748b; font-size: 13px; }
                #sanctionModal .ds-who { flex: 1 1 auto; min-width: 0; line-height: 1.2; }
                #sanctionModal .ds-plate { display: block; font-size: 17px; font-weight: 800; letter-spacing: .5px; }
                #sanctionModal .ds-driver { display: block; font-size: 13px; color: #64748b; }
                #sanctionModal .ds-count { flex: none; font-size: 12px; font-weight: 700; border-radius: 999px; padding: 3px 9px; background: #fef3c7; color: #92400e; text-align: center; line-height: 1.2; }
                #sanctionModal .ds-photo-btn { flex: 1; min-height: 48px; border-radius: 12px; font-weight: 600; font-size: 15px !important;
                    display: inline-flex; align-items: center; justify-content: center; gap: 6px; cursor: pointer; margin: 0; }
                #sanctionModal .ds-preview { max-width: 100%; max-height: 220px; border-radius: 12px; border: 1px solid #e2e8f0; object-fit: contain; background: #fff; }
                #sanctionModal .ds-save { min-height: 52px; border-radius: 14px; font-size: 17px !important; font-weight: 800; }
            </style>
        @endpush
    @endonce

    <div class="modal fade" id="sanctionModal" tabindex="-1" aria-hidden="true" wire:ignore.self
         x-data="{
             picked: [],
             q: '',
             reason: '',
             saving: false,
             uploading: false,
             save() {
                 if (this.saving) { return }
                 this.saving = true;
                 $wire.sanctionVehicles([...this.picked], this.reason).then((ok) => {
                     this.saving = false;
                     if (ok) { this.picked = []; this.reason = ''; this.q = ''; hideModal('sanctionModal') }
                 });
             },
         }"
         x-on:change.capture="if ($event.target.type === 'file') { uploading = $event.target.files.length > 0 }"
         x-on:livewire-upload-finish="uploading = false" x-on:livewire-upload-error="uploading = false" x-on:livewire-upload-cancel="uploading = false">
        <div class="modal-dialog modal-dialog-scrollable modal-lg modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ti ti-gavel"></i> Sancionar placas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning small py-2 mb-3">
                        <i class="ti ti-alert-triangle"></i> La placa que junte <b>2 sanciones</b> se suspende sola <b>3 días de sorteo</b>,
                        desde el próximo día sin sortear y sin contar domingos (el domingo puede entrar si la eligen).
                        @if($suspensionPreview)
                            Si se suspende ahora: <b>{{ $suspensionPreview }}</b>.
                        @endif
                    </div>

                    <div class="ds-step">1. ¿Qué placas? <span class="text-muted fw-normal small" x-show="picked.length" x-text="'(' + picked.length + ' marcadas)'"></span></div>
                    <input type="search" class="form-control ds-input mb-2" style="min-height: 44px;" placeholder="Buscar placa, COD o conductor" x-model="q">
                    <div class="bg-white rounded-3 border ds-list">
                        @foreach($sanctionable as $vehicle)
                            @php $accumulated = $pendingSanctions[$vehicle->id] ?? 0; @endphp
                            <label class="ds-pick" wire:key="sanction-pick-{{ $vehicle->id }}"
                                   data-search="{{ \Illuminate\Support\Str::lower($vehicle->plate.' '.$vehicle->sort_order.' '.$vehicle->driver?->name) }}"
                                   x-show="! q || $el.dataset.search.includes(q.toLowerCase())">
                                <input type="checkbox" class="form-check-input" value="{{ $vehicle->id }}" x-model.number="picked">
                                <span class="ds-cod">{{ $vehicle->sort_order }}</span>
                                <span class="ds-who">
                                    <span class="ds-plate">{{ $vehicle->plate }}</span>
                                    <span class="ds-driver">{{ \App\Services\DrawService::firstName($vehicle->driver?->name) }}</span>
                                </span>
                                @if($accumulated > 0)
                                    <span class="ds-count" title="Sanciones acumuladas: con {{ \App\Models\DrawSanction::SANCTIONS_TO_SUSPEND - $accumulated }} más se suspende">
                                        {{ $accumulated }} de {{ \App\Models\DrawSanction::SANCTIONS_TO_SUSPEND }}<br><small>sanciones</small>
                                    </span>
                                @endif
                            </label>
                        @endforeach
                    </div>

                    <div class="ds-step">2. Motivo</div>
                    <textarea class="form-control ds-input" rows="2" maxlength="255" x-model="reason" placeholder="¿Por qué se sanciona? (queda en el historial)"></textarea>

                    <div class="ds-step">3. Foto <span class="text-muted fw-normal small">(opcional)</span></div>
                    @if($sanctionPhoto && $sanctionPhoto->isPreviewable())
                        <div class="text-center">
                            <img src="{{ $sanctionPhoto->temporaryUrl() }}" alt="Foto de la sanción" class="ds-preview">
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeSanctionPhoto" wire:loading.attr="disabled">
                                    <i class="ti ti-trash"></i> Quitar foto
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="d-flex gap-2">
                            <input id="sanction_photo_camera" type="file" class="visually-hidden" accept="image/*" capture="environment"
                                   wire:model="sanctionPhoto" data-compress-image>
                            <input id="sanction_photo_gallery" type="file" class="visually-hidden" accept="image/*"
                                   wire:model="sanctionPhoto" data-compress-image>
                            <label for="sanction_photo_camera" class="btn btn-outline-primary ds-photo-btn"><i class="ti ti-camera"></i> Tomar foto</label>
                            <label for="sanction_photo_gallery" class="btn btn-outline-secondary ds-photo-btn"><i class="ti ti-photo"></i> Galería</label>
                        </div>
                    @endif
                    @error('sanctionPhoto') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    <small data-photo-compress-status class="text-muted align-items-center gap-2 mt-2 d-none">
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando la foto...
                    </small>
                    <small wire:loading.flex wire:target="sanctionPhoto" class="text-primary align-items-center gap-2 mt-2">
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Subiendo la foto...
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger w-100 ds-save" x-on:click="save()"
                            :disabled="saving || uploading || ! picked.length || reason.trim().length < 3">
                        <i class="ti ti-gavel"></i> Sancionar (<span x-text="picked.length">0</span>)
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
