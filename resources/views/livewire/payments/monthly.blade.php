@push('styles')
    <style>
        .cond-badge {
            display:inline-block; padding:.15rem .4rem; border-radius:.35rem;
            font-size:.75rem; font-weight:600; letter-spacing:.3px;
        }
        .cond-EX  { background:#e2e8f0; color:#334155; }
        .cond-GN  { background:#fef3c7; color:#92400e; }
        .cond-DT  { background:#dcfce7; color:#166534; }
    </style>
@endpush
@php
    $monthName = \Illuminate\Support\Str::upper(\Carbon\Carbon::create($year, $month, 1)->translatedFormat('F'));
@endphp
<div class="container-fluid">
    <!-- Header -->
    <div class="row align-items-center mb-3">
        <div class="col-sm-6">
            <h4 class="main-title mb-0 title-modules">REPORTE MENSUAL DE PAGO – {{$monthName}} DEL {{$year}}</h4>
        </div>
        <div class="col-sm-6 mt-sm-0 mt-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end mb-0">
                <li class="d-flex">
                    <i class="ti ti-cash f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Pagos</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Mensual</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">

        <!-- Tabla -->
        <div class="col-12">
            <div class="card shadow-sm">

                <div class="card-body pb-2">

                    <div class="row mb-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-2 overflow-auto py-1">

                                <!-- Mes -->
                                <div class="flex-shrink-0" style="min-width: 160px;">
                                    <label class="form-label mb-1">Mes</label>
                                    <select class="form-select form-select-sm" wire:model="month">
                                        @foreach($months as $mVal => $mName)
                                            <option value="{{ $mVal }}">{{ $mName }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Año -->
                                <div class="flex-shrink-0" style="min-width: 120px;">
                                    <label class="form-label mb-1">Año</label>
                                    <select class="form-select form-select-sm" wire:model="year">
                                        @foreach($years as $y)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Condición -->
                                <div class="flex-shrink-0" style="min-width: 140px;">
                                    <label class="form-label mb-1">Condición</label>
                                    <select class="form-select form-select-sm" wire:model="cond">
                                        <option value="">Todos</option>
                                        <option value="EX">EX</option>
                                        <option value="GN">GN</option>
                                        <option value="DT">DT</option>
                                        <option value="EX5">EX5</option>
                                    </select>
                                </div>

                                                                <button class="btn btn-sm btn-dark flex-shrink-0 align-self-end" wire:click="$refresh">
                                    <i class="ti ti-search f-s-12"></i>
                                </button>

                                <!-- Exportar -->
                                <a href="#"
                                   wire:click.prevent="export"
                                   class="btn btn-sm btn-primary flex-shrink-0 align-self-end">
                                    <i class="ti ti-file-spreadsheet f-s-12"></i> Exportar
                                </a>

                                <!-- Regresar -->
                                <a href="{{ route('payments.index') }}"
                                   class="btn btn-sm btn-primary flex-shrink-0 align-self-end">
                                    <i class="ti ti-arrow-back-up f-s-12"></i> Regresar
                                </a>

                                <button class="btn btn-sm btn-primary flex-shrink-0 align-self-end" id="down" title="Bajar">
                                    <i class="fa-solid fa-angle-down"></i>
                                </button>

                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th rowspan="2">Item</th>
                                <th rowspan="2">Cod</th>
                                <th rowspan="2">Placa</th>
                                <th colspan="3">Deuda del mes anterior</th>
                                <th colspan="6">Pagos</th>
                            </tr>
                            <tr>

                                <th>Deuda</th>
                                <th>Exonerado</th>
                                <th>P.Deuda</th>

                                <th>{{ str_pad($month,2,'0',STR_PAD_LEFT) }}/{{ $year }}</th>
                                <th>Lab.</th>
                                <th>DT</th>
                                <th>DNT</th>
                                <th>Condición</th>
                                <th>T.Deuda</th>
                            </tr>
                            </thead>

                            <tbody>
                            @forelse($rows as $vid => $r)
                                @php
                                    $cond = strtoupper($r['condition'] ?? '');
                                    $condClass = 'cond-badge ';
                                    if (str_starts_with($cond,'EX')) { $condClass .= 'cond-EX'; }
                                    elseif ($cond === 'GN') { $condClass .= 'cond-GN'; }
                                    elseif ($cond === 'DT') { $condClass .= 'cond-DT'; }
                                @endphp
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $r['order'] ?: '-' }}</td>
                                    <td class="green_modules">
                                        <a href="{{ route('settings.vehicles.index', ['search' => $r['plate'], 'filter' => 'plate']) }}" target="_blank">{{ $r['plate'] }}</a>
                                    </td>

                                    {{-- Deuda anterior (debt_days) --}}
                                    <td>{{ number_format($r['prev_debt'], 2) }}</td>
                                    <td>{{ number_format($r['prev_exonerated'], 2) }}</td>
                                    <td>{{ number_format($r['prev_paid_debt'], 2) }}</td>

                                    {{-- Mes actual --}}
                                    <td class="green_modules">
                                        <a href="{{ route('payments.index', ['q' => $r['plate'], 'filter' => '1', 'from' => $year.'-'.str_pad($month,2,'0',STR_PAD_LEFT).'-01', 'to' => \Carbon\Carbon::create($year,$month,1)->endOfMonth()->format('Y-m-d')]) }}" target="_blank">{{ number_format($r['month_amount'], 2) }}</a>
                                    </td>
                                    <td>{{ $laborableDays }}</td>
                                    <td>{{ $r['dt_days'] }}</td>
                                    <td>{{ $r['dnt_days'] }}</td>
                                    <td><span class="{{ $condClass }}">{{ $cond ?: '-' }}</span></td>

                                    @php $tdebt = (float)$r['tdebt']; @endphp
                                    <td>
                                        <span @class([
                                            'title-modules' => $tdebt > 0 && !str_starts_with($cond,'EX'),
                                            'text-success' => $tdebt == 0
                                        ])>
                                            {{ number_format($tdebt, 2) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center">
                                        No hay datos para el mes seleccionado.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="bg-primary fw-semibold">
                            <tr>
                                <td></td>
                                <td><b>TOTAL</b></td>
                                <td></td>

                                <td>{{ number_format($sumPrevDebt, 2) }}</td>
                                <td>{{ number_format($sumPrevExonerated, 2) }}</td>
                                <td>{{ number_format($sumPrevPaidDebt, 2) }}</td>

                                <td>{{ number_format($sumMonthAmount, 2) }}</td>
                                <td>{{ $laborableDays }}</td>
                                <td>{{ $sumDtDays }}</td>
                                <td>{{ $sumDntDays }}</td>
                                <td></td>
                                <td>{{ number_format($sumTDebt, 2) }}</td>
                            </tr>
                            <tr>
                                <td></td>
                                <td><b>TOTAL</b></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td colspan="2">
                                    {{ number_format($sumMonthAmount + $sumPrevPaidDebt, 2) }}
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
