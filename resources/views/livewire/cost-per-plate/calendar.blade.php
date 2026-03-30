{{-- resources/views/livewire/cost-per-plate/calendar.blade.php --}}
@push('styles')
    <style>
        .sunday{ background-color:#ef4444 !important; color:#fff !important; }
        .w-amt { width: 70px !important; display: inline-block; }
    </style>
@endpush

<div class="container-fluid">
    <div class="row">
        <div class="col-12 text-center my-3">
            <h5 class="fw-bold">PLACA : {{ $plate }} - {{ $order ?? '' }}</h5>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body pb-2">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="bg-primary">
                            <tr>
                                @foreach (['L','M','M','J','V','S','D'] as $dow)
                                    <th class="text-center {{ $loop->last ? 'sunday' : '' }}">{{ $dow }}</th>
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
                                        <td class="{{ $isSun ? 'sunday' : '' }} p-2">
                                            @if ($date)
                                                @php $day = \Carbon\Carbon::parse($date)->day; @endphp
                                                <div class="d-flex align-items-center justify-content-center gap-1">
                                                    <span class="fw-semibold">{{ $day }}</span>
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

                    <div class="d-flex gap-2 justify-content-center my-2">
                        <button class="btn btn-sm btn-primary" wire:click="goBack">
                            <i class="ti ti-arrow-back-up f-s-12"></i> Regresar
                        </button>
                        <button class="btn btn-sm btn-primary" wire:click="saveAll">
                            <i class="ti ti-device-floppy f-s-12"></i> Guardar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
