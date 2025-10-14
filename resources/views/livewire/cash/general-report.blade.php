@push('styles')
    <style>
        .bg-saldo{
            background: #009BDC;
            color: #fff;
        }
        .table thead th {
            color: #fff;
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

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Mes</label>
                            <select wire:model.live="month" class="form-select">
                                @for($m=1;$m<=12;$m++)
                                    <option value="{{ $m }}">{{ \Carbon\Carbon::create(null, $m, 1)->locale('es')->translatedFormat('F') }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Año</label>
                            <select wire:model.live="year" class="form-select">
                                @for($y=now()->year-5;$y<=now()->year+1;$y++)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-1  d-flex align-items-end">
                            <button class="btn btn-primary w-100" id="down">
                                <i class="ti ti-square-chevrons-down f-s-17"></i>
                            </button>
                        </div>
                    </div>

                </div>
                <div class="card-body">
                    <table class="table table-bordered table-hover">
                        <thead class="bg-primary">
                        <tr>
                            <th class="px-2 py-2 w-14">ITEM</th>
                            <th class="px-2 py-2 w-28">FECHA</th>
                            <th class="px-2 py-2 w-72">DATOS CLIENTE</th>
                            <th class="px-2 py-2">GLOSA</th>
                            <th class="px-2 py-2 w-28 text-end">INGRESO</th>
                            <th class="px-2 py-2 w-28 text-end">EGRESO</th>
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
                                    <td class="px-2 py-1 text-center">{{ $item++ }}</td>
                                    <td class="px-2 py-1">{{ $r['date'] }}</td>
                                    <td class="px-2 py-1">{{ $r['cliente'] ?? '—' }}</td>  {{-- DATOS CLIENTE --}}
                                    <td class="px-2 py-1">{{ $r['glosa'] }}</td>
                                    <td class="px-2 py-1 text-end">{{ $r['ingreso'] ? number_format($r['ingreso'],2) : '0.00' }}</td>
                                    <td class="px-2 py-1 text-end">{{ $r['egreso'] ? number_format($r['egreso'],2) : '0.00' }}</td>
                                </tr>
                            @endforeach

                            @if($hayMov)
                                {{-- FOOT del día: SALDO FINAL–INICIAL + Saldos en la misma fila --}}
                                <tr class="bg-saldo fw-semibold">

                                    {{-- Columna "DATOS CLIENTE" --}}
                                    <td class="px-2 py-2" colspan="4">
                                        SALDO FINAL–INICIAL
                                        <span class="me-3 opacity-75">
                    Saldo del día:
                    <span class="font-monospace">{{ number_format($saldoD, 2) }}</span>
                </span>
                                        <span class="fw-medium">Saldo acumulado:</span>
                                        <span class="font-monospace">{{ number_format($saldoA, 2) }}</span>
                                    </td>

                                    {{-- Totales del día --}}
                                    <td class="px-2 py-2 text-end">{{ number_format($sumI, 2) }}</td>
                                    <td class="px-2 py-2 text-end">{{ number_format($sumE, 2) }}</td>
                                </tr>
                            @endif
                        @endforeach

                        </tbody>

                        {{-- FOOTER del mes --}}
                        <tfoot>
                        <tr class="bg-dark text-white fw-semibold">
                            <td class="px-2 py-2 text-center" colspan="4">TOTAL GENERAL</td>
                            <td class="px-2 py-2 text-end">{{ number_format($totalIncomes,2) }}</td>
                            <td class="px-2 py-2 text-end">{{ number_format($totalExpenses,2) }}</td>
                        </tr>
                        <tr class="table-light fw-bold">
                            <td class="px-2 py-2" colspan="2">UTILIDAD</td>
                            <td class="px-2 py-2" colspan="2"></td>
                            <td class="px-2 py-2 text-end text-primary">
                                {{ number_format($finalBalance,2) }}
                            </td>
                            <td class="px-2 py-2"></td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>
