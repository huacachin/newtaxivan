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
    .lightbox-overlay {
        position: fixed; inset: 0; z-index: 9999;
        background: rgba(0,0,0,.85);
        display: flex; align-items: center; justify-content: center;
    }
    .lightbox-img {
        max-width: 90vw; max-height: 85vh;
        border-radius: 6px;
        box-shadow: 0 4px 24px rgba(0,0,0,.5);
    }
    .lightbox-btn {
        position: absolute; top: 50%; transform: translateY(-50%);
        background: rgba(255,255,255,.15); border: none; color: #fff;
        font-size: 28px; padding: 8px 14px; border-radius: 50%;
        cursor: pointer; transition: background .2s;
    }
    .lightbox-btn:hover { background: rgba(255,255,255,.3); }
    .lightbox-prev { left: 16px; }
    .lightbox-next { right: 16px; }
    .lightbox-close {
        position: absolute; top: 16px; right: 20px;
        background: none; border: none; color: #fff;
        font-size: 32px; cursor: pointer; line-height: 1;
    }
    .lightbox-counter {
        position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%);
        color: #fff; font-size: 14px; background: rgba(0,0,0,.5);
        padding: 4px 12px; border-radius: 12px;
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

                          {{-- Exonerado (input) --}}
                          <div class="flex-item flex-item-md">
                              <label class="form-label mb-1">Exonerado (S/)</label>
                              <input type="number" step="0.01"
                                     class="form-control form-control-sm text-end @error('exonerateInput') is-invalid @enderror"
                                     style="background-color: yellow;"
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
                          <div class="flex-item flex-item-lg">
                              <label class="form-label mb-1">Detalle exoneración</label>
                              <input type="text"
                                     class="form-control form-control-sm @error('detailInput') is-invalid @enderror"
                                     style="background-color: yellow;"
                                     wire:model.live.defer="detailInput"
                                     placeholder="Motivo / detalle">
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
                             >
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
                                    <div class="d-flex flex-wrap gap-1"
                                         x-data="{ urls: {{ json_encode(collect($row['images'])->pluck('url')->values()) }} }">
                                        @foreach($row['images'] as $imgIdx => $img)
                                            <img src="{{ $img['url'] }}" alt="Img"
                                                 class="img-thumb-clickable"
                                                 x-on:click="$dispatch('open-lightbox', { images: urls, index: {{ $imgIdx }} })">
                                        @endforeach
                                    </div>
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

    {{-- Lightbox --}}
    <div x-data="{
            open: false,
            images: [],
            current: 0,
            show(imgs, idx) { this.images = imgs; this.current = idx; this.open = true; },
            close() { this.open = false; this.images = []; this.current = 0; },
            prev() { this.current = (this.current - 1 + this.images.length) % this.images.length; },
            next() { this.current = (this.current + 1) % this.images.length; },
         }"
         x-on:open-lightbox.window="show($event.detail.images, $event.detail.index)"
         x-on:keydown.escape.window="close()"
         x-on:keydown.left.window="if(open) prev()"
         x-on:keydown.right.window="if(open) next()"
         x-show="open"
         x-cloak
         class="lightbox-overlay"
         x-on:click.self="close()">

        <template x-if="open && images.length">
            <div>
                <img :src="images[current]" class="lightbox-img" alt="Imagen">
                <button class="lightbox-close" x-on:click="close()" title="Cerrar">&times;</button>
                <template x-if="images.length > 1">
                    <div>
                        <button class="lightbox-btn lightbox-prev" x-on:click.stop="prev()">&#8249;</button>
                        <button class="lightbox-btn lightbox-next" x-on:click.stop="next()">&#8250;</button>
                        <div class="lightbox-counter" x-text="(current + 1) + ' / ' + images.length"></div>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>
