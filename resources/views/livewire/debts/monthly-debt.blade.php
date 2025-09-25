@push('styles')
    <style>
        /* ===== Compacto, igual que DebtsPerDays ===== */
        .compact-table-xxs{ font-size:11px; line-height:1.05; table-layout:fixed; }
        .compact-table-xxs th,.compact-table-xxs td{
            padding:.18rem .25rem; white-space:nowrap; vertical-align:middle; text-align:center;
        }
        .compact-table-xxs thead th.sticky{ position:sticky; top:0; z-index:2; background:#e9f4ff; }

        /* Contenedor con scroll horizontal cuando no alcance */
        .x-scroll{ overflow-x:auto; overflow-y:visible; }

        /* Anchos consistentes */
        .col-edit{ width:42px; }
        .col-cod{  width:60px; }
        .col-plate{width:92px; }
        .col-cond{ width:72px; }
        .col-dayslbl{ min-width:160px; }   /* “Días NO trabajados” */
        .col-dnum{ width:72px; }           /* T. d.n.t (DÍAS) */
        .col-money{ width:92px; }          /* montos S/ */
        .text-end{ text-align:right !important; }
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

        {{-- Filtros (arriba) --}}
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row g-2 align-items-end">
                        <div class="col-xl-3 col-md-4">
                            <label class="form-label">Mes</label>
                            <select class="form-select" wire:model.live="month">
                                @foreach($months as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-4">
                            <label class="form-label">Año</label>
                            <select class="form-select" wire:model.live="year">
                                @foreach($years as $y)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-4">
                            <label class="form-label">Buscar placa</label>
                            <input type="search" class="form-control" placeholder="ABC-123"
                                   wire:model.live.debounce.300ms="search">
                        </div>
                        <div class="col-xl-3 col-md-4">
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
                    </div>
                </div>
            </div>
        </div>

        {{-- Acciones (abajo), como en Payments/DebtsPerDays --}}
        <div class="col-12 mt-2">
            <div class="card">
                <div class="card-body">
                    <div class="row justify-content-end g-2">
                        <div class="col-sm-3 col-md-2">
                            <button class="btn btn-primary w-100" wire:click="export">
                                <i class="ti ti-file-analytics f-s-16"></i> Exportar
                            </button>
                        </div>
                        <div class="col-sm-2 col-md-1">
                            <button id="down" class="btn btn-primary w-100">
                                <i class="ti ti-square-chevrons-down f-s-17"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div id="bottom"></div>
        </div>

        {{-- Tabla --}}
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">

                    <div class="table-responsive x-scroll">
                        <table class="table table-sm table-bordered table-striped align-middle compact-table-xxs">
                            <thead class="table-primary">
                            <tr>
                                <th class="sticky col-edit">Op</th>
                                <th class="sticky col-cod">Cod</th>
                                <th class="sticky col-plate">Placa</th>
                                <th class="sticky col-cond">Condición</th>
                                <th class="sticky col-dayslbl" title="Días NO trabajados">Días NO trabajados</th>
                                <th class="sticky col-dnum" title="Total Días no Trabajados">T. d.n.t</th>
                                <th class="sticky col-money" title="Total Deuda (S/)">T. D. (S/)</th>
                                <th class="sticky col-money text-danger" title="Exonerado (S/)">Ex (S/)</th>
                                <th class="sticky col-money" title="Total por pagar (S/)">T. D.x.P (S/)</th>
                                <th class="sticky col-money" title="Amortización (S/)">Amor (S/)</th>
                                <th class="sticky col-money" title="Pendiente (S/)">Pend (S/)</th>
                            </tr>
                            </thead>

                            <tbody>
                            {{-- fila loading --}}
                            <tr wire:loading>
                                <td colspan="11" class="text-center">
                                    <div class="d-flex justify-content-center align-items-center gap-2 py-2">
                                        <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
                                        <span>Cargando…</span>
                                    </div>
                                </td>
                            </tr>

                            {{-- filas --}}
                            @forelse($rows as $r)
                                <tr wire:key="row-{{ $r['item'] }}" wire:loading.class="d-none">
                                    <td>
                                        @if(($r['total'] ?? 0) > 0)
                                            <a href="#" title="Editar" wire:click.prevent="detail({{ $r['id'] }})">
                                                <i class="ti ti-edit"></i>
                                            </a>
                                        @endif
                                    </td>
                                    <td>{{ $r['cod'] }}</td>
                                    <td><strong>{{ $r['plate'] }}</strong></td>
                                    <td>{{ $r['condition'] }}</td>
                                    <td class="text-start">{!! $r['days_text'] !!}</td>
                                    <td>{{ $r['days_late'] }}</td>
                                    <td class="text-end">{{ number_format($r['total'], 2) }}</td>
                                    <td class="text-end text-danger">{{ number_format($r['exonerated'], 2) }}</td>
                                    <td class="text-end">{{ number_format($r['to_pay'], 2) }}</td>
                                    <td class="text-end">{{ number_format($r['amortized'], 2) }}</td>
                                    <td class="text-end">{{ number_format($r['pending'], 2) }}</td>
                                </tr>
                            @empty
                                <tr wire:loading.class="d-none">
                                    <td colspan="11" class="text-center">No se encontraron resultados.</td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="table-primary fw-bold">
                            <tr>
                                <td colspan="6" class="text-center">Total General</td>
                                <td class="text-end">{{ number_format($totals['total'] ?? 0, 2) }}</td>
                                <td class="text-end">{{ number_format($totals['exonerated'] ?? 0, 2) }}</td>
                                <td class="text-end">{{ number_format($totals['to_pay'] ?? 0, 2) }}</td>
                                <td class="text-end">{{ number_format($totals['amortized'] ?? 0, 2) }}</td>
                                <td class="text-end">{{ number_format($totals['pending'] ?? 0, 2) }}</td>
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
</div>
