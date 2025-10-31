@push('styles')
    <style>

        table {
            border-collapse: collapse; /* opcional */
            width: 100%;
        }

        th, td {
            padding: 1px !important;
            font-size: 10px !important;
            text-align: center !important;
            vertical-align: middle; /* <-- clave */
        }

        .btn, input, select {
            font-size: 10px !important;
        }

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

                <div class="card-body lw-holder">
                    <div class="row my-2">
                        <div class="col-6 d-flex align-items-end ">
                            <h5>REPORTE ESTADÍSTICO DRACO {{ $year }}</h5>
                        </div>
                        <div class="col-6 d-flex align-items-end justify-content-end">
                            <select class="form-control form-control-sm w-80 mg-e-10" wire:model.live="year">
                                @for($y = now()->year + 1; $y >= 2015; $y--)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endfor
                            </select>
                            <button class="btn btn-sm btn-primary" wire:click="export">
                                <i class="ti ti-file-analytics f-s-12"></i>
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="bg-primary">
                            <tr>
                                <th>CONTROLADOR</th>
                                <th>PARADERO</th>
                                @foreach($months as $mn)
                                    <th>{{ $mn }}</th>
                                @endforeach
                                <th>TOTAL</th>
                            </tr>
                            </thead>

                            <tbody>
                            {{-- Oficina / Base --}}
                            <tr>
                                <td><strong>OFICINA</strong></td>
                                <td><strong>BASE</strong></td>
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
                                    <td><strong>{{ strtoupper($g['user']) }}</strong></td>
                                    <td></td>
                                    @foreach($months as $_) <td></td> @endforeach
                                    <td></td>
                                </tr>
                                @foreach($g['hq_rows'] as $row)
                                    <tr>
                                        <td></td>
                                        <td><strong>{{ $row['hq'] }}</strong></td>
                                        @foreach($row['m'] as $val)
                                            <td>{{ number_format($val, 2) }}</td>
                                        @endforeach
                                        <td><strong>{{ number_format($row['total'], 2) }}</strong></td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="{{ 2 + count($months) + 1 }}">
                                        No hay registros DRACO para {{ $year }}.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="bg-primary">
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
                                <th colspan="2">TOTAL GENERAL</th>
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
        <div class="col-12">
            <div class="card">

                <div class="card-body">
                    <h6 class="my-2">Resumen por Sucursal</h6>
                    <div class="row table-responsive">

                           <div class="col-md-4 col-12">
                               <table class="table table-bordered" >
                                   <thead class="bg-primary">
                                   <tr><th>SUCURSAL</th><th class="text-end">TOTAL</th></tr>
                                   </thead>
                                   <tbody>
                                   @php $sumHQ = 0; @endphp
                                   @foreach($byHeadquarter as $h)
                                       @php $sumHQ += $h['total']; @endphp
                                       <tr>
                                           <td>{{ $h['hq'] }}</td>
                                           <td class="text-end">{{ number_format($h['total'], 2) }}</td>
                                       </tr>
                                   @endforeach
                                   <tr>
                                       <td>BASE</td>
                                       <td class="text-end">{{ number_format($grandTotalBase, 2) }}</td>
                                   </tr>
                                   </tbody>
                                   <tfoot class="table-primary">
                                   <tr>
                                       <th>TOTAL</th>
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
    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="export,year">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>
