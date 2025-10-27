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

    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Reporte General</h4>
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
                    <a href="#" class="f-s-14">Reporte General</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row">

        {{-- ===== Table ===== --}}
        <div class="col-xl-12">
            <div class="card">
                <div class="card-header">

                    <div class="row mt-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-2 overflow-auto py-1">

                                <!-- Mes -->
                                <div class="flex-shrink-0" style="min-width: 220px;">
                                    <label class="form-label mb-1">Mes</label>
                                    <select wire:model.live="month" class="form-select form-select-sm">
                                        @for($m=1;$m<=12;$m++)
                                            <option value="{{ $m }}">
                                                {{ \Carbon\Carbon::create(null, $m, 1)->locale('es')->translatedFormat('F') }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>

                                <!-- Año -->
                                <div class="flex-shrink-0" style="min-width: 160px;">
                                    <label class="form-label mb-1">Año</label>
                                    <select wire:model.live="year" class="form-select form-select-sm">
                                        @for($y=now()->year-5;$y<=now()->year+1;$y++)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endfor
                                    </select>
                                </div>

                                <!-- Exportar -->
                                <button class="btn btn-sm btn-primary flex-shrink-0 align-self-end"
                                        wire:click="export">
                                    <i class="ti ti-file-analytics f-s-12"></i> Exportar
                                </button>

                                <!-- Ir al final -->
                                <button class="btn btn-sm btn-primary flex-shrink-0 align-self-end"
                                        id="down" type="button" title="Ir al final">
                                    <i class="ti ti-square-chevrons-down f-s-12"></i>
                                </button>

                            </div>
                        </div>
                    </div>



                </div>
                <div class="card-body">
                    <table class="table table-bordered table-hover">
                        <thead class="bg-primary">
                        <tr>
                            <th>ITEM</th>
                            <th>FECHA</th>
                            <th>DATOS CLIENTE</th>
                            <th>GLOSA</th>
                            <th>INGRESO</th>
                            <th>EGRESO</th>
                        </tr>
                        </thead>

                        <tbody>
                        @php $item=1; @endphp

                        @foreach($rowsByDay as $day => $pack)
                            @php
                                $rows    = $pack['rows'];
                                $sumI    = $pack['sum_ingreso'];
                                $sumE    = $pack['sum_egreso'];
                                $saldoD  = $pack['saldo_dia'];   // saldo del día
                                $saldoA  = $pack['saldo_acum'];  // saldo acumulado
                                $hayMov  = ($sumI != 0 || $sumE != 0); // ¿hubo algo este día?
                            @endphp


                            {{-- Filas del día (si no hay, este foreach no imprime nada) --}}
                            @foreach($rows as $r)
                                <tr>
                                    <td>{{ $item++ }}</td>
                                    <td>{{ $r['date'] }}</td>
                                    <td>{{ $r['cliente'] ?? '—' }}</td>  {{-- DATOS CLIENTE --}}
                                    <td>{{ $r['glosa'] }}</td>
                                    <td>{{ $r['ingreso'] ? number_format($r['ingreso'],2) : '0.00' }}</td>
                                    <td>{{ $r['egreso'] ? number_format($r['egreso'],2) : '0.00' }}</td>
                                </tr>
                            @endforeach

                            @if($hayMov)
                                {{-- FOOT del día: SALDO FINAL–INICIAL + Saldos en la misma fila --}}
                                <tr class="bg-primary">

                                    {{-- Columna "DATOS CLIENTE" --}}
                                    <td colspan="4">
                                        SALDO FINAL–INICIAL
                                        <span>
                    Saldo del día:
                    <span>{{ number_format($saldoD, 2) }}</span>
                </span>
                                        <span>Saldo acumulado:</span>
                                        <span>{{ number_format($saldoA, 2) }}</span>
                                    </td>

                                    {{-- Totales del día --}}
                                    <td>{{ number_format($sumI, 2) }}</td>
                                    <td>{{ number_format($sumE, 2) }}</td>
                                </tr>
                            @endif
                        @endforeach

                        </tbody>

                        {{-- FOOTER del mes --}}
                        <tfoot class="bg-primary">
                        <tr>
                            <td colspan="4">TOTAL GENERAL</td>
                            <td>{{ number_format($totalIncomes,2) }}</td>
                            <td>{{ number_format($totalExpenses,2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="4">UTILIDAD</td>
                            <td colspan="2">
                                {{ number_format($finalBalance,2) }}
                            </td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

    </div>
    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="export,year,month">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>
