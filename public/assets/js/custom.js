// custom.js — JS compartido extraído de master.blade.php
// =========================================================

// =========================================================
// jQuery UI Datepicker: locale español
// =========================================================
$(function () {
    if (!$.datepicker.regional['es']) {
        $.datepicker.regional['es'] = {
            closeText: 'Cerrar',
            prevText: 'Anterior',
            nextText: 'Siguiente',
            currentText: 'Hoy',
            monthNames: [
                'enero','febrero','marzo','abril','mayo','junio',
                'julio','agosto','septiembre','octubre','noviembre','diciembre'
            ],
            monthNamesShort: ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'],
            dayNames: ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'],
            dayNamesShort: ['dom','lun','mar','mié','jue','vie','sáb'],
            dayNamesMin: ['Do','Lu','Ma','Mi','Ju','Vi','Sa'],
            weekHeader: 'Sm',
            dateFormat: 'yy-mm-dd',
            firstDay: 1,
            isRTL: false,
            showMonthAfterYear: false,
            yearSuffix: ''
        };
    }

    $.datepicker.setDefaults($.datepicker.regional['es']);
});

// =========================================================
// Helper: inicializar múltiples datepickers de Livewire
// Uso en Blade:
//   var wire = @this;
//   initLivewireDatepicker([['#selector', 'wire_prop'], ...], wire);
// =========================================================
function initLivewireDatepicker(pairs, wire) {
    pairs.forEach(function (pair) {
        var selector = pair[0];
        var prop = pair[1];
        $(selector).datepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: 'yy-mm-dd',
            onSelect: function (dateText) {
                wire.set(prop, dateText);
            }
        });
    });
}

// =========================================================
// Modal helpers
// =========================================================
function openModal(id, opts) {
    opts = opts || {};
    var el = document.getElementById(id);
    if (!el) return;

    var opener = document.activeElement;
    if (opener) opener.setAttribute('data-open', id);

    var instance = bootstrap.Modal.getOrCreateInstance(el);
    instance.show();

    var focusSelector = opts.focus || '[autofocus], input:not([type=hidden]):not([disabled]), select, textarea, button';
    var onShown = function () {
        el.removeEventListener('shown.bs.modal', onShown);
        var target = el.querySelector(focusSelector);
        if (target) target.focus();
    };
    el.addEventListener('shown.bs.modal', onShown, { once: true });
}

function hideModal(id) {
    var modalEl = document.getElementById(id);
    if (!modalEl) return;

    var active = document.activeElement;
    if (active && modalEl.contains(active) && typeof active.blur === 'function') {
        active.blur();
    }

    var trigger = document.querySelector('[data-open="' + id + '"]');
    if (trigger) trigger.focus();

    requestAnimationFrame(function () {
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
    });
}

// =========================================================
// SweetAlert helpers
// =========================================================
function successAlert(message) {
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: message,
        confirmButtonText: 'OK',
    });
}

function alertError() {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Hubo un error. Contactar con el administrador',
        confirmButtonText: 'OK',
    });
}

function questionDelete(id) {
    Swal.fire({
        title: "Se va a eliminar el registro",
        text: "Esta seguro que desea eliminar el registro?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Eliminar"
    }).then(function (result) {
        if (result.isConfirmed) {
            Livewire.dispatch('register_destroy', [id]);
            Swal.fire({
                title: "Eliminado!",
                text: "El registro se eliminado correctamente.",
                icon: "success"
            });
        }
    });
}

function questionGenerate() {
    Swal.fire({
        title: "Generar costo por placa",
        text: "Se eliminaran registros del mes actual y se generar el costo por placa, esto es de solo contingencia esta de acuerdo?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Generar"
    }).then(function (result) {
        if (result.isConfirmed) {
            Livewire.dispatch('generate_cost_per_plates');
            Swal.fire({
                title: "Generado!",
                text: "Se genero el costo por placa con éxito!!!",
                icon: "success"
            });
        }
    });
}

function questionLogout() {
    Swal.fire({
        title: "Esta seguro que desea salir del sistema ?",
        text: "La sesión actual se cerrará y abandonará el sistema",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Salir"
    }).then(function (result) {
        if (result.isConfirmed) {
            Livewire.dispatch('logout');
        }
    });
}

// =========================================================
// Event listeners
// =========================================================
document.addEventListener('open-modal', function (event) {
    openModal(event.detail[0]['name'], { focus: '#' + event.detail[0]['focus'] });
});

window.addEventListener('modal-close', function (event) {
    hideModal(event.detail[0]['name']);
});

window.addEventListener('successAlert', function (event) {
    successAlert(event.detail[0]['message']);
});

window.addEventListener('questionDelete', function (event) {
    questionDelete(event.detail[0]['id']);
});

window.addEventListener('questionGenerate', function (event) {
    questionGenerate();
});

window.addEventListener('questionLogout', function (event) {
    questionLogout();
});

window.addEventListener('alertError', function (event) {
    alertError();
});

document.addEventListener('click', function (e) {
    var btn = e.target.closest('#down');
    if (!btn) return;

    var h = Math.max(
        document.body.scrollHeight,
        document.documentElement.scrollHeight
    );
    window.scrollTo({ top: h, behavior: 'smooth' });
});

window.addEventListener('url-open', function (event) {
    var url = (event.detail && event.detail[0] && event.detail[0].url)
           || (event.detail && event.detail.url);
    if (!url) return;
    window.open(url);
});

window.addEventListener('go-back', function (e) {
    var fb = (e.detail && e.detail.fallback) || '/';
    if (history.length > 1) history.back();
    else location.href = fb;
});
