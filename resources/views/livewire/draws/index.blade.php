@push('styles')
    <style>
        .draws-module .dr-table { font-size: 13px; }
        .draws-module .dr-table th, .draws-module .dr-table td { padding: 4px 6px; vertical-align: middle; white-space: nowrap; word-break: normal; overflow-wrap: normal; }
        .draws-module .dr-sticky { position: sticky; left: 0; z-index: 2; background: #fff; }
        .draws-module .dr-table thead tr, .draws-module .dr-table tfoot tr,
        .draws-module thead .dr-sticky, .draws-module tfoot .dr-sticky { background-color: inherit; }
        .draws-module .dr-day { min-width: 46px; text-align: center; line-height: 1.15; }
        .draws-module .dr-day-open { background: #fff7d6 !important; color: #1b2430 !important; }
        .draws-module .dr-day-meta { font-size: 11px; opacity: .9; }
        .draws-module .dr-open { background: #fffbeb; }
        .draws-module .dr-low { background: #dcfce7; color: #166534; font-weight: 600; }
        .draws-module .dr-mid { background: #fef3c7; color: #92400e; font-weight: 600; }
        .draws-module .dr-high { background: #fee2e2; color: #991b1b; font-weight: 600; }
        .draws-module .dr-repeat { outline: 2px solid #dc2626; outline-offset: -2px; }
        .draws-module .dr-blocked-sep td { background: #f1f5f9 !important; color: #475569; font-weight: 600; font-size: 12px; letter-spacing: .02em; }
        .draws-module .dr-blocked td { background: #f8fafc; color: #64748b; }
        .draws-module .dr-blocked td.dr-sticky { background: #f8fafc; }
        .draws-module .dr-lock { cursor: pointer; }
        .draws-module .dr-sel { width: 30px; text-align: center; background: #eef4ff !important; }
        .draws-module thead .dr-sel { background: #1e3a8a !important; color: #fff; }
        .draws-module .dr-selbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 8px 12px; margin-bottom: 8px;
            border: 1px solid #c7d7fe; background: #eef4ff; border-radius: 10px; font-size: 13px; }
        [x-cloak] { display: none !important; }
        .draws-module .dr-swatch { display: inline-block; width: 14px; height: 14px; border-radius: 3px; vertical-align: -2px; margin-right: 4px; }
        .draws-module .dr-pending .btn { border-radius: 10px; }
        .draws-module .dr-day-done { position: relative; cursor: pointer; }
        .draws-module .dr-day-done:hover { filter: brightness(1.12); }
        .draws-module .dr-day-done .dr-annul { position: relative; z-index: 2; }

    </style>
@endpush

<div class="container-fluid draws-module" x-data="{
    selecting: false,
    toBlock: [],
    toUnblock: [],
    stopSelecting() { this.selecting = false; this.toBlock = []; this.toUnblock = [] },
    platesOf(ids) {
        return ids.map(id => document.querySelector(`input[data-sel][value='${id}']`)?.dataset.plate).filter(Boolean).join(', ')
    },
    toggleAll(checked) {
        this.toBlock = checked ? [...document.querySelectorAll('input[data-sel=block]')].map(c => c.value) : []
    },
    blockSelected() {
        if (! this.toBlock.length) { return }
        Swal.fire({
            title: 'Bloquear ' + this.toBlock.length + ' placa(s)',
            text: this.platesOf(this.toBlock),
            input: 'text',
            inputLabel: 'Motivo (queda registrado)',
            inputPlaceholder: 'Ej. En taller',
            inputAttributes: { maxlength: 255 },
            showCancelButton: true,
            confirmButtonColor: '#212529',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Bloquear',
            cancelButtonText: 'Cancelar',
            inputValidator: (value) => (! value || value.trim().length < 3) ? 'Escribe un motivo (mínimo 3 letras).' : undefined,
        }).then((result) => {
            if (result.isConfirmed) { $wire.blockVehicles(this.toBlock, result.value).then(() => this.stopSelecting()) }
        })
    },
    unblockSelected() {
        if (! this.toUnblock.length) { return }
        this.confirmThen('¿Desbloquear ' + this.toUnblock.length + ' placa(s)?', this.platesOf(this.toUnblock) + ' vuelven a entrar desde el próximo sorteo.',
            () => $wire.unblockVehicles(this.toUnblock).then(() => this.stopSelecting()), 'Sí, desbloquear')
    },
    ask(d) {
        Swal.fire({
            title: d.title,
            html: d.html,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, sortear',
            cancelButtonText: 'Cancelar',
        }).then((result) => { if (result.isConfirmed) { $wire.draw(d.date) } })
    },
    done(d) {
        const html = document.createElement('div');
        html.innerHTML = '<p class=\'mb-3\'></p><div class=\'d-flex flex-wrap justify-content-center gap-2\'>'
            + '<a class=\'btn btn-primary\' target=\'_blank\' rel=\'noopener\'><i class=\'ti ti-file-text\'></i> Ver PDF</a>'
            + '<a class=\'btn btn-success\'><i class=\'ti ti-download\'></i> Descargar PDF</a></div>';
        html.querySelector('p').textContent = d.text;
        if (d.warning) {
            const warn = document.createElement('div');
            warn.className = 'alert alert-warning text-start small py-2 mb-3';
            warn.textContent = d.warning;
            html.querySelector('p').after(warn);
        }
        const links = html.querySelectorAll('a');
        links[0].href = d.pdfUrl;
        links[1].href = d.downloadUrl;
        Swal.fire({ title: d.title, html: html, icon: d.warning ? 'warning' : 'success', confirmButtonText: 'Cerrar', confirmButtonColor: '#6c757d' })
    },
    confirmThen(title, text, action, confirmText = 'Sí, quitar') {
        Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: confirmText,
            cancelButtonText: 'Cancelar',
        }).then((result) => { if (result.isConfirmed) { action() } })
    }
}" x-on:draw-confirm.window="ask($event.detail)" x-on:draw-done.window="done($event.detail)">

    {{-- Header --}}
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">{{ $simulating ? 'SIMULACIÓN DE SORTEOS' : 'SORTEOS' }} DE {{ \Illuminate\Support\Str::upper($monthName) }} DEL {{ $year }}</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-arrows-shuffle f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Sorteos</span></a>
                </li>
                <li class="d-flex active"><a href="#" class="f-s-14">Sorteo diario</a></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">

                    {{-- Filtros y pendientes --}}
                    <div class="d-flex flex-wrap align-items-end gap-2 mb-2">
                        <button class="btn btn-sm btn-light align-self-end" wire:click="prevMonth" title="Mes anterior"><i class="ti ti-chevron-left"></i></button>
                        <div>
                            <label class="form-label mb-1">Mes</label>
                            <select class="form-select form-select-sm" wire:model.live="month">
                                @foreach(\App\Livewire\Draws\Index::MONTHS as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div style="min-width: 100px;">
                            <label class="form-label mb-1">Año</label>
                            <select class="form-select form-select-sm" wire:model.live="year">
                                @foreach($years as $y)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="btn btn-sm btn-light align-self-end" wire:click="nextMonth" title="Mes siguiente"><i class="ti ti-chevron-right"></i></button>

                        <div class="dr-pending d-flex flex-wrap align-items-center gap-2 align-self-end ms-md-3">
                            @if($simulating)
                                <span class="small text-muted"><i class="ti ti-flask"></i> Viendo una simulación: los sorteos reales no se muestran.</span>
                            @elseif(count($drawable))
                                <span class="small text-muted"><i class="ti ti-clock"></i> Pendiente de sorteo:</span>
                                @foreach($drawableLabels as $date => $label)
                                    <button class="btn btn-sm btn-warning" wire:click="goToMonth('{{ $date }}')" title="Ir a ese mes">{{ $label }}</button>
                                @endforeach
                            @else
                                <span class="small text-success"><i class="ti ti-circle-check"></i> No hay días pendientes de sortear.</span>
                            @endif
                        </div>

                        <div class="d-flex gap-2 align-self-end ms-auto">
                            @unless($simulating)
                                <button class="btn btn-sm btn-outline-primary" wire:click="openSimulation" title="Llenar el mes con sorteos de prueba, sin guardar nada">
                                    <i class="ti ti-flask"></i> Simular
                                </button>
                            @endunless
                            @if($canBlock && ! $simulating)
                                <button class="btn btn-sm" :class="selecting ? 'btn-primary' : 'btn-outline-primary'" x-on:click="selecting ? stopSelecting() : selecting = true"
                                        title="Marcar varias placas en la tabla para bloquearlas o desbloquearlas">
                                    <i class="ti ti-checkbox"></i> <span x-text="selecting ? 'Terminar selección' : 'Seleccionar varios'">Seleccionar varios</span>
                                </button>
                            @endif
                            <button class="btn btn-sm btn-outline-secondary" wire:click="openBlocks" title="Placas que no entran al sorteo hasta desbloquearlas">
                                <i class="ti ti-lock"></i> Bloqueos
                                @if($blocks->isNotEmpty())
                                    <span class="badge bg-secondary ms-1">{{ $blocks->count() }}</span>
                                @endif
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" wire:click="openHolidays">
                                <i class="ti ti-calendar-off"></i> Feriados
                            </button>
                        </div>
                    </div>

                    @if($simulating)
                        <div class="dr-sim alert alert-warning d-flex flex-wrap align-items-center gap-2 py-2 mb-2">
                            <div class="flex-grow-1">
                                <b><i class="ti ti-flask"></i> SIMULACIÓN — no se guarda nada.</b>
                                Cada día participan al azar entre {{ $simMin }} y {{ $simMax }} vehículos; al resto se le quitó el check.
                                <div class="small mt-1">
                                    {{ $simSummary['days'] }} días ·
                                    {{ $simSummary['minParticipants'] }}–{{ $simSummary['maxParticipants'] }} participantes por día ·
                                    {{ number_format($simSummary['numbers']) }} números ·
                                    <b>{{ $simSummary['repeats'] }}</b> repeticiones de franja ·
                                    <b>{{ $simSummary['repeatedNumbers'] }}</b> números repetidos en el mes ·
                                    {{ $simSummary['deviations'] }} desvíos ·
                                    B/M/A parejo (diferencia de 1 o menos) en {{ $simSummary['even'] }} de {{ $simSummary['vehicles'] }} vehículos, diferencia máxima {{ $simSummary['maxGap'] }}
                                </div>
                            </div>
                            <a class="btn btn-sm btn-export" href="{{ $simExportUrl }}"><i class="ti ti-file-spreadsheet"></i> Exportar Excel</a>
                            <button class="btn btn-sm btn-warning" wire:click="resimulate" wire:loading.attr="disabled" wire:target="resimulate"><i class="ti ti-refresh"></i> Otra simulación</button>
                            <button class="btn btn-sm btn-dark" wire:click="exitSimulation"><i class="ti ti-x"></i> Salir de la simulación</button>
                        </div>
                    @endif

                    @if($canBlock && ! $simulating)
                        <div class="dr-selbar" x-show="selecting" x-cloak>
                            <span><i class="ti ti-checkbox"></i> Marca las placas en la columna azul de la tabla.</span>
                            <button type="button" class="btn btn-sm btn-dark" :disabled="! toBlock.length" x-on:click="blockSelected()">
                                <i class="ti ti-lock"></i> Bloquear (<span x-text="toBlock.length">0</span>)
                            </button>
                            <button type="button" class="btn btn-sm btn-success" x-show="toUnblock.length" x-on:click="unblockSelected()">
                                <i class="ti ti-lock-open"></i> Desbloquear (<span x-text="toUnblock.length">0</span>)
                            </button>
                            <button type="button" class="btn btn-sm btn-light ms-auto" x-on:click="stopSelecting()">Cancelar</button>
                        </div>
                    @endif

                    <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;max-width:100%">
                        <table class="table table-bordered table-hover dr-table mb-2">
                            <thead class="bg-primary">
                            <tr>
                                <th class="dr-sel" x-show="selecting" x-cloak title="Marcar todas las que entran al sorteo">
                                    <input type="checkbox" class="form-check-input m-0" x-on:change="toggleAll($event.target.checked)">
                                </th>
                                <th>Nº</th>
                                <th>COD</th>
                                <th class="dr-sticky">PLACA</th>
                                <th>COND.</th>
                                @forelse($days as $day)
                                    @php
                                        $result = $results[$day['date']] ?? null;
                                        $isOpen = ! $result && in_array($day['date'], $drawable, true);
                                    @endphp
                                    <th class="dr-day {{ $isOpen ? 'dr-day-open' : '' }} {{ $result ? 'dr-day-done' : '' }}" wire:key="head-{{ $day['date'] }}"
                                        @if($result) title="{{ $result['title'] }} · clic para abrir el PDF del orden de salida" @endif>
                                        <div class="small">{{ $day['weekday'] }}</div>
                                        <div class="fw-bold">{{ $day['n'] }}</div>
                                        @if($result)
                                            <div class="dr-day-meta">
                                                <a href="{{ $result['url'] }}" target="_blank" rel="noopener" class="stretched-link text-white text-decoration-none" aria-label="Abrir el PDF del orden de salida del {{ $day['weekday'] }} {{ $day['n'] }}"><i class="ti ti-list-numbers"></i>{{ $result['participants'] }}</a>
                                                @if($isDirector && $result['drawId'] && $result['drawId'] === $lastActiveDrawId)
                                                    <button type="button" class="btn btn-link btn-sm p-0 text-white dr-annul" wire:click.stop="openAnnul({{ $result['drawId'] }})" title="Anular este sorteo">
                                                        <i class="ti ti-ban"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        @elseif($isOpen)
                                            <button type="button" class="btn btn-sm btn-primary mt-1 px-2 py-0" wire:click="confirmDraw('{{ $day['date'] }}')"
                                                    wire:loading.attr="disabled" wire:target="confirmDraw,draw">
                                                <i class="ti ti-arrows-shuffle"></i> Sortear
                                            </button>
                                        @endif
                                    </th>
                                @empty
                                    <th>No hay días hábiles en este mes.</th>
                                @endforelse
                                <th class="text-center" title="Veces con número bajo en el mes">B</th>
                                <th class="text-center" title="Veces con número medio en el mes">M</th>
                                <th class="text-center" title="Veces con número alto en el mes">A</th>
                            </tr>
                            </thead>

                            <tbody>
                            @forelse($rows as $i => $row)
                                @if($row['block'] && ! ($rows[$i - 1]['block'] ?? null))
                                    <tr class="dr-blocked-sep" wire:key="blocked-sep">
                                        <td colspan="{{ count($days) + 8 }}"><i class="ti ti-lock"></i> BLOQUEADAS ({{ $blocks->count() }}): no entran al sorteo ni a la simulación hasta desbloquearlas</td>
                                    </tr>
                                @endif
                                <tr wire:key="row-{{ $row['id'] }}" @class(['dr-blocked' => $row['block']])>
                                    <td class="dr-sel" x-show="selecting" x-cloak>
                                        @if($row['block'])
                                            <input type="checkbox" class="form-check-input m-0" data-sel="unblock" data-plate="{{ $row['plate'] }}" value="{{ $row['id'] }}" x-model="toUnblock" title="Marcar para desbloquear">
                                        @elseif($row['active'])
                                            <input type="checkbox" class="form-check-input m-0" data-sel="block" data-plate="{{ $row['plate'] }}" value="{{ $row['id'] }}" x-model="toBlock" title="Marcar para bloquear">
                                        @endif
                                    </td>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $row['cod'] }}</td>
                                    <td class="dr-sticky">
                                        @if($row['block'])
                                            <strong>{{ $row['plate'] }}</strong>
                                            <span class="badge bg-secondary dr-lock" wire:click="openBlocks"
                                                  title="Bloqueada desde el {{ $row['block']->created_at->format('d/m/Y') }} por {{ $row['block']->blockedBy?->name ?? '—' }}: {{ $row['block']->reason }}"><i class="ti ti-lock"></i></span>
                                        @elseif($row['active'])
                                            <strong>{{ $row['plate'] }}</strong>
                                        @else
                                            <strong class="text-danger" data-bs-toggle="tooltip" title="Ya no entra al sorteo (inactivo o NT en rojo)">{{ $row['plate'] }}</strong>
                                        @endif
                                        @if($row['nt'])
                                            <span class="badge {{ $row['nt']['class'] }} {{ $row['nt']['text'] }}" title="{{ $row['nt']['title'] }}">{{ $row['nt']['abbr'] }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $row['condition'] }}</td>

                                    @foreach($days as $day)
                                        @php
                                            $date = $day['date'];
                                            $cell = $cells[$date][$row['id']] ?? null;
                                        @endphp
                                        @if(isset($results[$date]))
                                            @if(! $cell)
                                                <td></td>
                                            @elseif($cell->number === null)
                                                <td class="text-center text-muted" title="No participó">—</td>
                                            @else
                                                @php $isRepeated = in_array($cell->number, $row['repeatedNumbers'], true); @endphp
                                                <td class="text-center dr-{{ $cell->band }} {{ $isRepeated ? 'dr-repeat' : '' }}"
                                                    title="{{ $bandLabels[$cell->band] }}@if($cell->deviated) · no siguió el ciclo: venía de {{ \Illuminate\Support\Str::lower($bandLabels[$cell->previous_band]) }} y le tocaba {{ \Illuminate\Support\Str::lower($bandLabels[\App\Models\DrawEntry::nextBand($cell->previous_band)]) }}@endif @if($isRepeated) · número repetido en el mes @endif">
                                                    {{ $cell->number }}@if($cell->deviated)<sup>*</sup>@endif
                                                </td>
                                            @endif
                                        @elseif($row['active'] && in_array($date, $drawable, true))
                                            <td class="text-center dr-open">
                                                <input type="checkbox" class="form-check-input m-0" wire:model="included.{{ $date }}" value="{{ $row['id'] }}"
                                                       title="Desmarca para que no participe el {{ $drawableLabels[$date] }}">
                                            </td>
                                        @else
                                            <td></td>
                                        @endif
                                    @endforeach

                                    <td class="text-center dr-low">{{ $row['counts']['low'] }}</td>
                                    <td class="text-center dr-mid">{{ $row['counts']['mid'] }}</td>
                                    <td class="text-center dr-high">{{ $row['counts']['high'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($days) + 8 }}" class="text-center text-muted">No hay vehículos activos.</td></tr>
                            @endforelse
                            </tbody>

                            <tfoot class="bg-primary">
                            <tr>
                                <td x-show="selecting" x-cloak></td>
                                <td colspan="2"></td>
                                <td class="dr-sticky">PARTICIPAN</td>
                                <td></td>
                                @foreach($days as $day)
                                    <td class="text-center">{{ $results[$day['date']]['participants'] ?? '' }}</td>
                                @endforeach
                                <td colspan="3"></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- Leyenda --}}
                    <div class="small text-muted">
                        <div class="d-flex flex-wrap gap-3 mb-1">
                            <span><span class="dr-swatch dr-low"></span>Bajo: primer tercio de los números del día</span>
                            <span><span class="dr-swatch dr-mid"></span>Medio: segundo tercio</span>
                            <span><span class="dr-swatch dr-high"></span>Alto: último tercio</span>
                            <span><b>*</b> no pudo seguir el ciclo ese día</span>
                            <span><b>—</b> no participó</span>
                            <span><i class="ti ti-lock"></i> placa bloqueada: no entra hasta desbloquearla</span>
                            <span><span class="dr-swatch" style="outline: 2px solid #dc2626; outline-offset: -2px;"></span>número repetido en el mes</span>
                        </div>
                        <div>Cada vehículo rota bajo → medio → alto → bajo, nunca repite franja dos sorteos seguidos ni un número dentro del mes. Solo días de lunes a sábado sin feriados. Los NT en rojo ({{ \App\Models\Vehicle::NOT_WORKING_DROP_DAYS }}+ días sin trabajar) no entran al sorteo.</div>
                        @if($monthHolidays->isNotEmpty())
                            <div>Feriados del mes:
                                {{ $monthHolidays->map(fn ($h) => $h->date->format('d/m').' '.$h->description)->implode(' · ') }}
                            </div>
                        @endif
                    </div>

                    {{-- Anulados --}}
                    @if($annulledDraws->isNotEmpty())
                        <div class="mt-3">
                            <h6 class="mb-2"><i class="ti ti-ban text-danger"></i> Sorteos anulados en el mes</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0 small">
                                    <thead>
                                    <tr><th>Día</th><th>Sorteado por</th><th>Anulado por</th><th>Cuándo</th><th>Motivo</th><th></th></tr>
                                    </thead>
                                    <tbody>
                                    @foreach($annulledDraws as $annulled)
                                        <tr wire:key="annulled-{{ $annulled->id }}">
                                            <td>{{ $annulled->date->format('d/m/Y') }}</td>
                                            <td>{{ $annulled->drawnBy?->name ?? '—' }} <span class="text-muted">{{ $annulled->created_at->format('d/m H:i') }}</span></td>
                                            <td>{{ $annulled->annulledBy?->name ?? '—' }}</td>
                                            <td>{{ $annulled->annulled_at->format('d/m/Y H:i') }}</td>
                                            <td style="white-space: normal;">{{ $annulled->annul_reason }}</td>
                                            <td><a href="{{ route('draws.pdf', $annulled) }}" target="_blank" rel="noopener" title="PDF del sorteo anulado"><i class="ti ti-file-text"></i></a></td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>

    {{-- Modal: anular sorteo --}}
    <div class="modal fade" id="annulDrawModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" wire:submit.prevent="annul">
                <div class="modal-header">
                    <h5 class="modal-title">Anular sorteo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    @if($annulTarget)
                        <p class="mb-2">Se anulará el sorteo del <b>{{ $annulTarget->date->format('d/m/Y') }}</b> ({{ $annulTarget->participants }} vehículos). Queda en el historial y el día vuelve a quedar libre.</p>
                    @endif
                    <label class="form-label" for="annulReason">Motivo</label>
                    <textarea id="annulReason" rows="3" class="form-control @error('annulReason') is-invalid @enderror" wire:model="annulReason" maxlength="255"></textarea>
                    @error('annulReason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger" wire:loading.attr="disabled" wire:target="annul"><i class="ti ti-ban"></i> Anular</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: simular --}}
    <div class="modal fade" id="simulateModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" wire:submit.prevent="simulate">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ti ti-flask"></i> Simular {{ $monthName }} {{ $year }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Llena todos los días hábiles del mes con el mismo algoritmo de los sorteos reales. Cada día participan al azar entre el mínimo y el máximo de vehículos; al resto se le quita el check. <b>No se guarda nada.</b></p>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label mb-1" for="simMin">Mínimo por día</label>
                            <input type="number" id="simMin" min="1" class="form-control @error('simMin') is-invalid @enderror" wire:model="simMin">
                            @error('simMin') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label mb-1" for="simMax">Máximo por día</label>
                            <input type="number" id="simMax" min="1" class="form-control @error('simMax') is-invalid @enderror" wire:model="simMax">
                            @error('simMax') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="simulate"><i class="ti ti-flask"></i> Simular</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: placas bloqueadas --}}
    <div class="modal fade" id="blocksModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ti ti-lock"></i> Placas bloqueadas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Una placa bloqueada no entra al sorteo ni a la simulación hasta que se desbloquee, y en la grilla va abajo de todo. Queda registrado quién la bloqueó, quién la desbloqueó y cuándo.</p>

                    <div class="alert alert-light border small py-2 mb-3">
                        @if($canBlock)
                            <i class="ti ti-info-circle"></i> Para bloquear, usa <b>Seleccionar varios</b> en la tabla, marca las placas y pulsa <b>Bloquear</b>.
                        @else
                            Solo el director o el gerente pueden bloquear y desbloquear placas.
                        @endif
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0 small">
                            <thead>
                            <tr><th>Placa</th><th>Conductor</th><th>Motivo</th><th>Bloqueada por</th><th>Desde</th><th></th></tr>
                            </thead>
                            <tbody>
                            @forelse($blocks as $block)
                                <tr wire:key="block-{{ $block->id }}">
                                    <td><strong>{{ $block->vehicle?->plate ?? '—' }}</strong></td>
                                    <td>{{ $block->vehicle?->driver?->name ?? '—' }}</td>
                                    <td style="white-space: normal;">{{ $block->reason }}</td>
                                    <td>{{ $block->blockedBy?->name ?? '—' }}</td>
                                    <td>{{ $block->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="text-end">
                                        @if($canBlock)
                                            <button type="button" class="btn btn-sm btn-outline-success py-0"
                                                    x-on:click="confirmThen('¿Desbloquear la placa?', @js(($block->vehicle?->plate ?? '').' vuelve a entrar desde el próximo sorteo.'), () => $wire.unblockVehicle({{ $block->id }}), 'Sí, desbloquear')">
                                                <i class="ti ti-lock-open"></i> Desbloquear
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">No hay placas bloqueadas.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: feriados --}}
    <div class="modal fade" id="holidaysModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Feriados {{ $year }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form class="row g-2 align-items-start mb-3" wire:submit.prevent="addHoliday">
                        <div class="col-sm-4">
                            <label class="form-label mb-1" for="holidayDate">Fecha</label>
                            <input type="date" id="holidayDate" class="form-control form-control-sm @error('holidayDate') is-invalid @enderror" wire:model="holidayDate">
                            @error('holidayDate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label mb-1" for="holidayDescription">Descripción</label>
                            <input type="text" id="holidayDescription" class="form-control form-control-sm @error('holidayDescription') is-invalid @enderror" wire:model="holidayDescription" maxlength="100" placeholder="Ej. Día no laborable">
                            @error('holidayDescription') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-3 d-grid">
                            <label class="form-label mb-1 d-none d-sm-block">&nbsp;</label>
                            <button type="submit" class="btn btn-sm btn-primary" wire:loading.attr="disabled" wire:target="addHoliday"><i class="ti ti-plus"></i> Agregar</button>
                        </div>
                    </form>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small text-muted">{{ $yearHolidays->count() }} feriado(s) en {{ $year }}</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="loadNationalHolidays" wire:loading.attr="disabled" wire:target="loadNationalHolidays">
                            <i class="ti ti-download"></i> Cargar feriados nacionales {{ $year }}
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead>
                            <tr><th>Fecha</th><th>Día</th><th>Descripción</th><th></th></tr>
                            </thead>
                            <tbody>
                            @forelse($yearHolidays as $holiday)
                                <tr wire:key="holiday-{{ $holiday->id }}">
                                    <td>{{ $holiday->date->format('d/m/Y') }}</td>
                                    <td>{{ \App\Services\DrawService::weekdayLabel($holiday->date) }}</td>
                                    <td>{{ $holiday->description }}</td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-link text-danger p-0" title="Quitar feriado"
                                                x-on:click="confirmThen('¿Quitar el feriado?', @js($holiday->date->format('d/m/Y').' '.$holiday->description), () => $wire.deleteHoliday({{ $holiday->id }}))">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">No hay feriados cargados para {{ $year }}.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
