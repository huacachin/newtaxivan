@push('styles')
    <style>
        /* ===== Encabezado y pie oscuros pegajosos (modelo Payments) ===== */
        .tableFixHead thead,
        .tableFixHead thead th{
            background-color:#009BDC !important;
            color:#fff !important;
        }
        .tableFixHead thead th{
            position: sticky; top: 0; z-index: 2;
            vertical-align: middle;
        }
        .tableFixHead tfoot,
        .tableFixHead tfoot th,
        .tableFixHead tfoot td{
            background-color:#009BDC !important;
            color:#fff !important;
        }

        /* Compacta y no cortar líneas */
        .tableFixHead table.table th,
        .tableFixHead table.table td{
            white-space: nowrap;
        }

        /* Hover suave */
        .tableFixHead tbody tr:hover{ background:#f8fafc; }

        /* Utilidades */
        .text-end{ text-align:right !important; }
        .chip{
            display:inline-block; padding:.25rem .5rem; border-radius:999px;
            background:#f8fafc; border:1px solid #e5e7eb; font-size:.875rem; font-weight:600;
        }
    </style>
@endpush

<div class="container-fluid">
    {{-- Header --}}
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Deuda mensual — Detalle</h4>
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
    @if (session('ok'))
        <div class="alert alert-success py-2 my-2">{{ session('ok') }}</div>
    @endif

    {{-- Card: Resumen + Formulario --}}
    <div class="card mb-2">
        <div class="card-body">
            <form wire:submit.prevent="save">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Placa</label>
                        <input type="text" class="form-control" value="{{ $plate }}" readonly style="background:#eee;">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha</label>
                        <input type="text" class="form-control" value="{{ $date }}" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Días (no trabajados)</label>
                        <input type="text" class="form-control" value="{{ $days }}" readonly style="background:#eee;">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Deuda Total (S/)</label>
                        <input type="text" class="form-control text-end" value="{{ number_format($total,2) }}" readonly style="background:#eee;">
                    </div>

                    <div class="col-12">
                        <label class="form-label"><b style="color:red;">Días no trabajados — detalle</b></label>
                        <div class="form-control" style="min-height:38px; background:#fff;">
                            {!! $this->daysString !!}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Exonerado (S/)</label>
                        <input type="number" step="0.01"
                               class="form-control text-end @error('exonerateInput') is-invalid @enderror"
                               wire:model.live.debounce.400ms="exonerateInput">
                        @error('exonerateInput') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- Oculto (legacy) --}}
                    <div class="col-md-3 d-none">
                        <label class="form-label">Amortización (S/)</label>
                        <input type="number" step="0.01"
                               class="form-control text-end @error('amortizeInput') is-invalid @enderror"
                               wire:model.live.debounce.400ms="amortizeInput">
                        @error('amortizeInput') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Detalle exoneración</label>
                        <input type="text"
                               class="form-control @error('detailInput') is-invalid @enderror"
                               wire:model.live.defer="detailInput"
                               placeholder="Motivo / detalle">
                        @error('detailInput') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- Chips de totales --}}
                    <div class="col-12">
                        <div class="d-flex flex-wrap gap-2 mt-1">
                            <span class="chip">Exonerado: S/ {{ number_format($sumExonerated,2) }}</span>
                            <span class="chip">Amortizado: S/ {{ number_format($sumAmortized,2) }}</span>
                            <span class="chip">Pendiente: S/ {{ number_format($pending,2) }}</span>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Card: Acciones (homologado) --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="row justify-content-end g-2">
                <div class="col-xl-2 col-md-3">
                    <button class="btn btn-primary w-100" wire:click="save" wire:loading.attr="disabled">
                        <i class="ti ti-device-floppy f-s-16"></i> Guardar
                    </button>
                </div>
                <div class="col-xl-2 col-md-3">
                    <a class="btn btn-primary w-100" href="{{ url()->previous() }}">
                        <i class="ti ti-arrow-left f-s-16"></i> Regresar
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Card: Tabla de detalles --}}
    <div class="card">
        <div class="card-body">
            <h6 class="mb-2">Detalles</h6>

            <div class="table-responsive tableFixHead">
                <table class="table table-sm table-bordered table-striped table-hover align-middle">
                    <thead class="text-center">
                    <tr>
                        <th>Acción</th>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th class="text-start">Detalle</th>
                        <th>Exonerado (S/)</th>
                        <th>Amortización (S/)</th>
                        <th>Usuario</th>
                    </tr>
                    </thead>

                    <tbody>
                    {{-- Loading row --}}
                    <tr wire:loading>
                        <td colspan="7" class="p-0">
                            <div class="d-flex justify-content-center align-items-center" style="min-height:60px;">
                                <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
                                <span class="ms-2">Cargando...</span>
                            </div>
                        </td>
                    </tr>

                    @forelse($details as $row)
                        <tr wire:loading.remove class="text-center">
                            <td>
                                {{-- Eliminar --}}
                                <button class="btn btn-sm btn-danger ms-1"
                                        onclick="if(!confirm('¿Eliminar este detalle?')) return false;"
                                        wire:click="deleteDetail({{ $row['id'] }})">
                                    Eliminar
                                </button>
                            </td>
                            <td>{{ $row['id'] }}</td>
                            <td>{{ $row['date'] }}</td>
                            <td class="text-start">{{ $row['detail'] }}</td>
                            <td class="text-end">{{ number_format($row['exonerated'], 2) }}</td>
                            <td class="text-end">{{ number_format($row['amortized'], 2) }}</td>
                            <td>{{ $row['user'] }}</td>
                        </tr>
                    @empty
                        <tr wire:loading.remove>
                            <td colspan="7" class="text-center">Sin detalles aún.</td>
                        </tr>
                    @endforelse
                    </tbody>

                    <tfoot class="text-center fw-semibold">
                    <tr>
                        <th colspan="4" class="text-end">Total general:</th>
                        <th class="text-end">{{ number_format($sumExonerated, 2) }}</th>
                        <th class="text-end">{{ number_format($sumAmortized, 2) }}</th>
                        <th>Pendiente: S/ {{ number_format($pending, 2) }}</th>
                    </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-2" wire:loading.delay>
                <span class="text-muted">
                    <span class="spinner-border spinner-border-sm"></span> Cargando…
                </span>
            </div>
        </div>
    </div>
</div>
