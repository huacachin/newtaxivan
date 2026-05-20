{{--
    Banner discreto que aparece cuando la URL trae ?highlight=...
    El usuario lo dispara desde /audit-logs al ir al edit de un registro.
    Boton "Quitar resaltado" llama a window.clearAuditHighlight() que
    limpia el query string y remueve las clases del DOM.
--}}
<div x-data="{ visible: new URLSearchParams(window.location.search).has('highlight') }"
     x-show="visible"
     x-cloak
     class="audit-highlight-banner">
    <div class="d-flex align-items-center gap-2">
        <i class="ti ti-flag audit-highlight-banner__icon"></i>
        <span>Resaltando los campos modificados según el registro de auditoría.</span>
    </div>
    <button type="button"
            class="audit-highlight-banner__btn"
            x-on:click="window.clearAuditHighlight(); visible = false;">
        Quitar resaltado
    </button>
</div>
