{{-- resources/views/livewire/reports/departures-monthly-by-stop.blade.php --}}
@push('styles')
    <style>
        .hide-label { position:absolute; left:-9999px; }
    </style>
@endpush

@php
    /** @var array $months, $years */
    /** @var int $month, $year, $daysInMonth */
    $monthName = $months[$month] ?? '';
@endphp

<div class="container-fluid">
    <!-- Header start -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">RMP V.T</h4>
            <div class="text-muted f-s-12">{{ $monthName }} {{ $year }}</div>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-door-exit f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Salidas</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">RMP V.T</a>
                </li>
            </ul>
        </div>
    </div>
    <!-- Header end -->

    <div class="row table-section">

        <!-- Filtros start -->
        <div class="col-12">
            <div class="card">
                <div class="card-body pt-3 pb-3">
                    <div class="row g-3">
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
                            <label class="form-label d-block invisible">.</label>
                            <a wire:click="export"
                               class="btn btn-primary w-100">
                                <i class="ti ti-file-analytics f-s-16"></i> Exportar
                            </a>
                        </div>
                        <div class="col-xl-3 col-md-4">
                            <label class="form-label d-block invisible">.</label>
                            <a href="{{ route('departures.index') }}" class="btn btn-secondary w-100">
                                <i class="ti ti-rotate-2 f-s-16"></i> Regresar
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Filtros end -->

        <!-- Tabla RMP V.T start -->
        <div class="col-xl-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Reporte mensual por paradero (V.T.)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">

                        <table class="table table-sm table-bordered table-striped table-hover">
                            <thead class="table-primary text-center">
                            <tr>
                                <th class="ta-center">CONTROLADOR</th>
                                <th class="ta-center">PARADERO</th>
                                <th class="ta-center">TIPO</th>
                                @for($d=1; $d<=$daysInMonth; $d++)
                                    @php
                                        $dateStr = \Illuminate\Support\Carbon::create($year, $month, $d);
                                    @endphp
                                    <th class="ta-center {{ $dateStr->isSunday() ? 'bg-danger text-white' : '' }}">
                                        {{ $d }}
                                    </th>
                                @endfor
                                <th class="ta-center">TOTAL</th>
                            </tr>
                            </thead>

                            <tbody>
                            @forelse($rows as $r)
                                <tr class="text-center">
                                    <td class="text-start">{{ $r['controller'] }}</td>
                                    <td class="text-start">{{ $r['stop'] }}</td>
                                    <td>{{ $r['type'] === 'Emp' ? 'Emp.' : 'Apoyo.' }}</td>

                                    @for($d=1; $d<=$daysInMonth; $d++)
                                        <td>{{ $r['days'][$d] ?? 0 }}</td>
                                    @endfor

                                    <td class="f-w-600">{{ $r['total'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 3 + $daysInMonth + 1 }}" class="text-center">
                                        No se encontraron resultados
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="table-primary text-center f-w-600">
                            <tr>
                                <td class="text-end p-2" colspan="3">T.E</td>
                                @for($d=1; $d<=$daysInMonth; $d++)
                                    <td class="p-2">{{ $totalsTE[$d] }}</td>
                                @endfor
                                <td class="p-2">{{ $grandTE }}</td>
                            </tr>
                            <tr>
                                <td class="text-end p-2" colspan="3">T.A</td>
                                @for($d=1; $d<=$daysInMonth; $d++)
                                    <td class="p-2">{{ $totalsTA[$d] }}</td>
                                @endfor
                                <td class="p-2">{{ $grandTA }}</td>
                            </tr>
                            <tr>
                                <td class="text-end p-2" colspan="3">V.T</td>
                                @for($d=1; $d<=$daysInMonth; $d++)
                                    <td class="p-2">{{ $totalsVT[$d] }}</td>
                                @endfor
                                <td class="p-2">{{ $grandVT }}</td>
                            </tr>
                            </tfoot>
                        </table>

                    </div>
                </div>
            </div>
        </div>
        <!-- Tabla RMP V.T end -->

    </div>
</div>
