@push('styles')
    <style>
        /* Encabezado/foot oscuros y pegajosos — igual que Payments */
        .tableFixHead thead th{
            position: sticky; top: 0; z-index: 2;
            background-color:#009BDC !important; color:#fff !important; vertical-align: middle;
        }
        .tableFixHead tfoot th,
        .tableFixHead tfoot td{
            background-color:#009BDC !important; color:#fff !important;
        }

        /* Ajuste al contenido y números alineados a la derecha */
        .tableFixHead table.table th,
        .tableFixHead table.table td{ white-space:nowrap; }
        .num{ text-align:right; }

        .screen-overlay {
            position: fixed;
            inset: 0;                 /* full viewport */
            display: none;            /* Livewire lo pondrá en flex */
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,.35);
            backdrop-filter: blur(2px);
            z-index: 2000;            /* sobre modals/backdrops de Bootstrap */
            pointer-events: all;      /* bloquea clics */
        }

        /* Overlay LOCAL solo dentro del card-body */
        .card-body { position: relative; }
        .screen-overlay-local {
            position: absolute;       /* solo cubre el card-body */
            inset: 0;
            display: none;            /* Livewire lo pone en flex */
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,.35);
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
            z-index: 10;              /* encima del contenido, debajo de modals */
            pointer-events: all;      /* bloquea clics dentro del card-body */
            color:#FFF;
        }
    </style>
@endpush

<div class="container-fluid">
    {{-- Header / migas --}}
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Deuda mensual</h4>
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
            <div class="card shadow-sm">
                <div class="card-header">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label">Mes</label>
                            <select class="form-select" wire:model.live="month">
                                @foreach($months as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Año</label>
                            <select class="form-select" wire:model.live="year">
                                @foreach($years as $y)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Buscar placa</label>
                            <input type="search" class="form-control" placeholder="ABC-123"
                                   wire:model.live.debounce.750ms="search">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Condición</label>
                            <select class="form-select" wire:model.live="condition">
                                <option value="">Todas</option>
                                <option value="DT">DT</option>
                                <option value="GN">GN</option>
                                <option value="EX">EX</option>
                                <option value="EX5">EX5</option>
                                <option value="Exonerado">Exonerado</option>
                                <option value="Amortizado">Amortizado</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary w-100" wire:click="export">
                                <i class="ti ti-file-analytics f-s-16"></i> Exportar
                            </button>
                        </div>
                        <div class="col-md-1">
                            <button id="down" class="btn btn-primary w-100" title="Ir al final">
                                <i class="ti ti-square-chevrons-down f-s-17"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive tableFixHead">
                        <div class="screen-overlay-local"
                             wire:loading.flex
                             wire:target="search">
                            <div class="text-center">
                                <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
                                <div class="mt-2 text-white fw-semibold">Cargando…</div>
                            </div>
                        </div>
                        <table class="table table-sm table-bordered table-striped table-hover align-middle">
                            <thead class="text-center">
                            <tr>
                                <th>Op</th>
                                <th>Cod</th>
                                <th>Placa</th>
                                <th>Condición</th>
                                <th title="Días NO trabajados">Días NO trabajados</th>
                                <th title="Total Días no Trabajados">T. d.n.t</th>
                                <th title="Total Deuda (S/)" class="num">T. D. (S/)</th>
                                <th title="Exonerado (S/)" class="num">Ex (S/)</th>
                                <th title="Total por pagar (S/)" class="num">T. D.x.P (S/)</th>
                                <th title="Amortización (S/)" class="num">Amor (S/)</th>
                                <th title="Pendiente (S/)" class="num">Pend (S/)</th>
                            </tr>
                            </thead>

                            <tbody>
                            {{-- filas --}}
                            @forelse($rows as $r)
                                <tr wire:key="row-{{ $r['item'] }}" wire:loading.class="d-none">
                                    <td class="text-center">
                                        @if(($r['total'] ?? 0) > 0)
                                            <a href="#" title="Editar" wire:click.prevent="detail({{ $r['id'] }})">
                                                <i class="ti ti-edit"></i>
                                            </a>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $r['cod'] }}</td>
                                    <td class="text-center"><strong>{{ $r['plate'] }}</strong></td>
                                    <td class="text-center">{{ $r['condition'] }}</td>
                                    <td class="text-start">{!! $r['days_text'] !!}</td>
                                    <td class="text-center">{{ $r['days_late'] }}</td>
                                    <td class="num">{{ number_format($r['total'], 2) }}</td>
                                    <td class="num text-danger">{{ number_format($r['exonerated'], 2) }}</td>
                                    <td class="num">{{ number_format($r['to_pay'], 2) }}</td>
                                    <td class="num">{{ number_format($r['amortized'], 2) }}</td>
                                    <td class="num">{{ number_format($r['pending'], 2) }}</td>
                                </tr>
                            @empty
                                <tr wire:loading.class="d-none">
                                    <td colspan="11" class="text-center">No se encontraron resultados.</td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="text-center fw-semibold">
                            <tr>
                                <td colspan="6">TOTAL GENERAL</td>
                                <td class="num">{{ number_format($totals['total'] ?? 0, 2) }}</td>
                                <td class="num">{{ number_format($totals['exonerated'] ?? 0, 2) }}</td>
                                <td class="num">{{ number_format($totals['to_pay'] ?? 0, 2) }}</td>
                                <td class="num">{{ number_format($totals['amortized'] ?? 0, 2) }}</td>
                                <td class="num">{{ number_format($totals['pending'] ?? 0, 2) }}</td>
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
    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="export,month,year,condition">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>
