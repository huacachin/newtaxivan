<div class="container-fluid">
    {{-- Header / migas --}}
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">DEUDA</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Caja</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Deuda mensual</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">



        {{-- Tabla (estilo Payments) --}}
        <div class="col-xl-12">
            <div class="card">

                <div class="card-body">
                    <div class="row my-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-2 overflow-auto py-1">

                                <!-- Buscar placa -->
                                <div class="flex-shrink-0" >
                                    <label class="form-label mb-1">Buscar placa</label>
                                    <input type="search"
                                           class="form-control form-control-sm"
                                           placeholder="ABC123"
                                           wire:model="search">
                                </div>



                                <!-- Mes -->
                                <div class="flex-shrink-0" >
                                    <label class="form-label mb-1">Mes</label>
                                    <select class="form-select form-select-sm" wire:model="month">
                                        @foreach($months as $val => $label)
                                            <option value="{{ $val }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Año -->
                                <div class="flex-shrink-0">
                                    <label class="form-label mb-1">Año</label>
                                    <select class="form-select form-select-sm" wire:model="year">
                                        @foreach($years as $y)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endforeach
                                    </select>
                                </div>



                                <!-- Condición -->
                                <div class="flex-shrink-0">
                                    <label class="form-label mb-1">Condición</label>
                                    <select class="form-select form-select-sm" wire:model="condition">
                                        <option value="">Todas</option>
                                        <option value="DT">DT</option>
                                        <option value="GN">GN</option>
                                        <option value="EX">EX</option>
                                        <option value="EX5">EX5</option>
                                        <option value="Exonerado">Exonerado</option>
                                        <option value="Amortizado">Amortizado</option>
                                    </select>
                                </div>

                                <button class="btn btn-sm btn-dark flex-shrink-0 align-self-end" wire:click="$refresh">
                                    <i class="ti ti-search f-s-12"></i>
                                </button>

                                <!-- Exportar -->
                                <button class="btn btn-sm btn-export flex-shrink-0 align-self-end"
                                        wire:click="export">
                                    <i class="ti ti-file-analytics f-s-12"></i>
                                </button>

                                <!-- Ir al final -->
                                <button id="down"
                                        class="btn btn-sm btn-primary flex-shrink-0 align-self-end" >
                                    <i class="fa-solid fa-angle-down"></i>
                                </button>

                            </div>
                        </div>
                    </div>
                    {{-- ════════ MOBILE: cards de deuda mensual ════════ --}}
                    <div class="d-md-none list-cards">
                        @forelse($rows as $r)
                            @php
                                $pending = (float)($r['pending'] ?? 0);
                                $hasPending = $pending > 0;
                                $cardClass = $hasPending ? 'list-card list-card--danger' : 'list-card';
                            @endphp
                            <article class="{{ $cardClass }}" wire:key="card-{{ $r['item'] }}">
                                <header class="list-card__head">
                                    <div class="list-card__title-wrap">
                                        <span class="list-card__index">{{ $loop->iteration }}</span>
                                        <span class="list-card__title list-card__title--plate">{{ $r['plate'] }}</span>
                                        @if($r['condition'])
                                            <span class="list-chip">{{ $r['condition'] }}</span>
                                        @endif
                                    </div>
                                    @hasanyrole('director|gerente|administrador')
                                        @if(($r['total'] ?? 0) > 0)
                                            <a href="#" class="list-card__edit" wire:click.prevent="detail({{ $r['id'] }})" aria-label="Editar">
                                                <i class="ti ti-edit"></i>
                                            </a>
                                        @endif
                                    @endhasanyrole
                                </header>

                                <div class="list-card__subtitle">
                                    {{ $r['days_late'] }} día(s) no trabajado(s) en {{ $months[$month] ?? '' }}
                                </div>

                                <ul class="list-card__meta">
                                    <li>
                                        <span class="list-card__meta-lbl"><i class="ti ti-receipt-tax"></i> Total deuda</span>
                                        <span class="list-card__meta-val">S/ {{ number_format($r['total'], 2) }}</span>
                                    </li>
                                    @if((float)($r['exonerated'] ?? 0) > 0)
                                        <li>
                                            <span class="list-card__meta-lbl"><i class="ti ti-discount"></i> Exonerado</span>
                                            <span class="list-card__meta-val">S/ {{ number_format($r['exonerated'], 2) }}</span>
                                        </li>
                                    @endif
                                    @if((float)($r['amortized'] ?? 0) > 0)
                                        <li>
                                            <span class="list-card__meta-lbl"><i class="ti ti-arrow-down"></i> Amortizado</span>
                                            <span class="list-card__meta-val">S/ {{ number_format($r['amortized'], 2) }}</span>
                                        </li>
                                    @endif
                                    <li>
                                        <span class="list-card__meta-lbl"><i class="ti ti-alert-circle"></i> Pendiente</span>
                                        <span class="list-card__meta-val {{ $hasPending ? 'text-danger fw-bold' : '' }}">
                                            S/ {{ number_format($pending, 2) }}
                                        </span>
                                    </li>
                                    @if(!empty($r['images']))
                                        <li class="d-flex justify-content-end pt-1">
                                            <button type="button" class="btn btn-sm btn-outline-dark"
                                                    onclick="openGallery(@js($r['images']))">
                                                <i class="fa-solid fa-camera"></i> Ver imágenes
                                            </button>
                                        </li>
                                    @endif
                                </ul>
                            </article>
                        @empty
                            <div class="list-cards__empty">No se encontraron resultados</div>
                        @endforelse
                    </div>

                    {{-- ════════ DESKTOP: tabla original ════════ --}}
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th></th>
                                <th>Nº</th>
                                <th>Cod</th>
                                <th>Placa</th>
                                <th>Condición</th>
                                <th title="Días NO trabajados">DIAS NO TRABAJADOS (<span style="color:#eab723">{{ $months[$month] ?? '' }}</span>)</th>
                                <th title="Total Días no Trabajados">T.D.N.T</th>
                                <th title="Total Deuda">T. D.</th>
                                <th title="Exonerado">EX</th>
                                <th title="Total por pagar">T. D.X.P</th>
                                <th title="Amortización">AMOR</th>
                                <th title="Pendiente">PEND</th>
                                <th class="text-center" title="Imágenes">Img</th>
                            </tr>
                            </thead>

                            <tbody>
                            {{-- filas --}}
                            @forelse($rows as $r)
                                <tr wire:key="row-{{ $r['item'] }}">
                                    <td>
                                        @hasanyrole('director|gerente|administrador')
                                        @if(($r['total'] ?? 0) > 0)
                                            <a href="#" title="Editar" wire:click.prevent="detail({{ $r['id'] }})">
                                                <i class="ti ti-edit f-s-12 text-success"></i>
                                            </a>
                                        @endif
                                        @endhasanyrole
                                    </td>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $r['cod'] }}</td>
                                    <td><strong>{{ $r['plate'] }}</strong></td>
                                    <td>{{ $r['condition'] }}</td>
                                    <td>{!! $r['days_text'] !!}</td>
                                    <td>{{ $r['days_late'] }}</td>
                                    <td>{{ number_format($r['total'], 2) }}</td>
                                    <td class="title-modules">{{ number_format($r['exonerated'], 2) }}</td>
                                    <td>{{ number_format($r['to_pay'], 2) }}</td>
                                    <td>{{ number_format($r['amortized'], 2) }}</td>
                                    <td>{{ number_format($r['pending'], 2) }}</td>
                                    <td class="text-center">
                                        @if(!empty($r['images']))
                                            <i class="fa-solid fa-camera f-s-18 text-dark" style="cursor:pointer;"
                                               onclick="openGallery(@js($r['images']))"></i>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13">No se encontraron resultados.</td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="bg-primary">
                            <tr>
                                <td colspan="7">TOTAL GENERAL</td>
                                <td>{{ number_format($totals['total'] ?? 0, 2) }}</td>
                                <td>{{ number_format($totals['exonerated'] ?? 0, 2) }}</td>
                                <td>{{ number_format($totals['to_pay'] ?? 0, 2) }}</td>
                                <td>{{ number_format($totals['amortized'] ?? 0, 2) }}</td>
                                <td>{{ number_format($totals['pending'] ?? 0, 2) }}</td>
                                <td></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="mt-2 small text-muted">
                        <div>“T. d.n.t” = total de días sin pago considerados en el mes.</div>
                        <div>“T. D.x.P” = Total Deuda menos Exonerado.</div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Lightbox galería --}}
    <div class="modal fade" id="modalGallery" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="background:rgba(0,0,0,0.85);">
                <div class="modal-header border-0 pb-0 d-flex justify-content-between">
                    <span id="galleryCounter" class="text-white small"></span>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-2 position-relative">
                    <button type="button" id="galleryPrev" class="btn btn-sm btn-light position-absolute start-0 top-50 translate-middle-y ms-2" style="z-index:10;opacity:.7;">
                        <i class="fa-solid fa-chevron-left"></i>
                    </button>
                    <img id="galleryImg" src="" alt="Comprobante" class="img-fluid rounded" style="max-height:80vh;">
                    <button type="button" id="galleryNext" class="btn btn-sm btn-light position-absolute end-0 top-50 translate-middle-y me-2" style="z-index:10;opacity:.7;">
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
let _galleryImages = [], _galleryIdx = 0;

function openGallery(images) {
    _galleryImages = images;
    _galleryIdx = 0;
    _renderGallery();
    new bootstrap.Modal(document.getElementById('modalGallery')).show();
}

function _renderGallery() {
    document.getElementById('galleryImg').src = _galleryImages[_galleryIdx];
    document.getElementById('galleryCounter').textContent = (_galleryIdx + 1) + ' / ' + _galleryImages.length;
    document.getElementById('galleryPrev').style.display = _galleryImages.length > 1 ? '' : 'none';
    document.getElementById('galleryNext').style.display = _galleryImages.length > 1 ? '' : 'none';
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('galleryPrev').addEventListener('click', function () {
        _galleryIdx = (_galleryIdx - 1 + _galleryImages.length) % _galleryImages.length;
        _renderGallery();
    });
    document.getElementById('galleryNext').addEventListener('click', function () {
        _galleryIdx = (_galleryIdx + 1) % _galleryImages.length;
        _renderGallery();
    });
});
</script>
@endpush
