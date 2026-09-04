@push('styles')
<style>
    .dropzone-area {
        border: 2px dashed #b0bec5;
        border-radius: 6px;
        padding: 8px;
        text-align: center;
        cursor: pointer;
        transition: border-color .2s, background .2s;
        background: #fafafa;
    }
    .dropzone-area:hover,
    .dropzone-active {
        border-color: #2874A6;
        background: #e3f2fd;
    }
    .dropzone-label {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
        cursor: pointer;
        color: #607d8b;
    }
    .img-thumb-clickable {
        height: 40px; width: 40px; object-fit: cover;
        cursor: pointer; border-radius: 4px;
        transition: opacity .2s;
    }
    .img-thumb-clickable:hover { opacity: .8; }
</style>
@endpush

<div class="container-fluid">
    {{-- Header --}}
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">DEUDA: ACTUALIZAR</h4>
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
                          <span class="chip" style="color:red;font-weight:bold;">Pendiente: S/ {{ number_format($pending,2) }}</span>
                      </div>
                  </div>
              </div>

              {{-- Form con WRAP (rompe a 2da/3ra fila según espacio) --}}
              <div class="row mt-2">
                  <div class="col-12">
                      <div class="d-flex flex-wrap align-items-end gap-2 py-1">

                          {{-- Placa --}}
                          <div class="flex-item flex-item-md">
                              <label class="form-label mb-1">Placa</label>
                              <input type="text" class="form-control form-control-sm input-readonly"
                                     value="{{ $plate }}" readonly>
                          </div>

                          {{-- Fecha --}}
                          <div class="flex-item flex-item-sm">
                              <label class="form-label mb-1">Fecha</label>
                              <input type="text" class="form-control form-control-sm"
                                     value="{{ $date }}" readonly>
                          </div>

                          {{-- Días (no trabajados) --}}
                          <div class="flex-item flex-item-md">
                              <label class="form-label mb-1">Días (no trabajados)</label>
                              <input type="text" class="form-control form-control-sm input-readonly"
                                     value="{{ $days }}" readonly>
                          </div>

                          {{-- Días no trabajados — detalle (más ancho, puede ir a otra fila) --}}
                          <div class="flex-item flex-item-xl">
                              <label class="form-label mb-1">
                                  <b class="title-modules">Días no trabajados — detalle</b>
                              </label>
                              <input class="form-control form-control-sm" rows="2" value="{!! $this->daysString !!}" readonly/>
                          </div>

                          {{-- Deuda Total --}}
                          <div class="flex-item flex-item-md">
                              <label class="form-label mb-1">Deuda Total (S/)</label>
                              <input type="text" class="form-control form-control-sm text-end input-readonly"
                                     style="color:red;font-weight:bold;"
                                     value="{{ number_format($total,2) }}" readonly>
                          </div>

                          {{-- Exonerado (input) — chips con historial (LRU local + frecuentes del servidor) --}}
                          <div class="flex-item flex-item-md">
                              <label class="form-label mb-1">Exonerado (S/)</label>
                              <div x-data="numericChips({
                                  storageKey: 'monthly-debt.exonerate',
                                  server: () => $wire.exonerateSuggestions,
                                  decimals: 2
                              })">
                                  <input type="text" inputmode="decimal"
                                         x-ref="input"
                                         name="debt_exonerate" autocomplete="on"
                                         class="form-control form-control-sm text-end @error('exonerateInput') is-invalid @enderror"
                                         style="background-color: yellow;"
                                         wire:model="exonerateInput">
                                  <div class="num-chips" x-show="suggestions.length" x-cloak>
                                      <template x-for="(s, i) in suggestions" :key="s.value">
                                          <button type="button" :class="badgeClass(s.source)"
                                                  :title="`${s.hint} (Alt+${i+1})`"
                                                  @click="pick(s.value)" x-text="formatted(s.value)"></button>
                                      </template>
                                  </div>
                              </div>
                              @error('exonerateInput')
                              <div class="invalid-feedback d-block">{{ $message }}</div>
                              @enderror
                          </div>

                          {{-- Amortización (oculto legacy) --}}
                          <div class="d-none">
                              <label class="form-label mb-1">Amortización (S/)</label>
                              <input type="text" inputmode="decimal"
                                     name="debt_amortize" autocomplete="on"
                                     class="form-control form-control-sm text-end @error('amortizeInput') is-invalid @enderror"
                                     wire:model="amortizeInput">
                              @error('amortizeInput')
                              <div class="invalid-feedback d-block">{{ $message }}</div>
                              @enderror
                          </div>

                          {{-- Detalle exoneración — historial visible (LRU local + detalles ya usados) --}}
                          <div class="flex-item flex-item-lg">
                              <label class="form-label mb-1">Detalle exoneración</label>
                              <div class="txt-suggest" x-data="textSuggest({
                                  storageKey: 'monthly-debt.detail',
                                  server: () => $wire.detailSuggestions,
                                  max: 8
                              })">
                                  <input type="text"
                                         x-ref="input"
                                         class="form-control form-control-sm @error('detailInput') is-invalid @enderror"
                                         style="background-color: yellow;"
                                         autocomplete="off"
                                         wire:model="detailInput"
                                         placeholder="Motivo / detalle">
                                  <ul class="txt-suggest__list" x-ref="list" role="listbox"
                                      x-show="open && items.length" x-cloak>
                                      <template x-for="(it, i) in items" :key="it.value">
                                          <li :class="itemClass(it, i)" role="option" :title="it.hint"
                                              @mousedown.prevent @click="pick(it.value)"
                                              @mouseenter="active = i" x-text="it.value"></li>
                                      </template>
                                  </ul>
                              </div>
                              @error('detailInput')
                              <div class="invalid-feedback d-block">{{ $message }}</div>
                              @enderror
                          </div>

                          {{-- Imágenes (drag & drop) --}}
                          <div class="flex-item" style="min-width:220px;"
                               x-data="{ dragging: false }">
                              <label class="form-label mb-1">
                                  Imágenes
                                  @if(!empty($image_files))
                                      <span class="badge bg-primary ms-1">{{ count($image_files) }}</span>
                                      <a href="#" class="f-s-12 ms-1 text-danger" wire:click.prevent="$set('image_files', [])">limpiar</a>
                                  @endif
                              </label>
                              <div class="dropzone-area"
                                   x-on:dragover.prevent="dragging = true"
                                   x-on:dragleave.prevent="dragging = false"
                                   x-on:drop.prevent="dragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }))"
                                   :class="{ 'dropzone-active': dragging }">
                                  <input type="file" multiple accept="image/*"
                                         wire:model="new_images"
                                         x-ref="fileInput"
                                         class="d-none">
                                  <div class="dropzone-label mb-0"
                                       x-on:click.prevent="$refs.fileInput.click()">
                                      <i class="ti ti-cloud-upload f-s-18"></i>
                                      <span class="f-s-12">Arrastra o haz clic (varias)</span>
                                  </div>
                              </div>
                              @error('new_images.*') <span class="title-modules f-s-12">{{ $message }}</span> @enderror

                              {{-- Previews acumulados --}}
                              @if(!empty($image_files))
                                  <div class="d-flex flex-wrap gap-1 mt-1">
                                      @foreach($image_files as $idx => $file)
                                          @if($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile && $file->isPreviewable())
                                              <div class="position-relative d-inline-block">
                                                  <img src="{{ $file->temporaryUrl() }}" alt="Preview"
                                                       class="img-thumb-clickable"
                                                       onclick="window.open(this.src, '_blank')">
                                                  <button type="button"
                                                          wire:click="removeImage({{ $idx }})"
                                                          class="position-absolute top-0 end-0 border-0 bg-danger text-white rounded-circle d-flex align-items-center justify-content-center"
                                                          style="width:16px;height:16px;font-size:10px;line-height:1;padding:0;transform:translate(4px,-4px);">&times;</button>
                                              </div>
                                          @endif
                                      @endforeach
                                  </div>
                              @endif
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
                          <span wire:loading wire:target="save" class="spinner-border spinner-border-sm"></span>
                          <span wire:loading.remove wire:target="save"><i class="ti ti-device-floppy f-s-12"></i> Guardar</span>
                      </button>

                      <!-- Regresar -->
                      <a class="btn btn-sm btn-primary flex-shrink-0"
                         href="{{ route('debts.monthly') }}">
                          <i class="ti ti-arrow-left f-s-12"></i> Regresar
                      </a>

                  </div>
              </div>
          </div>

            {{-- ════════ MOBILE: cards de detalles de deuda ════════ --}}
            <div class="d-md-none list-cards">
                @forelse($details as $row)
                    @php
                        $exonerated = (float)($row['exonerated'] ?? 0);
                        $amortized  = (float)($row['amortized'] ?? 0);
                        $hasImages  = !empty($row['images']);
                    @endphp
                    <article class="list-card">
                        <header class="list-card__head">
                            <div class="list-card__title-wrap">
                                <span class="list-card__index">{{ $loop->iteration }}</span>
                                <span class="list-card__title">{{ $row['date'] }}</span>
                            </div>
                            <button type="button" class="list-card__edit" style="background:rgba(220,38,38,0.10); color:#991b1b;"
                                    wire:click="questionDelete({{ $row['id'] }})" aria-label="Eliminar">
                                <i class="ti ti-trash"></i>
                            </button>
                        </header>

                        @if(!empty($row['detail']))
                            <div class="list-card__subtitle">{{ $row['detail'] }}</div>
                        @endif

                        <ul class="list-card__meta">
                            @if($exonerated > 0)
                                <li>
                                    <span class="list-card__meta-lbl"><i class="ti ti-discount"></i> Exonerado</span>
                                    <span class="list-card__meta-val">S/ {{ number_format($exonerated, 2) }}</span>
                                </li>
                            @endif
                            @if($amortized > 0)
                                <li>
                                    <span class="list-card__meta-lbl"><i class="ti ti-arrow-down"></i> Amortizado</span>
                                    <span class="list-card__meta-val">S/ {{ number_format($amortized, 2) }}</span>
                                </li>
                            @endif
                            @if(!empty($row['user']))
                                <li>
                                    <span class="list-card__meta-lbl"><i class="ti ti-user"></i> Usuario</span>
                                    <span class="list-card__meta-val">{{ $row['user'] }}</span>
                                </li>
                            @endif
                            @if($hasImages)
                                <li class="d-flex justify-content-end pt-1">
                                    <span x-data="{ urls: {{ json_encode(collect($row['images'])->pluck('url')->values()) }} }"
                                          x-on:click="$dispatch('open-lightbox', { images: urls, index: 0 })"
                                          class="btn btn-sm btn-outline-dark" style="cursor:pointer;">
                                        <i class="fa-solid fa-camera"></i>
                                        Ver imágenes
                                        @if(count($row['images']) > 1)
                                            <span class="badge bg-primary ms-1">{{ count($row['images']) }}</span>
                                        @endif
                                    </span>
                                </li>
                            @endif
                        </ul>
                    </article>
                @empty
                    <div class="list-cards__empty">Sin detalles aún</div>
                @endforelse
            </div>

            {{-- ════════ DESKTOP: tabla original ════════ --}}
            <div class="table-responsive d-none d-md-block">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="bg-primary">
                    <tr>
                        <th></th>
                        <th>Nº</th>
                        <th>Fecha</th>
                        <th>Detalle</th>
                        <th>Exonerado (S/)</th>
                        <th>Amortización (S/)</th>
                        <th>Usuario</th>
                        <th>Imágenes</th>
                    </tr>
                    </thead>

                    <tbody>

                    @forelse($details as $row)
                        <tr>
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
                            <td>
                                @if(!empty($row['images']))
                                    <span x-data="{ urls: {{ json_encode(collect($row['images'])->pluck('url')->values()) }} }"
                                          style="cursor:pointer;position:relative;display:inline-block;"
                                          x-on:click="$dispatch('open-lightbox', { images: urls, index: 0 })">
                                        <i class="fa-solid fa-camera f-s-18 text-dark"></i>
                                        @if(count($row['images']) > 1)
                                            <span class="badge bg-primary" style="position:absolute;top:-8px;right:-12px;font-size:9px;">{{ count($row['images']) }}</span>
                                        @endif
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">Sin detalles aún.</td>
                        </tr>
                    @endforelse
                    </tbody>

                    <tfoot class="bg-primary">
                    <tr>
                        <th colspan="4">Total general:</th>
                        <th>{{ number_format($sumExonerated, 2) }}</th>
                        <th>{{ number_format($sumAmortized, 2) }}</th>
                        <th>Pendiente: S/ {{ number_format($pending, 2) }}</th>
                        <th></th>
                    </tr>
                    </tfoot>
                </table>
            </div>

        </div>
    </div>

    @include('partials.image-lightbox')
</div>
