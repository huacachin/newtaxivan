{{-- resources/views/livewire/debt-days/edit-simple.blade.php --}}
<div class="container-fluid">

    @if (session('ok'))
        <div class="alert alert-success py-2 my-2">{{ session('ok') }}</div>
    @endif

    <h4 class="mb-3">DEUDA : ACTUALIZAR</h4>

    <form wire:submit.prevent="save">
        <div class="row g-3">

            <div class="col-md-3">
                <label class="form-label"><b>Placa</b></label>
                <input type="text" class="form-control" value="{{ $plate }}" readonly style="background:#eee;">
            </div>

            <div class="col-md-3">
                <label class="form-label"><b>Fecha</b></label>
                <input type="text" class="form-control" value="{{ $date }}" readonly>
            </div>

            <div class="col-md-3">
                <label class="form-label"><b>Días</b></label>
                <input type="text" class="form-control" value="{{ $days }}" readonly style="background:#eee;">
            </div>

            <div class="col-md-3">
                <label class="form-label"><b>Deuda Total</b></label>
                <input type="text" class="form-control" value="{{ number_format($total,2) }}" readonly style="background:#eee;">
            </div>

            <div class="col-12">
                <label class="form-label"><b style="color:red;">Días (no trabajados)</b></label>
                <div class="form-control" style="min-height:38px; background:#fff;">
                    {!! $this->daysString !!}
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label"><b style="color:red;">Exonerado (S/)</b></label>
                <input type="number" step="0.01"
                       class="form-control @error('exonerateInput') is-invalid @enderror"
                       wire:model.live.debounce.400ms="exonerateInput">
                @error('exonerateInput') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-3 d-none">
                <label class="form-label"><b>Amortización (S/)</b></label>
                <input type="number" step="0.01"
                       class="form-control @error('amortizeInput') is-invalid @enderror"
                       wire:model.live.debounce.400ms="amortizeInput">
                @error('amortizeInput') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label class="form-label"><b>Detalle Exonera</b></label>
                <input type="text"
                       class="form-control @error('detailInput') is-invalid @enderror"
                       wire:model.live.defer="detailInput"
                       placeholder="Motivo / detalle">
                @error('detailInput') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-3">
                <label class="form-label"><b>Pendiente</b></label>
                <input type="text" class="form-control" value="{{ number_format($pending,2) }}" readonly style="background:#eee;">
            </div>

            <div class="col-12">
                <hr>
                <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">
                    Guardar
                </button>
                <a class="btn btn-outline-secondary" href="{{ url()->previous() }}">Regresar</a>
            </div>
        </div>
    </form>

    <hr class="my-4">

    <h5>Detalles</h5>
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-striped align-middle">
            <thead>
            <tr class="table-light text-center">
                <th>ID</th>
                <th>Fecha</th>
                <th>Detalle</th>
                <th>Monto Exonerado</th>
                <th>Amortización</th>
                <th>Usuario</th>
                <th>Opciones</th>
            </tr>
            </thead>

            <tbody>
            <tr wire:loading.class="opacity-50">
                <td colspan="7" class="p-0">
                    <div wire:loading.flex class="justify-content-center align-items-center" style="min-height:60px;">
                        <div class="spinner-border" role="status" style="width:1.75rem;height:1.75rem;">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                    </div>
                </td>
            </tr>

            @forelse($details as $row)
                <tr class="text-center">
                    <td>{{ $row['id'] }}</td>
                    <td>{{ $row['date'] }}</td>
                    <td class="text-start">{{ $row['detail'] }}</td>
                    <td>{{ $row['exonerated'] }}</td>
                    <td>{{ $row['amortized'] }}</td>
                    <td>{{ $row['user'] }}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-danger"
                                onclick="if(!confirm('¿Eliminar este detalle?')) return false;"
                                wire:click="deleteDetail({{ $row['id'] }})">
                            Eliminar
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center">Sin detalles aún.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
