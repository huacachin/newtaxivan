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
                'Enero','Febrero','Marzo','Abril','Mayo','Junio',
                'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'
            ],
            monthNamesShort: ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'],
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
// Registro global de datepickers para sobrevivir re-renders de Livewire
var _dpRegistry = [];

function _applyDatepickers(pairs, wire) {
    pairs.forEach(function (pair) {
        var $el = $(pair[0]);
        if (!$el.length) return;
        var currentVal = $el.val();
        // Destruir siempre antes de reinicializar (evita doble binding)
        try { $el.datepicker('destroy'); } catch (e) {}
        $el.datepicker({
            changeMonth: true,
            changeYear: true,
            yearRange: '2015:2036',
            dateFormat: 'yy-mm-dd',
            onSelect: function (dateText) {
                wire.set(pair[1], dateText);
            }
        });
        // Restaurar valor visual Y estado interno del datepicker
        if (currentVal) {
            $el.datepicker('setDate', currentVal);
        }
    });
}

function initLivewireDatepicker(pairs, wire) {
    _dpRegistry.push({ pairs: pairs, wire: wire });
    _applyDatepickers(pairs, wire);
}

// Hook de Livewire 3: reinicializar datepickers después de cada commit exitoso
document.addEventListener('livewire:initialized', function () {
    Livewire.hook('commit', function (_ref) {
        var succeed = _ref.succeed;
        succeed(function () {
            requestAnimationFrame(function () {
                _dpRegistry.forEach(function (cfg) {
                    _applyDatepickers(cfg.pairs, cfg.wire);
                });
            });
        });
    });
});

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

function questionDelete(id, role, name) {
    var msg = (role && name)
        ? '¿Está seguro de eliminar al ' + role + ' <span style="color:red;font-weight:bold">' + name + '</span>?'
        : "¿Está seguro que desea eliminar el registro?";
    Swal.fire({
        title: "Se va a eliminar el registro",
        html: msg,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Eliminar"
    }).then(function (result) {
        if (result.isConfirmed) {
            Livewire.dispatch('register_destroy', {id: id});
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
    var data = event.detail[0];
    questionDelete(data['id'], data['role'] || '', data['name'] || '');
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

window.addEventListener('errorAlert', function (event) {
    var data = event.detail[0] || {};
    Swal.fire({
        icon: 'warning',
        title: 'Atención',
        text: data.message || 'Hubo un error',
        confirmButtonColor: '#3085d6',
    });
});

window.addEventListener('confirmReactivate', function (event) {
    var data = event.detail[0] || {};
    Swal.fire({
        title: data.entity + ' inactivo encontrado',
        html: 'El ' + data.entity.toLowerCase() + ' <strong>' + data.name + '</strong> fue eliminado anteriormente. ¿Desea reactivarlo?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, reactivar',
        cancelButtonText: 'No',
    }).then(function (result) {
        if (result.isConfirmed) {
            Livewire.dispatch('reactivateConfirmed', { id: data.id });
        }
    });
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

// Bootstrap Tooltips
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (el) { return new bootstrap.Tooltip(el); });
});
document.addEventListener('livewire:navigated', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (el) { return new bootstrap.Tooltip(el); });
});

// =========================================================
// numericLRU: LRU por usuario + campo en localStorage
// Uso:
//   numericLRU.record('departures.passage', 35);
//   numericLRU.get('departures.passage'); // ['35','40','30']
// =========================================================
window.numericLRU = (function () {
    var PREFIX = 'taxivan.lru.';
    function key(k) {
        var meta = document.querySelector('meta[name="lru-user"]');
        var uid  = meta ? meta.getAttribute('content') : 'anon';
        return PREFIX + uid + '.' + k;
    }
    function get(k) {
        try {
            var raw = localStorage.getItem(key(k));
            if (!raw) return [];
            var arr = JSON.parse(raw);
            return Array.isArray(arr) ? arr : [];
        } catch (e) { return []; }
    }
    function record(k, value, max) {
        max = max || 5;
        var v = String(value).trim();
        if (!v) return;
        var arr = get(k).filter(function (x) { return String(x) !== v; });
        arr.unshift(v);
        if (arr.length > max) arr = arr.slice(0, max);
        try { localStorage.setItem(key(k), JSON.stringify(arr)); } catch (e) {}
    }
    return { get: get, record: record };
})();

// =========================================================
// Alpine factory: numericChips
// Mezcla fuentes (contextual derivado, server-side via Livewire, LRU local)
// con prioridad y deduplicación. Maneja Alt+1..5 para pick rápido.
// =========================================================
document.addEventListener('alpine:init', function () {
    if (!window.Alpine) return;

    window.Alpine.data('numericChips', function (opts) {
        return {
            opts: opts || {},
            suggestions: [],

            init: function () {
                var self = this;
                self.refresh();

                // Recalcular cuando Livewire commitea cambios de estado
                if (window.Livewire && Livewire.hook) {
                    Livewire.hook('commit', function (_ref) {
                        var succeed = _ref.succeed;
                        succeed(function () { self.refresh(); });
                    });
                }

                var input = self.$refs.input;
                if (input) {
                    // Atajos Alt+1..5 dentro del input
                    input.addEventListener('keydown', function (e) {
                        if (!e.altKey) return;
                        var num = parseInt(e.key, 10);
                        if (!num || num < 1 || num > 9) return;
                        var item = self.suggestions[num - 1];
                        if (!item) return;
                        e.preventDefault();
                        self.pick(item.value);
                    });

                    // Registrar LRU cuando el input pierde foco con un valor válido
                    input.addEventListener('blur', function () {
                        var v = (input.value || '').replace(',', '.').trim();
                        if (!v) return;
                        if (self.opts.storageKey && !isNaN(parseFloat(v))) {
                            window.numericLRU.record(self.opts.storageKey, v, 5);
                        }
                    });
                }
            },

            refresh: function () {
                var max = this.opts.max || 5;
                var pool = []; // { value, hint, source }

                var add = function (value, hint, source) {
                    if (value === null || value === undefined || value === '') return;
                    var v = String(value).trim();
                    if (!v) return;
                    if (pool.some(function (p) { return p.value === v; })) return;
                    pool.push({ value: v, hint: hint || '', source: source });
                };

                // 1) Contextual derivado (mayor prioridad)
                if (typeof this.opts.contextual === 'function') {
                    try {
                        var ctx = this.opts.contextual() || [];
                        ctx.forEach(function (item) {
                            if (typeof item === 'object' && item !== null) {
                                add(item.value, item.hint, 'context');
                            } else {
                                add(item, 'Contexto del vehículo', 'context');
                            }
                        });
                    } catch (e) {}
                }

                // 2) Server-side via Livewire
                if (typeof this.opts.server === 'function') {
                    try {
                        var srv = this.opts.server() || [];
                        srv.forEach(function (item) {
                            if (typeof item === 'object' && item !== null) {
                                add(item.value, item.hint || 'Frecuente', 'server');
                            } else {
                                add(item, 'Frecuente', 'server');
                            }
                        });
                    } catch (e) {}
                }

                // 3) LRU local
                if (this.opts.storageKey) {
                    var lru = window.numericLRU.get(this.opts.storageKey);
                    lru.forEach(function (v) { add(v, 'Tu último valor', 'lru'); });
                }

                this.suggestions = pool.slice(0, max);
            },

            pick: function (value) {
                var self = this;
                var input = self.$refs.input;
                if (!input) return;
                input.value = value;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                input.focus();

                if (self.opts.storageKey) {
                    window.numericLRU.record(self.opts.storageKey, value, 5);
                }
            },

            badgeClass: function (source) {
                if (source === 'context') return 'num-chip num-chip-context';
                if (source === 'server')  return 'num-chip num-chip-server';
                return 'num-chip num-chip-lru';
            },

            formatted: function (v) {
                if (typeof this.opts.format === 'function') {
                    try { return this.opts.format(v); } catch (e) {}
                }
                var n = parseFloat(v);
                if (isNaN(n)) return v;
                if (this.opts.decimals === 0) return String(Math.round(n));
                if (this.opts.decimals)       return n.toFixed(this.opts.decimals);
                return String(n);
            }
        };
    });
});
