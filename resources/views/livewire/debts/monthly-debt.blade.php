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

                                <button class="btn btn-sm btn-dark flex-shrink-0 align-self-end" wire:click="$refresh">
                                    <i class="ti ti-search f-s-12"></i>
                                </button>

                                <!-- Mes -->
                                <div class="flex-shrink-0" >
                                    <label class="form-label mb-1">Mes</label>
                                    <select class="form-select form-select-sm" wire:model.live="month">
                                        @foreach($months as $val => $label)
                                            <option value="{{ $val }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Año -->
                                <div class="flex-shrink-0">
                                    <label class="form-label mb-1">Año</label>
                                    <select class="form-select form-select-sm" wire:model.live="year">
                                        @foreach($years as $y)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endforeach
                                    </select>
                                </div>



                                <!-- Condición -->
                                <div class="flex-shrink-0">
                                    <label class="form-label mb-1">Condición</label>
                                    <select class="form-select form-select-sm" wire:model.live="condition">
                                        <option value="">Todas</option>
                                        <option value="DT">DT</option>
                                        <option value="GN">GN</option>
                                        <option value="EX">EX</option>
                                        <option value="EX5">EX5</option>
                                        <option value="Exonerado">Exonerado</option>
                                        <option value="Amortizado">Amortizado</option>
                                    </select>
                                </div>

                                <!-- Exportar -->
                                <button class="btn btn-sm btn-primary flex-shrink-0 align-self-end"
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
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th></th>
                                <th>Cod</th>
                                <th>Placa</th>
                                <th>Condición</th>
                                <th title="Días NO trabajados">DIAS NO TRABAJADOS(<span style="color:#eab723">{{ $months[$month] ?? '' }}</span>)</th>
                                <th title="Total Días no Trabajados">T. d.n.t</th>
                                <th title="Total Deuda (S/)">T. D. (S/)</th>
                                <th title="Exonerado (S/)">Ex (S/)</th>
                                <th title="Total por pagar (S/)">T. D.x.P (S/)</th>
                                <th title="Amortización (S/)">Amor (S/)</th>
                                <th title="Pendiente (S/)">Pend (S/)</th>
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
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11">No se encontraron resultados.</td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="bg-primary">
                            <tr>
                                <td colspan="6">TOTAL GENERAL</td>
                                <td>{{ number_format($totals['total'] ?? 0, 2) }}</td>
                                <td>{{ number_format($totals['exonerated'] ?? 0, 2) }}</td>
                                <td>{{ number_format($totals['to_pay'] ?? 0, 2) }}</td>
                                <td>{{ number_format($totals['amortized'] ?? 0, 2) }}</td>
                                <td>{{ number_format($totals['pending'] ?? 0, 2) }}</td>
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
