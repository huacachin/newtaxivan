{{-- resources/views/livewire/cost-per-plate/calendar.blade.php --}}
@push('styles')
    <style>


        table {
            border-collapse: collapse; /* opcional */
            width: 100%;
        }

        th,td{
            padding: 1px !important;
            font-size: 10px !important;
            text-align: center !important;
            vertical-align: middle;   /* <-- clave */
            white-space: normal !important;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .btn, input,select {
            font-size: 10px !important;
        }

        /* Domingos en rojo (sobre-escribe zebra) */
        .sunday{ background-color:#ef4444 !important; color:#fff !important; }


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


        <!-- Calendario -->
        <div class="col-xl-12">
            <div class="card">

                <div class="card-body pb-2">
                    <div class="d-flex g-3 justify-content-between align-items-center my-2">
                        <h5>Calendario</h5>
                        <div class="d-flex">
                            <input type="number" class="form-control form-control-sm mg-e-10" placeholder="0.00"
                                   aria-label="Apply" step="0.01" min="0"
                                   wire:model.live="bulk">
                            <button class="btn btn-sm btn-primary w-100 mg-e-10"
                                    wire:click="fillAll"
                                    wire:loading.attr="disabled"
                                    wire:target="fillAll,saveAll">
                                <i class="ti ti-circle-check-filled f-s-12"></i> Aplicar
                            </button>
                            <button class="btn btn-sm btn-primary w-100 mg-e-10"
                                    wire:click="saveAll"
                                    wire:loading.attr="disabled"
                                    wire:target="fillAll,saveAll">
                                <i class="ti ti-device-floppy f-s-12"></i> Guardar
                            </button>
                            <button class="btn btn-sm btn-primary" wire:click="goBack">
                                <i class="ti ti-arrow-back-up f-s-12"></i>
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
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
                                        <td class="{{ $isSun ? 'sunday' : '' }} p-1">
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
                </div>
            </div>
        </div>
    </div>
</div>
