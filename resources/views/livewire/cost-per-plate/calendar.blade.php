{{-- resources/views/livewire/cost-per-plate/calendar.blade.php --}}
@push('styles')
    <style>
        /* ===== Encabezado oscuro pegajoso (y pie si lo usas después) ===== */
        .tableFixHead thead th{
            position: sticky; top: 0; z-index: 3;
            background-color:#009BDC !important; color:#fff !important;
            vertical-align: middle; text-align:center;
        }
        .tableFixHead tfoot th,
        .tableFixHead tfoot td{
            position: sticky; bottom: 0; z-index: 2;
            background-color:#009BDC !important; color:#fff !important;
            vertical-align: middle;
        }

        /* Zebra suave y ajustes generales */
        .tableFixHead table.table th,
        .tableFixHead table.table td{ white-space: nowrap; vertical-align: middle; }
        tbody tr:nth-child(even) td{ background-color:#f9fafb; }

        /* Domingos en rojo (sobre-escribe zebra) */
        .sunday{ background-color:#ef4444 !important; color:#fff !important; }
        .sunday input{ background-color:#fff; color:#000; } /* input legible en fondo rojo */

        /* Inputs de monto compactos y alineados a la derecha */
        .w-amt{ width:90px; min-width:90px; }

        /* Quitar bordes duros al input dentro de tabla */
        .table input.form-control{
            padding:.2rem .4rem; height: calc(1.5em + .5rem + 2px);
        }
    </style>
@endpush

<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">
                Placa: {{ $plate }} —
                {{ \Carbon\Carbon::create($year, $month, 1)->locale('es')->isoFormat('MMMM YYYY') }}
            </h4>
            <small class="text-muted">Calendario de montos por día</small>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Configuración</span></a>
                </li>
                <li class="d-flex"><a href="#" class="f-s-14">Costo por placa</a></li>
                <li class="d-flex active"><a href="#" class="f-s-14">Calendario</a></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <!-- Acciones -->
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body pt-3">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-7">
                            <label class="form-label">Aplicar monto a todos los días</label>
                            <div class="position-relative">
                                <input type="number" class="form-control" placeholder="0.00"
                                       aria-label="Apply" step="0.01" min="0"
                                       wire:model.live="bulk">
                                <i class="ti ti-123 text-dark"></i>
                            </div>
                        </div>
                        <div class="col-md-2 d-grid">
                            <button class="btn btn-primary"
                                    wire:click="fillAll"
                                    wire:loading.attr="disabled"
                                    wire:target="fillAll,saveAll">
                                <i class="ti ti-circle-check-filled f-s-17"></i> Aplicar a todos
                            </button>
                        </div>
                        <div class="col-md-2 d-grid">
                            <button class="btn btn-primary"
                                    wire:click="saveAll"
                                    wire:loading.attr="disabled"
                                    wire:target="fillAll,saveAll">
                                <i class="ti ti-device-floppy f-s-17"></i> Guardar
                            </button>
                        </div>
                        <div class="col-md-1 d-grid">
                            <button class="btn btn-primary" wire:click="goBack">
                                <i class="ti ti-arrow-back-up f-s-17"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mt-2" wire:loading.delay>
                        <span class="text-muted">
                            <span class="spinner-border spinner-border-sm"></span> Procesando…
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendario -->
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0" style="color:#e11d48;">Calendario</h5>
                </div>
                <div class="card-body pb-2">
                    <div class="table-responsive tableFixHead">
                        <table class="table table-sm table-bordered table-striped table-hover align-middle">
                            <thead class="text-center">
                            <tr>
                                @foreach (['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $dow)
                                    <th class="{{ $dow === 'Dom' ? 'sunday' : '' }}">{{ $dow }}</th>
                                @endforeach
                            </tr>
                            </thead>

                            <tbody class="text-center">
                            @foreach ($weeks as $week)
                                <tr>
                                    @foreach ($week as $date)
                                        @php
                                            $isSun = $date ? \Carbon\Carbon::parse($date)->isSunday() : false;
                                        @endphp
                                        <td class="{{ $isSun ? 'sunday' : '' }}">
                                            @if ($date)
                                                @php $day = \Carbon\Carbon::parse($date)->day; @endphp
                                                <div class="fw-semibold mb-1">{{ $day }}</div>
                                                <div class="d-flex align-items-center justify-content-center gap-1">
                                                    <span>S/</span>
                                                    <input type="number" step="0.01" min="0"
                                                           wire:key="day-{{ $date }}"
                                                           wire:model.defer="values.{{ $date }}"
                                                           class="form-control form-control-sm text-end w-amt" />
                                                </div>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
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
