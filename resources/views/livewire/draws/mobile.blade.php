{{-- Sorteos en el celular (Draws\Mobile): por día, sin grilla. Día pendiente = tarjetas con
     interruptor + "Sortear" fijo abajo; día sorteado = orden de salida y Compartir PDF.
     Bloquear, desbloquear (placa o día) e historial usan las mismas acciones que la grilla. --}}
@push('styles')
    <style>
        .quick-access, .go-top { display: none !important; }
        .dm { max-width: 560px; margin: 0 auto; padding: 0 2px 110px; color: #0f172a; }
        .dm-top { display: flex; align-items: center; gap: 8px; margin: 4px 0 10px; }
        .dm-top h1 { font-size: 22px; font-weight: 800; margin: 0; flex: 1; color: #0f172a; }
        .dm-top .btn { border-radius: 12px; font-weight: 600; font-size: 14px !important; min-height: 40px; display: inline-flex; align-items: center; gap: 4px; }
        .dm-chips { display: flex; gap: 8px; overflow-x: auto; padding: 2px 0 10px; scrollbar-width: none; }
        .dm-chips::-webkit-scrollbar { display: none; }
        .dm-chip { flex: none; border: 2px solid #e2e8f0; background: #fff; border-radius: 14px; padding: 8px 12px; min-width: 86px; text-align: center; line-height: 1.15; }
        .dm-chip b { display: block; font-size: 15px; color: #0f172a; }
        .dm-chip small { font-size: 12px; font-weight: 600; }
        .dm-chip.is-done small { color: #16a34a; }
        .dm-chip.is-pending small { color: #b45309; }
        .dm-chip.is-sunday small { color: #6d28d9; }
        .dm-chip.is-active { border-color: #009BDC; background: #e0f2fe; }
        .dm-head { background: #fff; border-radius: 18px; padding: 14px 16px; margin-bottom: 10px; border: 1px solid #e2e8f0; }
        .dm-head h2 { font-size: 20px; font-weight: 800; margin: 0 0 4px; }
        .dm-head p { margin: 4px 0 0; font-size: 14px; color: #64748b; }
        .dm-tag { display: inline-block; font-size: 12px; font-weight: 700; border-radius: 999px; padding: 3px 10px; }
        .dm-tag--pending { background: #fef3c7; color: #92400e; }
        .dm-tag--sunday { background: #ede9fe; color: #5b21b6; }
        .dm-tag--done { background: #dcfce7; color: #166534; }
        .dm-tools { display: flex; gap: 8px; margin-bottom: 10px; position: sticky; top: 0; z-index: 5; background: #f4f7fb; padding: 6px 0; }
        .dm-tools input { font-size: 16px !important; min-height: 44px; border-radius: 12px; }
        .dm-tools .btn { border-radius: 12px; min-height: 44px; font-size: 14px !important; font-weight: 600; white-space: nowrap; }
        .dm-list { display: flex; flex-direction: column; gap: 8px; }
        .dm-card { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px;
            padding: 10px 12px; margin: 0; cursor: pointer; -webkit-tap-highlight-color: transparent; }
        .dm-card:has(input:checked) { border-color: #7dd3fc; background: #f0f9ff; }
        .dm-cod { flex: none; width: 34px; text-align: center; font-size: 13px; font-weight: 700; color: #64748b; }
        .dm-who { flex: 1 1 auto; min-width: 0; line-height: 1.2; }
        .dm-plate { display: block; font-size: 19px; font-weight: 800; letter-spacing: .5px; }
        .dm-driver { display: block; font-size: 13px; color: #64748b; }
        .dm-switch { margin: 0; padding: 0; min-height: 0; }
        /* !important: el tema achica los interruptores y los deja como un circulito */
        .dm-switch .form-check-input { width: 52px !important; height: 30px !important; margin: 0 !important; float: none; cursor: pointer;
            border-radius: 30px !important; background-size: contain !important; border: 2px solid #cbd5e1 !important; }
        .dm-switch .form-check-input:checked { background-color: #009BDC !important; border-color: #009BDC !important; }
        .dm-section { margin: 18px 4px 8px; font-size: 13px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .4px; }
        .dm-card--locked { cursor: default; background: #f8fafc; }
        .dm-card--locked .dm-plate { color: #64748b; }
        .dm-reason { display: block; font-size: 13px; font-weight: 600; margin-top: 2px; }
        .dm-mini { border-radius: 10px; font-size: 13px !important; font-weight: 600; white-space: nowrap; }
        .dm-bar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 1030; padding: 10px 12px calc(10px + env(safe-area-inset-bottom));
            background: rgba(255, 255, 255, .97); box-shadow: 0 -6px 20px rgba(15, 23, 42, .12); }
        .dm-bar .btn { width: 100%; max-width: 560px; display: block; margin: 0 auto; min-height: 54px; border-radius: 16px; font-size: 18px !important; font-weight: 800; }
        .dm-share { display: flex; gap: 10px; margin-bottom: 12px; }
        .dm-share .btn { flex: 1; min-height: 52px; border-radius: 14px; font-size: 16px !important; font-weight: 700;
            display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
        .dm-order { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 8px 12px; }
        .dm-num { flex: none; width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
            font-size: 21px; font-weight: 800; background: #e0f2fe; color: #0369a1; }
        .dm-order--top .dm-num { background: #ff4a12; color: #fff; }
        .dm-empty { text-align: center; color: #64748b; background: #fff; border: 1px dashed #cbd5e1; border-radius: 16px; padding: 28px 14px; }
        .dm-block { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 12px; }
        .dm-block + .dm-block { margin-top: 8px; }
        .dm-pick { display: flex; align-items: center; gap: 12px; padding: 10px 6px; border-bottom: 1px solid #f1f5f9; margin: 0; cursor: pointer; }
        .dm-pick .form-check-input { width: 24px !important; height: 24px !important; margin: 0 !important; flex: none; }
        .dm-big { min-height: 50px; border-radius: 14px; font-weight: 700; font-size: 16px !important; }
        #blocksModal .modal-body, #pickBlockModal .modal-body, #historyModal .modal-body { background: #f4f7fb; }
    </style>
@endpush

@push('scripts')
    <script>
        // Compartir el PDF del orden de salida como archivo (menú de compartir del celular: WhatsApp, etc.).
        // Si el celular no puede compartir archivos, lo abre.
        window.drawSharePdf = function (url, button) {
            if (button) { button.disabled = true; }
            fetch(url, { credentials: 'same-origin' })
                .then(function (r) {
                    if (!r.ok) { throw new Error('HTTP ' + r.status); }
                    var m = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(r.headers.get('content-disposition') || '');
                    return r.blob().then(function (blob) { return new File([blob], m ? decodeURIComponent(m[1]) : 'orden_salida.pdf', { type: 'application/pdf' }) });
                })
                .then(function (file) {
                    if (navigator.canShare && navigator.canShare({ files: [file] })) { return navigator.share({ files: [file], title: file.name }) }
                    window.open(url, '_blank');
                })
                .catch(function (err) { if (!err || err.name !== 'AbortError') { window.open(url, '_blank') } })
                .finally(function () { if (button) { button.disabled = false } });
        };
        // Cuántas placas participan: arranca con el número del servidor y se vuelve a contar cuando
        // Livewire termina de marcar los interruptores y después de cada respuesta suya
        document.addEventListener('livewire:init', function () {
            Livewire.hook('commit', function (payload) { payload.succeed(function () { setTimeout(function () { window.dispatchEvent(new Event('dm-recount')) }, 0) }) });
        });
        document.addEventListener('livewire:initialized', function () { setTimeout(function () { window.dispatchEvent(new Event('dm-recount')) }, 0) });
    </script>
@endpush

<div class="container-fluid">
<div class="dm" x-data="{
    ask(d) {
        Swal.fire({ title: d.title, html: d.html, icon: 'question', showCancelButton: true, confirmButtonColor: '#3085d6',
            cancelButtonColor: '#6c757d', confirmButtonText: 'Sí, sortear', cancelButtonText: 'Cancelar' })
            .then((r) => { if (r.isConfirmed) { $wire.draw(d.date) } })
    },
    done(d) {
        const html = document.createElement('div');
        html.innerHTML = '<p class=\'mb-3\'></p><div class=\'d-grid gap-2\'>'
            + '<button type=\'button\' class=\'btn btn-success btn-lg\'><i class=\'ti ti-share\'></i> Compartir PDF</button>'
            + '<a class=\'btn btn-outline-primary\' target=\'_blank\' rel=\'noopener\'><i class=\'ti ti-file-text\'></i> Ver PDF</a></div>';
        html.querySelector('p').textContent = d.text;
        if (d.warning) {
            const warn = document.createElement('div');
            warn.className = 'alert alert-warning text-start small py-2 mb-3';
            warn.textContent = d.warning;
            html.querySelector('p').after(warn);
        }
        html.querySelector('button').addEventListener('click', (e) => window.drawSharePdf(d.downloadUrl, e.currentTarget));
        html.querySelector('a').href = d.pdfUrl;
        Swal.fire({ title: d.title, html: html, icon: d.warning ? 'warning' : 'success', confirmButtonText: 'Cerrar', confirmButtonColor: '#6c757d' })
    },
    askReason(title, text, action) {
        const info = document.createElement('div');
        info.className = 'small text-muted mb-2';
        info.textContent = text;
        Swal.fire({ title: title, html: info, input: 'text', inputPlaceholder: 'Motivo (queda en el historial)',
            inputAttributes: { maxlength: 255 },
            inputValidator: (v) => (! v || v.trim().length < 3) ? 'Escribe un motivo (mínimo 3 letras).' : undefined,
            showCancelButton: true, confirmButtonColor: '#198754', cancelButtonColor: '#6c757d',
            confirmButtonText: 'Desbloquear', cancelButtonText: 'Cancelar' })
            .then((r) => { if (r.isConfirmed) { action(r.value.trim()) } })
    },
}" x-on:draw-confirm.window="ask($event.detail)" x-on:draw-done.window="done($event.detail)">

    {{-- Arriba: título y accesos --}}
    <div class="dm-top">
        <h1>Sorteos</h1>
        <button type="button" class="btn btn-outline-secondary" wire:click="openBlocks">
            <i class="ti ti-lock"></i> Bloqueos @if($blocks->isNotEmpty())<span class="badge bg-secondary">{{ $blocks->count() }}</span>@endif
        </button>
        @if($canSeeGrid)
            <a href="{{ route('draws.index', ['escritorio' => 1]) }}" class="btn btn-outline-secondary" title="Ver la grilla del mes"><i class="ti ti-table"></i></a>
        @endif
    </div>

    {{-- Días --}}
    <div class="dm-chips">
        @foreach($chips as $chip)
            <button type="button" wire:key="chip-{{ $chip['date'] }}" wire:click="selectDay('{{ $chip['date'] }}')"
                    @class(['dm-chip', 'is-'.$chip['state'], 'is-active' => $chip['date'] === $day])>
                <b>{{ $chip['label'] }}</b>
                <small>{{ ['done' => '✓ Sorteado', 'pending' => '● Pendiente', 'sunday' => 'Domingo'][$chip['state']] }}</small>
            </button>
        @endforeach
    </div>

    <div wire:loading.class="opacity-50" wire:target="selectDay,previousDrawnDay">
    @if(! $day)
        <div class="dm-empty">Todavía no hay sorteos.</div>

    @elseif($draw)
        {{-- ===== Día sorteado: orden de salida ===== --}}
        <div class="dm-head" wire:key="drawn-{{ $draw->id }}">
            <span class="dm-tag dm-tag--done">✓ Sorteado</span>
            <h2 class="mt-2">{{ $dayTitle }}</h2>
            <p>{{ $draw->participants }} participantes · {{ $draw->created_at->format('d/m H:i') }}
                por {{ \App\Models\DrawBlock::userLabel($draw->drawnBy) }} · #{{ $draw->code }}</p>
        </div>
        <div class="dm-share">
            <button type="button" class="btn btn-success" x-on:click="window.drawSharePdf(@js(route('draws.pdf', ['draw' => $draw, 'descargar' => 1])), $el)">
                <i class="ti ti-share"></i> Compartir PDF
            </button>
            <a class="btn btn-outline-primary" href="{{ route('draws.pdf', $draw) }}" target="_blank" rel="noopener"><i class="ti ti-file-text"></i> Ver PDF</a>
        </div>
        <div class="dm-list">
            @foreach($items as $item)
                <div @class(['dm-order', 'dm-order--top' => $item['number'] <= 3]) wire:key="order-{{ $draw->id }}-{{ $item['number'] }}">
                    <div class="dm-num">{{ $item['number'] }}</div>
                    <div class="dm-who"><span class="dm-plate">{{ $item['plate'] }}</span><span class="dm-driver">{{ $item['driver'] ?: '—' }}</span></div>
                </div>
            @endforeach
        </div>
        @if($hasPrevious)
            <button type="button" class="btn btn-light border w-100 mt-3" style="min-height: 48px; border-radius: 14px;" wire:click="previousDrawnDay">
                <i class="ti ti-chevron-left"></i> Ver el sorteo anterior
            </button>
        @endif

    @elseif($pending)
        {{-- ===== Día pendiente: elegir quién participa y sortear ===== --}}
        <div wire:key="pending-{{ $day }}" x-data="{
                q: '',
                count: {{ count($included[$day] ?? []) }},
                recount() { this.count = this.$refs.list ? this.$refs.list.querySelectorAll('input[data-part]:checked').length : 0 },
                setVisible(on) {
                    this.$refs.list.querySelectorAll('[data-card]').forEach((card) => {
                        if (card.style.display === 'none') { return }
                        const input = card.querySelector('input[data-part]');
                        if (input.checked !== on) { input.checked = on; input.dispatchEvent(new Event('change', { bubbles: true })) }
                    });
                    this.recount();
                },
            }" x-on:change="recount()" x-on:dm-recount.window="recount()">
            <div class="dm-head">
                <span @class(['dm-tag', 'dm-tag--sunday' => $isSunday, 'dm-tag--pending' => ! $isSunday])>{{ $isSunday ? 'Domingo · opcional' : '● Pendiente de sorteo' }}</span>
                <h2 class="mt-2">{{ $dayTitle }}</h2>
                <p>{{ $isSunday ? 'Prende solo las placas que trabajan este domingo: solo ellas entran al sorteo.' : 'Apaga las placas que no salen. Las prendidas entran al sorteo.' }}</p>
            </div>

            <div class="dm-tools">
                <input type="search" class="form-control" placeholder="Buscar" aria-label="Buscar placa o conductor" x-model="q">
                <button type="button" class="btn btn-outline-primary" x-on:click="setVisible(true)">Todas</button>
                <button type="button" class="btn btn-outline-secondary" x-on:click="setVisible(false)">Ninguna</button>
            </div>

            <div class="dm-list" x-ref="list">
                @forelse($vehicles as $vehicle)
                    @php $nt = \App\Models\Vehicle::notWorkingBadge($vehicle->not_working_since); @endphp
                    <label class="dm-card" data-card wire:key="card-{{ $day }}-{{ $vehicle->id }}"
                           data-search="{{ \Illuminate\Support\Str::lower($vehicle->plate.' '.$vehicle->sort_order.' '.$vehicle->driver?->name) }}"
                           x-show="! q || $el.dataset.search.includes(q.toLowerCase())">
                        <span class="dm-cod">{{ $vehicle->sort_order }}</span>
                        <span class="dm-who">
                            <span class="dm-plate">{{ $vehicle->plate }}</span>
                            <span class="dm-driver">
                                {{ \App\Services\DrawService::firstName($vehicle->driver?->name) ?: '—' }}
                                @if($vehicle->condition) · {{ $vehicle->condition }} @endif
                                @if($nt) <span class="badge {{ $nt['class'] }} {{ $nt['text'] }}">{{ $nt['abbr'] }}</span> @endif
                            </span>
                        </span>
                        <span class="form-check form-switch dm-switch">
                            <input class="form-check-input" type="checkbox" role="switch" data-part value="{{ $vehicle->id }}" wire:model="included.{{ $day }}"
                                   aria-label="Participa {{ $vehicle->plate }}">
                        </span>
                    </label>
                @empty
                    <div class="dm-empty">No hay placas que puedan entrar este día.</div>
                @endforelse
            </div>

            @if($blockedVehicles->isNotEmpty())
                <div class="dm-section"><i class="ti ti-lock"></i> No entran este día por bloqueo ({{ $blockedVehicles->count() }})</div>
                <div class="dm-list">
                    @foreach($blockedVehicles as $vehicle)
                        @php $block = $dayBlocks[$vehicle->id]; @endphp
                        <div class="dm-card dm-card--locked" wire:key="locked-{{ $day }}-{{ $vehicle->id }}">
                            <span class="dm-cod">{{ $vehicle->sort_order }}</span>
                            <span class="dm-who">
                                <span class="dm-plate"><i class="ti ti-lock"></i> {{ $vehicle->plate }}</span>
                                <span class="dm-driver">{{ $block->periodLabel() }} · {{ \App\Models\DrawBlock::userLabel($block->blockedBy) }}</span>
                                <span class="dm-reason" style="color: {{ \App\Models\DrawBlock::KIND_COLORS[$block->kind] ?? 'inherit' }};">{{ $block->reason }}</span>
                            </span>
                            @if($canBlock)
                                <button type="button" class="btn btn-sm btn-outline-success dm-mini"
                                        x-on:click="askReason('Desbloquear {{ $vehicle->plate }}', 'Solo el {{ \Illuminate\Support\Str::lower($dayTitle) }}: ese día entra al sorteo.', (reason) => $wire.unblockDays(['{{ $vehicle->id }}|{{ $day }}'], reason))">
                                    <i class="ti ti-lock-open"></i> Este día
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="dm-bar">
                <button type="button" class="btn btn-warning" wire:click="confirmDraw('{{ $day }}')" :disabled="! count"
                        wire:loading.attr="disabled" wire:target="confirmDraw,draw">
                    <i class="ti ti-arrows-shuffle"></i> Sortear {{ $dayShort }} (<span x-text="count">0</span>)
                </button>
            </div>
        </div>
    @endif
    </div>

    {{-- Bloqueos vigentes (pantalla completa en el celular) --}}
    <div class="modal fade" id="blocksModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ti ti-lock"></i> Placas bloqueadas ({{ $blocks->count() }})</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    @if($canBlock)
                        <button type="button" class="btn btn-dark w-100 mb-3 dm-big"
                                x-on:click="hideModal('blocksModal'); setTimeout(() => bootstrap.Modal.getOrCreateInstance(document.getElementById('pickBlockModal')).show(), 350)">
                            <i class="ti ti-lock"></i> Bloquear placas
                        </button>
                    @endif
                    @include('draws._kind_legend')
                    @forelse($blocks as $block)
                        <div class="dm-block" wire:key="mblock-{{ $block->id }}">
                            <div class="d-flex align-items-start gap-2">
                                <div class="flex-grow-1">
                                    <div class="dm-plate">{{ $block->vehicle?->plate ?? '—' }}
                                        <small class="text-muted fw-normal" style="font-size: 13px;">{{ \App\Services\DrawService::firstName($block->vehicle?->driver?->name) }}</small></div>
                                    <div class="small text-muted">{{ \App\Models\DrawBlock::KIND_LABELS[$block->kind] ?? $block->kind }} · {{ $block->periodLabel() }}</div>
                                    <div class="dm-reason" style="color: {{ \App\Models\DrawBlock::KIND_COLORS[$block->kind] ?? 'inherit' }};">{{ $block->reason }}</div>
                                    <div class="small text-muted">{{ \App\Models\DrawBlock::userLabel($block->blockedBy) }} · {{ $block->created_at->format('d/m/Y H:i') }}</div>
                                    @foreach($block->exceptions as $exception)
                                        @include('draws._unblocked_day', ['exception' => $exception])
                                    @endforeach
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary dm-mini flex-fill" style="min-height: 40px;"
                                        x-on:click="hideModal('blocksModal'); $wire.openHistory({{ $block->vehicle_id }})"><i class="ti ti-history"></i> Historial</button>
                                @if($canBlock)
                                    <button type="button" class="btn btn-sm btn-outline-success dm-mini flex-fill" style="min-height: 40px;"
                                            x-on:click="hideModal('blocksModal'); askReason('Desbloquear ' + @js($block->vehicle?->plate ?? ''), @js($block->periodLabel().' · vuelve a entrar desde el próximo sorteo.'), (reason) => $wire.unblockVehicle({{ $block->id }}, reason))">
                                        <i class="ti ti-lock-open"></i> Desbloquear
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="dm-empty">No hay placas bloqueadas.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Elegir placas para bloquear; después sigue el formulario de bloqueo de siempre --}}
    @if($canBlock)
        <div class="modal fade" id="pickBlockModal" tabindex="-1" aria-hidden="true" wire:ignore.self
             x-data="{
                 picked: [],
                 q: '',
                 next() {
                     const ids = [...this.picked];
                     const plates = ids.map((id) => this.$el.querySelector(`[data-pick='${id}']`)?.dataset.plate).filter(Boolean).join(', ');
                     hideModal('pickBlockModal');
                     $wire.blockFormOptions().then((opts) => window.drawsBlockForm(ids.length, plates, opts,
                         (form) => $wire.blockVehicles(ids, form).then((ok) => { if (ok) { this.picked = [] } return ok })));
                 },
             }">
            <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="ti ti-lock"></i> ¿Qué placas bloqueas?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="search" class="form-control mb-2" style="font-size: 16px; min-height: 44px; border-radius: 12px;" placeholder="Buscar placa o conductor" x-model="q">
                        <div class="bg-white rounded-3 border">
                            @foreach($blockable as $vehicle)
                                <label class="dm-pick" data-pick="{{ $vehicle->id }}" data-plate="{{ $vehicle->plate }}" wire:key="pick-{{ $vehicle->id }}"
                                       data-search="{{ \Illuminate\Support\Str::lower($vehicle->plate.' '.$vehicle->sort_order.' '.$vehicle->driver?->name) }}"
                                       x-show="! q || $el.dataset.search.includes(q.toLowerCase())">
                                    <input type="checkbox" class="form-check-input" value="{{ $vehicle->id }}" x-model.number="picked">
                                    <span class="text-muted small" style="width: 30px;">{{ $vehicle->sort_order }}</span>
                                    <span class="dm-who"><span class="dm-plate" style="font-size: 17px;">{{ $vehicle->plate }}</span>
                                        <span class="dm-driver">{{ \App\Services\DrawService::firstName($vehicle->driver?->name) }}</span></span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-dark w-100 dm-big" :disabled="! picked.length" x-on:click="next()">
                            Siguiente (<span x-text="picked.length">0</span>) <i class="ti ti-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Historial de sanciones de una placa --}}
    <div class="modal fade" id="historyModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-scrollable modal-lg modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ti ti-history"></i> Historial @if($historyVehicle)· <strong>{{ $historyVehicle->plate }}</strong>@endif</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    @if($historyVehicle)
                        @include('draws._sanctions', ['history' => $history, 'vehicle' => $historyVehicle])
                    @endif
                </div>
            </div>
        </div>
    </div>

    @include('draws._block-form')
</div>
</div>
