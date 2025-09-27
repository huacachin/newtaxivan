@push('styles')
    <style>
        /* ===== Tabla estilo Daily ===== */
        .tableFixHead thead th {
            position: sticky; top: 0; z-index: 3;
            background-color: #009BDC !important;
            color: #fff !important;
        }
        .tableFixHead tfoot th,
        .tableFixHead tfoot td {
            background-color: #009BDC !important;
            color: #fff !important;
            position: sticky; bottom: 0; z-index: 2; /* pie fijo si hay overflow */
        }

        /* Variables para anchos mínimos de columnas sticky */
        :root {
            --w-item: 64px;    /* ajusta a gusto */
            --w-plate: 120px;  /* ajusta a gusto */
        }

        /* Sticky cols (solo Item y Placa) */
        .tableFixHead .sticky-col   { position: sticky; left: 0;               z-index: 4; width: var(--w-item); }
        .tableFixHead .sticky-col-2 { position: sticky; left: var(--w-item);   z-index: 4; width: var(--w-plate); }

        /* Fondo BLANCO en sticky cells del cuerpo para que no se “transparente” */
        .tableFixHead tbody td.sticky-col,
        .tableFixHead tbody td.sticky-col-2 {
            background-color: #fff !important;
            background-clip: padding-box;
            box-shadow: 1px 0 0 rgba(0,0,0,.06) inset;
            white-space: nowrap; /* que no se rompa placa */
        }

        /* Encabezado mantiene el fondo oscuro en celdas sticky */
        .tableFixHead thead th.sticky-col,
        .tableFixHead thead th.sticky-col-2 {
            background-color: #009BDC !important;
            color: #fff !important;
        }

        /* Badges de condición (mismo esquema del Daily) */
        .cond-badge {
            display:inline-block; padding:.15rem .4rem; border-radius:.35rem;
            font-size:.75rem; font-weight:600; letter-spacing:.3px;
        }
        .cond-EX  { background:#e2e8f0; color:#334155; } /* EX/EX5 gris */
        .cond-GN  { background:#fef3c7; color:#92400e; } /* GN ámbar */
        .cond-DT  { background:#dcfce7; color:#166534; } /* DT verde */
    </style>
@endpush

<div class="container-fluid">
    <!-- Header -->
    <div class="row align-items-center mb-3">
        <div class="col-sm-6">
            <h4 class="main-title mb-0">Pagos</h4>
            <small class="text-muted">Mensual</small>
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
        <!-- Filtros -->
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body pt-3">
                    <div class="row g-3 align-items-end">
                        <div class="col-xl-3 col-md-4">
                            <label class="form-label">Mes</label>
                            <select class="form-select" wire:model.live="month">
                                @foreach($months as $mVal => $mName)
                                    <option value="{{ $mVal }}">{{ $mName }}</option>
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
                            <label class="form-label">Condición</label>
                            <select class="form-select" wire:model.live="cond">
                                <option value="">Todos</option>
                                <option value="EX">EX</option>
                                <option value="GN">GN</option>
                                <option value="DT">DT</option>
                                <option value="EX5">EX5</option>
                            </select>
                        </div>

                        <div class="col-xl-3 col-md-12 d-flex gap-2 justify-content-xl-end">
                            <a href="#" wire:click="export" class="btn btn-primary">
                                <i class="ti ti-file-spreadsheet f-s-16"></i> Exportar
                            </a>
                            <a href="{{ route('payments.index') }}" class="btn btn-primary">
                                <i class="ti ti-arrow-back-up"></i> Regresar
                            </a>
                        </div>
                    </div>

                    <div class="mt-2" wire:loading.delay>
                        <span class="text-muted">
                            <span class="spinner-border spinner-border-sm"></span> Cargando…
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    @php
                        $monthName = \Illuminate\Support\Str::upper(\Carbon\Carbon::create($year, $month, 1)->translatedFormat('F'));
                    @endphp
                    <h5 class="mb-3" style="color:#e11d48;">
                        REPORTE MENSUAL DE PAGO – {{ $monthName }} {{ $year }}
                    </h5>

                    <div class="table-responsive tableFixHead">
                        <table class="table table-sm table-bordered table-striped table-hover align-middle">
                            <thead class="text-center">
                            <tr>
                                <th class="sticky-col">Item</th>
                                <th>Cod</th>
                                <th class="sticky-col-2">Placa</th>
                                <th colspan="3">Deuda del mes anterior</th>
                                <th colspan="6">Pagos</th>
                            </tr>
                            <tr>
                                <th class="sticky-col"></th>
                                <th> </th>
                                <th class="sticky-col-2"></th>

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

                            <tbody class="text-center">
                            @forelse($rows as $vid => $r)
                                @php
                                    $cond = strtoupper($r['condition'] ?? '');
                                    $condClass = 'cond-badge ';
                                    if (str_starts_with($cond,'EX')) { $condClass .= 'cond-EX'; }
                                    elseif ($cond === 'GN') { $condClass .= 'cond-GN'; }
                                    elseif ($cond === 'DT') { $condClass .= 'cond-DT'; }
                                @endphp
                                <tr>
                                    <td class="sticky-col">{{ $loop->iteration }}</td>
                                    <td>{{ $r['order'] ?: '-' }}</td>
                                    <td class="sticky-col-2 text-start fw-semibold">{{ $r['plate'] }}</td>

                                    {{-- Deuda anterior (debt_days) --}}
                                    <td class="text-end">{{ number_format($r['prev_debt'], 2) }}</td>
                                    <td class="text-end">{{ number_format($r['prev_exonerated'], 2) }}</td>
                                    <td class="text-end">{{ number_format($r['prev_paid_debt'], 2) }}</td>

                                    {{-- Mes actual --}}
                                    <td class="text-end">{{ number_format($r['month_amount'], 2) }}</td>
                                    <td>{{ $laborableDays }}</td>
                                    <td>{{ $r['dt_days'] }}</td>
                                    <td>{{ $r['dnt_days'] }}</td>
                                    <td><span class="{{ $condClass }}">{{ $cond ?: '-' }}</span></td>

                                    @php $tdebt = (float)$r['tdebt']; @endphp
                                    <td class="text-end">
                                        <span @class([
                                            'text-danger' => $tdebt > 0 && !str_starts_with($cond,'EX'),
                                            'text-success' => $tdebt == 0
                                        ])>
                                            {{ number_format($tdebt, 2) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center py-4 text-muted">
                                        No hay datos para el mes seleccionado.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="text-center fw-semibold">
                            <tr>
                                <td class="sticky-col"></td>
                                <td><b>TOTAL</b></td>
                                <td class="sticky-col-2"></td>

                                <td class="text-end">{{ number_format($sumPrevDebt, 2) }}</td>
                                <td class="text-end">{{ number_format($sumPrevExonerated, 2) }}</td>
                                <td class="text-end">{{ number_format($sumPrevPaidDebt, 2) }}</td>

                                <td class="text-end">{{ number_format($sumMonthAmount, 2) }}</td>
                                <td>{{ $laborableDays }}</td>
                                <td>{{ $sumDtDays }}</td>
                                <td>{{ $sumDntDays }}</td>
                                <td></td>
                                <td class="text-end">{{ number_format($sumTDebt, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="sticky-col"></td>
                                <td><b>TOTAL</b></td>
                                <td class="sticky-col-2"></td>
                                <td></td>
                                <td></td>
                                <td class="text-center" colspan="2">
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

                    <div class="mt-2" wire:loading.delay>
                        <span class="text-muted">
                            <span class="spinner-border spinner-border-sm"></span> Actualizando…
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        // espacio para JS específico si luego lo necesitas
    </script>
@endpush
