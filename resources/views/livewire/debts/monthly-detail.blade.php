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
        }

        .btn, input,select {
            font-size: 10px !important;
        }

        thead,tfoot{
            font-weight: bold;
        }


        .screen-overlay {
            position: fixed;
            inset: 0;                 /* full viewport */
            display: none;            /* Livewire lo pondrá en flex */
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,.35);
            backdrop-filter: blur(2px);
            z-index: 2000;            /* sobre modals/backdrops de Bootstrap */
            pointer-events: all;      /* bloquea clics */
        }

    </style>
@endpush

<div class="container-fluid">
    {{-- Header --}}
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title text-danger">DEUDA: ACTUALIZAR</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Caja</span></a>
                </li>
                <li class="d-flex"><a href="#" class="f-s-14">Deuda mensual</a></li>
                <li class="d-flex active"><a href="#" class="f-s-14">Detalle</a></li>
            </ul>
        </div>
    </div>

    {{-- Alertas --}}
    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Revisa los siguientes errores:</strong>
            <ul class="mb-0 mt-2 ps-3">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        </div>
    @endif

    {{-- Card: Tabla de detalles --}}
    <div class="card">
      <div class="card-body">
          <form wire:submit.prevent="save">
              {{-- Chips (totales) a la derecha, con wrap si no entran --}}
              <div class="row g-2">
                  <div class="col-12">
                      <div class="d-flex justify-content-end flex-wrap gap-2 py-1">
                          <span class="chip">Exonerado: S/ {{ number_format($sumExonerated,2) }}</span>
                          <span class="chip">Amortizado: S/ {{ number_format($sumAmortized,2) }}</span>
                          <span class="chip">Pendiente: S/ {{ number_format($pending,2) }}</span>
                      </div>
                  </div>
              </div>

              {{-- Form con WRAP (rompe a 2da/3ra fila según espacio) --}}
              <div class="row mt-2">
                  <div class="col-12">
                      <div class="d-flex flex-wrap align-items-end gap-2 py-1">

                          {{-- Placa --}}
                          <div class="flex-item" style="flex:1 1 180px; min-width:160px;">
                              <label class="form-label mb-1">Placa</label>
                              <input type="text" class="form-control form-control-sm"
                                     value="{{ $plate }}" readonly style="background:#eee;">
                          </div>

                          {{-- Fecha --}}
                          <div class="flex-item" style="flex:1 1 160px; min-width:140px;">
                              <label class="form-label mb-1">Fecha</label>
                              <input type="text" class="form-control form-control-sm"
                                     value="{{ $date }}" readonly>
                          </div>

                          {{-- Días (no trabajados) --}}
                          <div class="flex-item" style="flex:1 1 180px; min-width:160px;">
                              <label class="form-label mb-1">Días (no trabajados)</label>
                              <input type="text" class="form-control form-control-sm"
                                     value="{{ $days }}" readonly style="background:#eee;">
                          </div>

                          {{-- Días no trabajados — detalle (más ancho, puede ir a otra fila) --}}
                          <div class="flex-item" style="flex:2 1 420px; min-width:320px; max-width:100%;">
                              <label class="form-label mb-1">
                                  <b class="text-danger">Días no trabajados — detalle</b>
                              </label>
                              <input class="form-control form-control-sm" rows="2" value="{!! $this->daysString !!}" readonly/>
                          </div>

                          {{-- Deuda Total --}}
                          <div class="flex-item" style="flex:1 1 180px; min-width:160px;">
                              <label class="form-label mb-1">Deuda Total (S/)</label>
                              <input type="text" class="form-control form-control-sm text-end"
                                     value="{{ number_format($total,2) }}" readonly style="background:#eee;">
                          </div>

                          {{-- Exonerado (input) --}}
                          <div class="flex-item" style="flex:1 1 180px; min-width:160px;">
                              <label class="form-label mb-1">Exonerado (S/)</label>
                              <input type="number" step="0.01"
                                     class="form-control form-control-sm text-end @error('exonerateInput') is-invalid @enderror"
                                     wire:model.live.debounce.400ms="exonerateInput">
                              @error('exonerateInput')
                              <div class="invalid-feedback d-block">{{ $message }}</div>
                              @enderror
                          </div>

                          {{-- Amortización (oculto legacy) --}}
                          <div class="d-none">
                              <label class="form-label mb-1">Amortización (S/)</label>
                              <input type="number" step="0.01"
                                     class="form-control form-control-sm text-end @error('amortizeInput') is-invalid @enderror"
                                     wire:model.live.debounce.400ms="amortizeInput">
                              @error('amortizeInput')
                              <div class="invalid-feedback d-block">{{ $message }}</div>
                              @enderror
                          </div>

                          {{-- Detalle exoneración --}}
                          <div class="flex-item" style="flex:2 1 300px; min-width:240px;">
                              <label class="form-label mb-1">Detalle exoneración</label>
                              <input type="text"
                                     class="form-control form-control-sm @error('detailInput') is-invalid @enderror"
                                     wire:model.live.defer="detailInput"
                                     placeholder="Motivo / detalle">
                              @error('detailInput')
                              <div class="invalid-feedback d-block">{{ $message }}</div>
                              @enderror
                          </div>

                      </div>
                  </div>
              </div>

          </form>
          <div class="row my-2">
              <div class="col-12">
                  <div class="d-flex flex-nowrap justify-content-end align-items-end gap-2 overflow-auto py-1">

                      <!-- Guardar -->
                      <button class="btn btn-sm btn-primary flex-shrink-0"
                              wire:click="save"
                              wire:loading.attr="disabled">
                          <i class="ti ti-device-floppy f-s-12"></i> Guardar
                      </button>

                      <!-- Regresar -->
                      <a class="btn btn-sm btn-primary flex-shrink-0"
                         href="{{ route('debts.monthly') }}">
                          <i class="ti ti-arrow-left f-s-12"></i> Regresar
                      </a>

                  </div>
              </div>
          </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="bg-primary">
                    <tr>
                        <th>Acción</th>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Detalle</th>
                        <th>Exonerado (S/)</th>
                        <th>Amortización (S/)</th>
                        <th>Usuario</th>
                    </tr>
                    </thead>

                    <tbody>

                    @forelse($details as $row)
                        <tr wire:loading.remove>
                            <td>
                                <button  class="btn btn-sm btn-danger" wire:click="questionDelete({{ $row['id'] }})">
                                    Eliminar
                                </button>
                            </td>
                            <td>{{ $row['id'] }}</td>
                            <td>{{ $row['date'] }}</td>
                            <td>{{ $row['detail'] }}</td>
                            <td>{{ number_format($row['exonerated'], 2) }}</td>
                            <td>{{ number_format($row['amortized'], 2) }}</td>
                            <td>{{ $row['user'] }}</td>
                        </tr>
                    @empty
                        <tr wire:loading.remove>
                            <td colspan="7">Sin detalles aún.</td>
                        </tr>
                    @endforelse
                    </tbody>

                    <tfoot class="bg-primary">
                    <tr>
                        <th colspan="4">Total general:</th>
                        <th>{{ number_format($sumExonerated, 2) }}</th>
                        <th>{{ number_format($sumAmortized, 2) }}</th>
                        <th>Pendiente: S/ {{ number_format($pending, 2) }}</th>
                    </tr>
                    </tfoot>
                </table>
            </div>

        </div>
    </div>

    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="save,questionDelete">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>
</div>
