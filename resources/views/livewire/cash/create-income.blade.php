<div class="container-fluid">

    {{-- Header --}}
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">INGRESOS : NUEVO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-cash f-s-16"></i>
                    <a href="{{ route('cash.incomes') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Ingresos</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <span class="f-s-14">Nuevo</span>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">

                    {{-- Errores de validacion --}}
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <strong>Corrige los siguientes errores:</strong>
                            <ul class="mb-0 mt-2 ps-3">
                                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row">

                        {{-- Fecha --}}
                        <div class="col-md-auto col-sm-12">
                            <div class="mb-3">
                                <label class="form-label">Fecha (*)</label>
                                <input type="date" class="form-control form-control-sm @error('date') is-invalid @enderror"
                                       wire:model.defer="date">
                                @error('date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Moneda --}}
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">Moneda (*)</label>
                                <select class="form-select form-select-sm @error('currency') is-invalid @enderror"
                                        wire:model.live="currency">
                                    <option value="Soles">Soles</option>
                                    <option value="Dolares">Dolares</option>
                                </select>
                                @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Monto --}}
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">Monto (*)</label>
                                <input type="number" step="0.01" min="0.01"
                                       class="form-control form-control-sm @error('amount_input') is-invalid @enderror"
                                       placeholder="0.00"
                                       wire:model.live="amount_input">
                                @error('amount_input') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                @if(!is_null($converted_total))
                                    <small class="text-muted">Total en S/: {{ number_format($converted_total, 2) }}</small>
                                @endif
                            </div>
                        </div>

                        {{-- A --}}
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">A (*)</label>
                                <input type="text" class="form-control form-control-sm @error('reason') is-invalid @enderror"
                                       placeholder="A quien / Area"
                                       wire:model.defer="reason">
                                @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Motivo --}}
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Motivo (*)</label>
                                <input type="text" class="form-control form-control-sm @error('detail') is-invalid @enderror"
                                       placeholder="Detalle"
                                       wire:model.defer="detail">
                                @error('detail') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Imagen --}}
                        <div class="w-100"></div>
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label class="form-label">Comprobante (imagen)</label>
                                <input type="file" class="form-control form-control-sm" wire:model="image_file" accept="image/*">
                                @error('image_file') <div class="title-modules">{{ $message }}</div> @enderror

                                @if ($image_file)
                                    <div class="mt-2">
                                        <img src="{{ $image_file->temporaryUrl() }}" alt="Vista previa"
                                             class="img-fluid rounded border" style="max-height:220px;">
                                    </div>
                                @endif
                                <small class="text-muted">TC usado (MVP): 3.80</small>
                            </div>
                        </div>

                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary" wire:click="save">
                            Guardar
                        </button>
                        <button type="button" class="btn btn-sm btn-warning" wire:click="clear">
                            Limpiar
                        </button>
                        <a href="{{ route('cash.incomes') }}" class="btn btn-sm btn-secondary">Volver</a>
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- Overlay de carga --}}

</div>
