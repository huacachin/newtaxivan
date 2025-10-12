@push('styles')
    <style>

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

        {{-- Tabla principal --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="row g-2">
                        <div class="col-md-6 d-flex align-items-center">
                            <h5>REPORTE ESTADÍSTICO DRACO {{ $year }}</h5>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" wire:model.live="year">
                                @for($y = now()->year + 1; $y >= 2015; $y--)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100" wire:click="export">
                                <i class="ti ti-file-analytics f-s-16"></i> Exportar
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body lw-holder">

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped compact-table-xxs">
                            <thead class="bg-primary">
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
                                <tr>
                                    <td class="text-start"><strong>{{ strtoupper($g['user']) }}</strong></td>
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
                            <thead class="bg-primary">
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
    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="export,year">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
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
