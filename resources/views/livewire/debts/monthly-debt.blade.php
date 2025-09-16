<div class="container-fluid">

    <div class="row mb-3">
        <div class="col-12 d-flex align-items-center gap-2">
            <h4 class="mb-0">DEUDA</h4>

            <button class="btn btn-sm btn-outline-primary" wire:click="prevMonth">« Mes anterior</button>

            <input type="date" class="form-control form-control-sm" style="max-width: 180px"
                   wire:model.live="monthDate">

            <button class="btn btn-sm btn-outline-primary" wire:click="nextMonth">Mes siguiente »</button>

            <div class="ms-auto d-flex align-items-center gap-2">
                <input type="search" class="form-control form-control-sm" placeholder="Buscar placa…"
                       wire:model.live="search" style="max-width: 220px">

                <select class="form-select form-select-sm" wire:model.live="condition" style="max-width: 180px">
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

    <div class="card">
        <div class="card-body table-responsive">

            <table class="table table-sm table-bordered table-striped align-middle">
                <thead class="table-light">
                <tr>
                    <th>Editar</th>
                    <th>Cod</th>
                    <th>Placa</th>
                    <th>Condición</th>
                    <th>Días NO trabajados</th>
                    <th title="Total Días no Trabajados">T. d.n.t</th> {{-- DÍAS --}}
                    <th title="Total Deuda (S/)">T. D. (S/)</th>
                    <th title="Exonerado (S/)">Ex (S/)</th>
                    <th title="Total por pagar (S/)">T. D.x.P (S/)</th> {{-- T.D - Ex --}}
                    <th title="Amortización (S/)">Amor (S/)</th>
                    <th title="Pendiente (S/)">Pend (S/)</th> {{-- T.D - Ex - Amor --}}
                </tr>
                </thead>

                <tbody>

                {{-- Filas --}}
                @forelse($rows as $r)
                    <tr wire:key="row-{{ $r['item'] }}" wire:loading.class="d-none">
                        <td>
                            @if(($r['total'] ?? 0) > 0)
                                <a href="#" title="Editar" wire:click="detail({{$r['id']}})">
                                    <i class="ti ti-edit"></i>
                                </a>
                            @endif
                        </td>
                        <td>{{ $r['cod'] }}</td>
                        <td>{{ $r['plate'] }}</td>
                        <td>{{ $r['condition'] }}</td>
                        <td>{!! $r['days_text'] !!}</td>
                        <td>{{ $r['days_late'] }}</td>
                        <td>{{ number_format($r['total'], 2) }}</td>
                        <td class="text-danger">{{ number_format($r['exonerated'], 2) }}</td>
                        <td>{{ number_format($r['to_pay'], 2) }}</td>
                        <td>{{ number_format($r['amortized'], 2) }}</td>
                        <td>{{ number_format($r['pending'], 2) }}</td>
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
                    <td>{{ number_format($totals['total'] ?? 0, 2) }}</td>
                    <td>{{ number_format($totals['exonerated'] ?? 0, 2) }}</td>
                    <td>{{ number_format($totals['to_pay'] ?? 0, 2) }}</td>
                    <td>{{ number_format($totals['amortized'] ?? 0, 2) }}</td>
                    <td>{{ number_format($totals['pending'] ?? 0, 2) }}</td>
                </tr>
                </tfoot>
            </table>

        </div>
    </div>
</div>
