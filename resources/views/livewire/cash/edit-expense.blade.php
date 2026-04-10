<div class="container-fluid">

    {{-- Header --}}
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">EGRESOS : ACTUALIZAR</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-cash f-s-16"></i>
                    <a href="{{ route('cash.expenses') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Egresos</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <span class="f-s-14">Editar</span>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">

                    {{-- Errores de validación --}}
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <strong>Revisa los siguientes errores:</strong>
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

                        {{-- Tipo de egreso --}}
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">Tipo de egreso (*)</label>
                                <select class="form-select form-select-sm @error('expenseKind') is-invalid @enderror"
                                        wire:model.live="expenseKind">
                                    <option value="Otros">Otros</option>
                                    <option value="Fijos">Fijos</option>
                                    <option value="Planilla">Planilla</option>
                                </select>
                                @error('expenseKind') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- A: condicional Fijos/Otros --}}
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">A (*)</label>
                                @if($expenseKind === 'Fijos')
                                    <select class="form-select form-select-sm @error('concept_id') is-invalid @enderror"
                                            wire:model.live="concept_id">
                                        <option value="">-- Seleccionar concepto --</option>
                                        @foreach($concepts as $c)
                                            <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('concept_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                @else
                                    <input type="text" class="form-control form-control-sm @error('reason_text') is-invalid @enderror"
                                           placeholder="Ej: Combustible, Servicio, etc."
                                           wire:model.defer="reason_text">
                                    @error('reason_text') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                @endif
                            </div>
                        </div>

                        {{-- Motivo / Detalle --}}
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Motivo / Detalle (*)</label>
                                <input type="text" class="form-control form-control-sm @error('detail') is-invalid @enderror"
                                       placeholder="Descripción breve"
                                       wire:model.defer="detail">
                                @error('detail') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Monto --}}
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">Monto (S/) (*)</label>
                                <input type="number" step="0.01" min="0"
                                       class="form-control form-control-sm @error('total') is-invalid @enderror"
                                       wire:model.defer="total">
                                @error('total') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- T. Comprobante --}}
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">T. Comprobante</label>
                                <input type="text" class="form-control form-control-sm @error('document_type') is-invalid @enderror"
                                       placeholder="Factura, Boleta, etc."
                                       wire:model.defer="document_type">
                                @error('document_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Responsable --}}
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">Responsable</label>
                                <select class="form-select form-select-sm" wire:model.defer="in_charge">
                                    <option value="">-- Seleccionar --</option>
                                    @foreach($users as $u)
                                        <option value="{{ $u }}">{{ $u }}</option>
                                    @endforeach
                                </select>
                                @error('in_charge') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Imagen --}}
                        <div class="w-100"></div>
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label class="form-label">Comprobante (imagen)</label>
                                <input type="file" class="form-control form-control-sm" wire:model="image_file" accept="image/*">
                                @error('image_file') <div class="title-modules">{{ $message }}</div> @enderror

                                <div class="mt-2">
                                    @if ($image_file)
                                        <img src="{{ $image_file->temporaryUrl() }}" alt="Vista previa"
                                             class="img-fluid rounded border" style="max-height:220px;">
                                    @else
                                        @php
                                            $p      = $image_path;
                                            $exists = $p && \Illuminate\Support\Facades\Storage::disk('public')->exists($p);
                                            $url    = $exists ? asset('storage/'.$p) : asset('images/placeholder-income.png');
                                        @endphp
                                        <img src="{{ $url }}" alt="Comprobante"
                                             class="img-fluid rounded border" style="max-height:220px;">
                                    @endif
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary" wire:click="update" wire:loading.attr="disabled">
                            <span wire:loading wire:target="update" class="spinner-border spinner-border-sm"></span>
                            <span wire:loading.remove wire:target="update">Guardar cambios</span>
                        </button>
                        @can('delete', $expense)
                        <button type="button" class="btn btn-sm btn-danger" wire:click="questionDelete({{ $expenseId }})">
                            Eliminar
                        </button>
                        @endcan
                        <a href="{{ route('cash.expenses') }}" class="btn btn-sm btn-secondary">Volver</a>
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- Overlay de carga --}}

</div>
