<div class="container-fluid">
    <div class="text-center mb-2">
        <h4 class="mb-2" style="color:#e11d48;">REPORTE ESTADISTICO DRACO {{ $year }}</h4>
        <div class="d-inline-flex align-items-center gap-2">
            <label class="me-1">Seleccione mes y Año</label>
            <select class="form-select form-select-sm" style="max-width:120px" wire:model.live="year">
                @for($y = now()->year+1; $y >= 2015; $y--)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>
            <button class="btn btn-primary btn-sm" wire:click="recalc">
                <i class="bi bi-search"></i> Consultar
            </button>
            <button class="btn btn-info btn-sm" onclick="history.back()">
                <i class="bi bi-reply"></i> Regresar
            </button>
        </div>
    </div>

    <div class="mb-2" wire:loading.delay>
        <span class="text-muted"><span class="spinner-border spinner-border-sm"></span> Cargando…</span>
    </div>

    <style>
        th, td { white-space: nowrap; vertical-align: middle; text-align:center; }
        thead th.sticky { position: sticky; top: 0; background: #0ea5e9; color:#fff; z-index: 1; }
        .user-header td { background:#0284c7; color:#fff; font-weight:700; }
        .table thead th { background:#0ea5e9; color:#fff; }
    </style>

    <div class="table-responsive">
        <table class="table table-sm table-bordered table-striped">
            <thead>
            <tr>
                <th class="sticky">CONTROLADOR</th>
                <th class="sticky">PARADERO</th>
                @foreach($months as $mn)
                    <th class="sticky">{{ $mn }}</th>
                @endforeach
                <th class="sticky">TOTAL</th>
            </tr>
            </thead>
            <tbody>
            {{-- Oficina / Base --}}
            <tr>
                <td><strong>OFICINA</strong></td>
                <td><strong>BASE</strong></td>
                @php $tBase=0; @endphp
                @foreach($baseMonthly as $val)
                    @php $tBase += $val; @endphp
                    <td>{{ number_format($val,2) }}</td>
                @endforeach
                <td><strong>{{ number_format($tBase,2) }}</strong></td>
            </tr>

            {{-- Grupos: Usuario -> HQs --}}
            @forelse($groups as $g)
                <tr class="user-header">
                    <td style="text-align:left;">{{ strtoupper($g['user']) }}</td>
                    <td></td>
                    @foreach($months as $_) <td></td> @endforeach
                    <td></td>
                </tr>
                @foreach($g['hq_rows'] as $row)
                    <tr>
                        <td></td>
                        <td style="text-align:left;"><strong>{{ $row['hq'] }}</strong></td>
                        @foreach($row['m'] as $val)
                            <td>{{ number_format($val, 2) }}</td>
                        @endforeach
                        <td><strong>{{ number_format($row['total'], 2) }}</strong></td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="{{ 2 + count($months) + 1 }}" class="text-center text-muted">
                        No hay registros DRACO para {{ $year }}.
                    </td>
                </tr>
            @endforelse
            </tbody>

            <tfoot class="table-primary">
            {{-- (Opcional) fila solo DRACO
            <tr>
                <th colspan="2">TOTAL DRACO</th>
                @foreach($totalsByMonth as $val)
                    <th>{{ number_format($val, 2) }}</th>
                @endforeach
                <th>{{ number_format($grandTotalDraco, 2) }}</th>
            </tr>
            --}}
            <tr>
                <th colspan="2">TOTAL GENERAL (DRACO + BASE)</th>
                @for($i=1;$i<=12;$i++)
                    <th>{{ number_format($totalsCombinedByMonth[$i] ?? 0, 2) }}</th>
                @endfor
                <th>{{ number_format($grandTotalCombined, 2) }}</th>
            </tr>
            </tfoot>
        </table>
    </div>

    {{-- Resumen por Sucursal (DRACO) + BASE + Total --}}
    <div class="row mt-3">
        <div class="col-md-4">
            <table class="table table-sm table-bordered" style="width: 260px;">
                <thead>
                <tr><th>SUCURSAL</th><th class="text-end">TOTAL</th></tr>
                </thead>
                <tbody>
                @php $sumHQ = 0; @endphp
                @foreach($byHeadquarter as $h)
                    @php $sumHQ += $h['total']; @endphp
                    <tr>
                        <td style="text-align:left;">{{ $h['hq'] }}</td>
                        <td class="text-end">{{ number_format($h['total'], 2) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td style="text-align:left;">BASE</td>
                    <td class="text-end">{{ number_format($grandTotalBase, 2) }}</td>
                </tr>
                </tbody>
                <tfoot>
                <tr>
                    <th style="text-align:left;">Total</th>
                    <th class="text-end">{{ number_format($sumHQ + $grandTotalBase, 2) }}</th>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
