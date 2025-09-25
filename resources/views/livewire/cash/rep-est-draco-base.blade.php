@push('styles')
    <style>
        /* ===== Tabla compacta, estilo homologado ===== */
        .compact-table-xxs{
            font-size:12px; line-height:1.12; table-layout:fixed;
        }
        .compact-table-xxs th,.compact-table-xxs td{
            padding:.22rem .35rem; white-space:nowrap; vertical-align:middle; text-align:center;
        }
        .sticky{ position:sticky; top:0; z-index:2; background:#e9f4ff; }
        .table thead th{ background:#e9f4ff; }
        .user-header td{ background:blue; color:#000; font-weight:700; }

        /* Cols angostas para dar respiro a los 12 meses */
        .col-ctrl { width: 160px; text-align:left; }
        .col-hq   { width: 160px; text-align:left; }
        .col-tot  { width: 110px; }

        /* Overlay de carga solo cuando cambia "year" */
        .lw-holder{ position:relative; }
        .lw-overlay{
            position:absolute; inset:0; background:rgba(255,255,255,.55);
            display:flex; align-items:center; justify-content:center; gap:.5rem; z-index:10;
        }

        /* Ajustes de tabla lateral (resumen HQ) */
        .mini-table th,.mini-table td{ padding:.25rem .45rem; }
    </style>
@endpush

<div class="container-fluid">

    {{-- Header / migas --}}
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Reporte Estadístico DRACO</h4>
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
                    <a href="#" class="f-s-14">Estadístico DRACO</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">

        {{-- Filtros --}}
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row g-2 align-items-end justify-content-end">
                        <div class="col-sm-4 col-md-3">
                            <label class="form-label">Año</label>
                            <select class="form-select" wire:model.live="year">
                                @for($y = now()->year + 1; $y >= 2015; $y--)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <button class="btn btn-primary w-100" wire:click="export">
                                <i class="ti ti-file-analytics f-s-16"></i> Exportar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        {{-- Tabla principal --}}
        <div class="col-12">
            <div class="card">
                <div class="card-body lw-holder">

                    {{-- Overlay de carga solo cuando varía "year" --}}
                    <div class="lw-overlay" wire:loading wire:target="year">
                        <div class="spinner-border" role="status" aria-hidden="true"></div>
                        <span class="text-muted">Cargando…</span>
                    </div>

                    <div class="text-center mb-2">
                        <h5 class="mb-0" style="color:#e11d48;">REPORTE ESTADÍSTICO DRACO {{ $year }}</h5>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped compact-table-xxs">
                            <thead>
                            <tr>
                                <th class="sticky col-ctrl">CONTROLADOR</th>
                                <th class="sticky col-hq">PARADERO</th>
                                @foreach($months as $mn)
                                    <th class="sticky">{{ $mn }}</th>
                                @endforeach
                                <th class="sticky col-tot">TOTAL</th>
                            </tr>
                            </thead>

                            <tbody>
                            {{-- Oficina / Base --}}
                            <tr>
                                <td class="text-start"><strong>OFICINA</strong></td>
                                <td class="text-start"><strong>BASE</strong></td>
                                @php $tBase = 0; @endphp
                                @foreach($baseMonthly as $val)
                                    @php $tBase += $val; @endphp
                                    <td>{{ number_format($val, 2) }}</td>
                                @endforeach
                                <td><strong>{{ number_format($tBase, 2) }}</strong></td>
                            </tr>

                            {{-- Grupos DRACO: Usuario -> HQs --}}
                            @forelse($groups as $g)
                                <tr class="user-header">
                                    <td class="text-start">{{ strtoupper($g['user']) }}</td>
                                    <td></td>
                                    @foreach($months as $_) <td></td> @endforeach
                                    <td></td>
                                </tr>
                                @foreach($g['hq_rows'] as $row)
                                    <tr>
                                        <td></td>
                                        <td class="text-start"><strong>{{ $row['hq'] }}</strong></td>
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
                            {{-- Si quieres fila de SOLO DRACO, descomenta:
                            <tr>
                              <th colspan="2">TOTAL DRACO</th>
                              @for($i=1;$i<=12;$i++)
                                <th>{{ number_format($totalsByMonth[$i] ?? 0, 2) }}</th>
                              @endfor
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

                </div>
            </div>
        </div>

        {{-- Resumen por Sucursal (DRACO) + BASE + Total --}}
        <div class="col-12 mt-2">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-2">Resumen por Sucursal</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mini-table" style="max-width:420px;">
                            <thead>
                            <tr><th class="text-start">SUCURSAL</th><th class="text-end">TOTAL</th></tr>
                            </thead>
                            <tbody>
                            @php $sumHQ = 0; @endphp
                            @foreach($byHeadquarter as $h)
                                @php $sumHQ += $h['total']; @endphp
                                <tr>
                                    <td class="text-start">{{ $h['hq'] }}</td>
                                    <td class="text-end">{{ number_format($h['total'], 2) }}</td>
                                </tr>
                            @endforeach
                            <tr>
                                <td class="text-start">BASE</td>
                                <td class="text-end">{{ number_format($grandTotalBase, 2) }}</td>
                            </tr>
                            </tbody>
                            <tfoot class="table-primary">
                            <tr>
                                <th class="text-start">TOTAL</th>
                                <th class="text-end">{{ number_format($sumHQ + $grandTotalBase, 2) }}</th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

@push('scripts')
    <script>
        (function(){
            const downBtn = document.getElementById('down');
            downBtn?.addEventListener('click', function(e){
                e.preventDefault();
                window.scrollTo({ top: document.body.scrollHeight, behavior:'smooth' });
            });
        })();
    </script>
@endpush
