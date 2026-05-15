{{-- Modal compartido para editar una plantilla de motivo antes de insertarla
     en el campo "detail" (Motivo). Lo incluyen CreateIncome/EditIncome/
     CreateExpense/EditExpense. Cada componente expone:
     - public ?string $reasonModalText
     - acceptReasonModal() : copia el text a detail y cierra el modal
     El partial NO declara estado, solo el markup. --}}
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
        /* Match Bootstrap form-control-sm en input-group */
        .reason-tpl-wrap .select2-container .select2-selection--single {
            height: 31px;
            border: 1px solid #ced4da;
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }
        .reason-tpl-wrap .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 29px;
            padding-left: 8px;
            font-size: 12px;
        }
        .reason-tpl-wrap .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 29px;
        }
    </style>
@endPushOnce

@pushOnce('scripts')
    <script src="{{ asset('assets/vendor/select/select2.min.js') }}"></script>
    <script>
        (function () {
            function initReasonTemplateSelects(root) {
                if (typeof $ === 'undefined' || !$.fn.select2) return;
                var $scope = root ? $(root) : $(document);
                $scope.find('select.reason-template-select').each(function () {
                    var $el = $(this);
                    if ($el.data('select2')) return; // ya inicializado
                    $el.select2({
                        placeholder: 'Buscar plantilla…',
                        allowClear: true,
                        width: '130px',
                        dropdownAutoWidth: true,
                        dropdownParent: $el.closest('.input-group').length ? $el.closest('.input-group') : $(document.body),
                    });
                    $el.on('select2:select', function (e) {
                        var val = e.params.data.id;
                        if (!val) return;
                        var $component = $el.closest('[wire\\:id]');
                        var wireId = $component.attr('wire:id');
                        if (window.Livewire && wireId) {
                            var c = window.Livewire.find(wireId);
                            if (c) c.call('openReasonModal', val);
                        }
                        // reset el select para que pueda reseleccionar la misma plantilla
                        setTimeout(function () {
                            $el.val('').trigger('change.select2');
                        }, 50);
                    });
                });
            }

            document.addEventListener('DOMContentLoaded', function () {
                initReasonTemplateSelects();
            });
            document.addEventListener('livewire:initialized', function () {
                initReasonTemplateSelects();
                if (window.Livewire && Livewire.hook) {
                    Livewire.hook('morph.added', function (ref) {
                        if (ref && ref.el) initReasonTemplateSelects(ref.el);
                    });
                }
            });
        })();
    </script>
@endPushOnce
