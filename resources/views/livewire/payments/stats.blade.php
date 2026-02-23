{{-- resources/views/livewire/reports/report-stats.blade.php --}}
@push('styles')
    <style>
        .sunday { background-color: #ef4444 !important; color:#fff !important; }
    </style>
@endpush

<div class="container-fluid">
    {{-- Header --}}
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">REPORTE ESTADÍSTICO DE PAGO – {{$this->monthName()}} {{$year}}</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-currency-dollar f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Pagos</span></a>
                </li>
                <li class="d-flex active"><a href="#" class="f-s-14">Estadístico</a></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">

        {{-- Tabla --}}
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header">

                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex flex-nowrap align-items-end gap-2 overflow-auto py-1">

                                <!-- Mes -->
                                <div class="flex-shrink-0" style="min-width: 180px;">
                                    <label class="form-label mb-1">Mes</label>
                                    <select class="form-select form-select-sm" wire:model.live="month">
                                        @foreach($months as $mVal => $mName)
                                            <option value="{{ $mVal }}">{{ $mName }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Año -->
                                <div class="flex-shrink-0" style="min-width: 140px;">
                                    <label class="form-label mb-1">Año</label>
                                    <select class="form-select form-select-sm" wire:model.live="year">
                                        @foreach($years as $y)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Exportar -->
                                <a href="#"
                                   wire:click.prevent="export"
                                   class="btn btn-sm btn-primary flex-shrink-0 align-self-end">
                                    <i class="ti ti-file-analytics"></i> Exportar
                                </a>

                                <!-- Regresar -->
                                <a href="{{ route('payments.index') }}"
                                   class="btn btn-sm btn-primary flex-shrink-0 align-self-end">
                                    <i class="ti ti-arrow-back-up"></i> Regresar
                                </a>

                            </div>
                        </div>
                    </div>

                </div>
                <div class="card-body pb-2">


                    @php $days = range(1, $daysInMonth); @endphp

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>CONTROL.</th>
                                <th colspan="2">PARADERO</th>
                                @foreach($days as $d)
                                    @php
                                        $date = \Carbon\Carbon::create($year, $month, $d);
                                        $isSun = $date->isSunday();
                                    @endphp
                                    <th class="@if($isSun) sunday @endif">{{ $d }}</th>
                                @endforeach
                                <th>Total</th>
                            </tr>
                            </thead>

                            <tbody>
                            @forelse($rows as $controller => $byHq)
                                @php
                                    $rowspan = max(1, count($byHq) * 3);
                                    $printController = true;
                                @endphp

                                @foreach($byHq as $hqName => $blocks)
                                    {{-- PAGO --}}
                                    <tr>
                                        @if($printController)
                                            <td rowspan="{{ $rowspan }}" class="fw-semibold bg-primary text-white">{{ $controller }}</td>
                                            @php $printController = false; @endphp
                                        @endif

                                        <td rowspan="3" class="fw-semibold bg-primary text-white">{{ $hqName }}</td>
                                        <td class="fw-semibold bg-primary text-white">Pago</td>

                                        @foreach($days as $d)
                                            <td class="num">{{ number_format($blocks['PAGO']['days'][$d] ?? 0, 2) }}</td>
                                        @endforeach
                                        <td class="num fw-semibold">{{ number_format($blocks['PAGO']['total'], 2) }}</td>
                                    </tr>

                                    {{-- RETRASO --}}
                                    <tr>
                                        <td class="fw-semibold bg-primary text-white">Retraso</td>
                                        @foreach($days as $d)
                                            <td class="num">{{ number_format($blocks['RETRASO']['days'][$d] ?? 0, 2) }}</td>
                                        @endforeach
                                        <td class="num fw-semibold">{{ number_format($blocks['RETRASO']['total'], 2) }}</td>
                                    </tr>

                                    {{-- DEUDA --}}
                                    <tr>
                                        <td class="fw-semibold bg-primary text-white">Deuda</td>
                                        @foreach($days as $d)
                                            <td class="num">{{ number_format($blocks['DEUDA']['days'][$d] ?? 0, 2) }}</td>
                                        @endforeach
                                        <td class="num fw-semibold">{{ number_format($blocks['DEUDA']['total'], 2) }}</td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="{{ 3 + $daysInMonth + 1 }}" class="py-4 text-muted">Sin datos para el mes seleccionado.</td>
                                </tr>
                            @endforelse
                            </tbody>

                            <tfoot class="fw-semibold bg-primary">
                            <tr>
                                <td colspan="3">TOTAL GENERAL</td>
                                @foreach($days as $d)
                                    <td class="num">{{ number_format($totalsPerDay[$d] ?? 0, 2) }}</td>
                                @endforeach
                                <td class="num">{{ number_format($grandTotal, 2) }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>

                    <small class="text-muted">
                        * Cómputo por día usando <code>date_register</code> (PAGO/RETRASO/DEUDA). Domingos resaltados para referencia visual.
                    </small>
                </div>
            </div>
        </div>
    </div>
    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="export,month,year">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>
