{{-- Modal compartido para editar una plantilla larga antes de insertarla
     en el campo "Motivo" (detail). El campo Motivo en cada vista es un
     unico Select2 con tags+templates; al elegir una plantilla se abre
     este modal para editar el texto antes de aceptar. --}}
<div class="modal fade" id="reasonTemplateModal" tabindex="-1" wire:ignore.self>
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Editar motivo antes de insertar</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <textarea class="form-control" rows="6" wire:model="reasonModalText" placeholder="Motivo..."></textarea>
                <small class="text-muted d-block mt-2">
                    Podés editar libremente el texto antes de aceptar.
                </small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" wire:click="acceptReasonModal">
                    Aceptar e insertar
                </button>
            </div>
        </div>
    </div>
</div>

@pushOnce('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/select/select2.min.css') }}">
    <style>
        /* Select2 visualmente equivalente a form-control-sm */
        .reason-input-wrap .select2-container .select2-selection--single {
            height: 31px;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
        }
        .reason-input-wrap .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 29px;
            padding-left: 12px;
            font-size: 14px;
            color: #212529;
        }
        .reason-input-wrap .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 29px;
        }
        .reason-input-wrap .select2-dropdown .select2-search__field {
            font-size: 13px;
        }
        .reason-input-wrap .select2-results__option {
            font-size: 13px;
            white-space: normal;
            line-height: 1.3;
        }
        .reason-input-wrap .is-invalid + .select2-container .select2-selection--single {
            border-color: #dc3545;
        }
    </style>
@endPushOnce

@pushOnce('scripts')
    <script src="{{ asset('assets/vendor/select/select2.min.js') }}"></script>
    <script>
        (function () {
            function syncHidden($sel, val) {
                var $hidden = $sel.closest('.reason-input-wrap').find('[data-reason-sync]');
                if ($hidden.length) {
                    var input = $hidden[0];
                    input.value = val == null ? '' : val;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            function initReasonInputSelects(root) {
                if (typeof $ === 'undefined' || !$.fn.select2) return;
                var $scope = root ? $(root) : $(document);
                $scope.find('select.reason-input-select').each(function () {
                    var $sel = $(this);
                    if ($sel.data('select2')) return;

                    $sel.select2({
                        tags: true,
                        placeholder: 'Tipear motivo o seleccionar plantilla…',
                        allowClear: true,
                        width: '100%',
                        dropdownParent: $sel.closest('.reason-input-wrap'),
                        createTag: function (params) {
                            var term = $.trim(params.term);
                            return term ? { id: term, text: term, newTag: true } : null;
                        },
                    });

                    // Sync inicial
                    syncHidden($sel, $sel.val());

                    $sel.on('select2:select', function (e) {
                        var data = e.params.data;
                        var val = data.id;
                        if (val === null || val === undefined) return;

                        var isTemplate = !data.newTag &&
                            $sel.find('option[value="' + (window.CSS && CSS.escape ? CSS.escape(val) : val.replace(/"/g, '\\"')) + '"]').data('template') === 1;

                        if (isTemplate) {
                            // Plantilla larga: abrir modal de edicion. NO sync aun;
                            // se sincronizara tras acceptReasonModal via evento.
                            var wireId = $sel.closest('[wire\\:id]').attr('wire:id');
                            if (window.Livewire && wireId) {
                                var c = window.Livewire.find(wireId);
                                if (c) c.call('openReasonModal', val);
                            }
                        } else {
                            // Tag nuevo o seleccion directa: sync inmediato
                            syncHidden($sel, val);
                        }
                    });

                    $sel.on('select2:clear', function () {
                        syncHidden($sel, '');
                    });
                });
            }

            function onReasonDetailUpdated(payload) {
                var detail = (payload && (payload.detail ?? (Array.isArray(payload) ? payload[0]?.detail : null))) || '';
                document.querySelectorAll('select.reason-input-select').forEach(function (sel) {
                    var $sel = $(sel);
                    var escVal = (window.CSS && CSS.escape ? CSS.escape(detail) : detail.replace(/"/g, '\\"'));
                    if (!$sel.find('option[value="' + escVal + '"]').length) {
                        $sel.append(new Option(detail, detail, true, true));
                    }
                    $sel.val(detail).trigger('change.select2');
                    syncHidden($sel, detail);
                });
            }

            document.addEventListener('DOMContentLoaded', function () {
                initReasonInputSelects();
            });
            document.addEventListener('livewire:initialized', function () {
                initReasonInputSelects();
                if (window.Livewire) {
                    if (Livewire.hook) {
                        Livewire.hook('morph.added', function (ref) {
                            if (ref && ref.el) initReasonInputSelects(ref.el);
                        });
                    }
                    if (Livewire.on) {
                        Livewire.on('reason-detail-updated', onReasonDetailUpdated);
                    }
                }
            });
        })();
    </script>
@endPushOnce
