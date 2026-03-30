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
                                                    <input type="text" inputmode="decimal"
                                                           onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode == 46"
                                                           id="cost-{{ $date }}"
                                                           value="{{ $values[$date] ?? 0 }}"
                                                           class="form-control form-control-sm text-end w-amt"
                                                           @if($isSun) readonly style="opacity:.6" @else onchange="@this.confirmChange('{{ $date }}', this.value)" @endif />
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
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
window.addEventListener('confirmCostChange', function (event) {
    var data = event.detail[0];
    var date = data.date;
    var value = data.value;

    Swal.fire({
        title: 'Cambio de costo',
        html: '¿Cómo desea aplicar el cambio de <b style="color:red">' + value + '</b>?',
        icon: 'question',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: 'Solo este día',
        denyButtonText: 'De aquí en adelante',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#3085d6',
        denyButtonColor: '#28a745',
        cancelButtonColor: '#d33',
    }).then(function (result) {
        if (result.isConfirmed) {
            Livewire.dispatch('applySingleFromJs', [date, value]);
        } else if (result.isDenied) {
            Livewire.dispatch('applyForwardFromJs', [date, value]);
        } else {
            // Cancelar: restaurar valor original
            var input = document.getElementById('cost-' + date);
            if (input) input.value = input.defaultValue;
        }
    });
});
</script>
@endpush
