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
