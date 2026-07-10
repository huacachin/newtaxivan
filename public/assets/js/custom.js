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
            yearRange: '1950:2036',
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

// =========================================================
// Compresión client-side de imágenes con Canvas API.
//
// Uso desde Blade: añadir el atributo `data-compress-image` al <input type="file">.
// Antes de que Livewire suba el archivo, se redimensiona a max 1920px de lado
// y se re-comprime a JPEG calidad 85. Una foto de 8MB pasa a ~500-800KB.
// =========================================================

function _compressImageFile(file, maxSide, quality) {
    return new Promise(function (resolve, reject) {
        var reader = new FileReader();
        reader.onload = function (e) {
            var img = new Image();
            img.onload = function () {
                var w = img.width, h = img.height;
                if (w > h && w > maxSide)      { h = h * (maxSide / w); w = maxSide; }
                else if (h >= w && h > maxSide){ w = w * (maxSide / h); h = maxSide; }
                var canvas = document.createElement('canvas');
                canvas.width = Math.round(w);
                canvas.height = Math.round(h);
                var ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                canvas.toBlob(function (blob) {
                    if (!blob) return reject(new Error('toBlob falló'));
                    resolve(blob);
                }, 'image/jpeg', quality);
            };
            img.onerror = reject;
            img.src = e.target.result;
        };
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
}

function _renameToJpeg(name) {
    return (name || 'photo').replace(/\.[^.]+$/, '') + '.jpg';
}

function attachImageCompression(input) {
    if (!input || input.dataset._compressBound === '1') return;
    input.dataset._compressBound = '1';

    var maxSide = parseInt(input.dataset.compressMax || '1920', 10);
    var quality = parseFloat(input.dataset.compressQuality || '0.85');

    input.addEventListener('change', function (event) {
        // Reentrada: nuestro propio re-dispatch ya viene comprimido.
        if (input.dataset._compressed === '1') {
            delete input.dataset._compressed;
            return;
        }

        var files = input.files;
        if (!files || files.length === 0) return;

        // Solo intervenir si hay al menos una imagen
        var anyImage = false;
        for (var i = 0; i < files.length; i++) {
            if (/^image\//.test(files[i].type)) { anyImage = true; break; }
        }
        if (!anyImage) return;

        // Detener listeners en burbuja (Livewire) hasta tener los blobs comprimidos.
        event.stopImmediatePropagation();

        // Buscar el indicador de compresion: hermano del input o en el padre cercano
        var statusEl = input.parentNode && input.parentNode.querySelector('[data-photo-compress-status]');
        if (!statusEl && input.closest) {
            var wrap = input.closest('.multi-photos, .mb-3, .col-12');
            if (wrap) statusEl = wrap.querySelector('[data-photo-compress-status]');
        }
        if (statusEl) {
            statusEl.classList.remove('d-none');
            statusEl.classList.add('d-flex');
        }

        var done = function () {
            if (statusEl) {
                statusEl.classList.add('d-none');
                statusEl.classList.remove('d-flex');
            }
        };

        var tasks = [];
        for (var j = 0; j < files.length; j++) {
            (function (file) {
                if (!/^image\//.test(file.type)) {
                    tasks.push(Promise.resolve(file));
                    return;
                }
                tasks.push(
                    _compressImageFile(file, maxSide, quality)
                        .then(function (blob) {
                            return new File([blob], _renameToJpeg(file.name), {
                                type: 'image/jpeg',
                                lastModified: Date.now()
                            });
                        })
                        .catch(function (err) {
                            console.warn('Compresión falló, mantengo original:', err);
                            return file;
                        })
                );
            })(files[j]);
        }

        Promise.all(tasks).then(function (outFiles) {
            var dt = new DataTransfer();
            outFiles.forEach(function (f) { dt.items.add(f); });
            input.files = dt.files;
            input.dataset._compressed = '1';
            done();
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }, true); // capture phase: corremos ANTES que el listener de Livewire
}

// Auto-bind al cargar la página y tras cada morph de Livewire.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[type=file][data-compress-image]').forEach(attachImageCompression);
});
document.addEventListener('livewire:init', function () {
    if (!window.Livewire || !Livewire.hook) return;
    Livewire.hook('morph.added', function (ref) {
        var el = ref && ref.el;
        if (!el) return;
        if (el.matches && el.matches('input[type=file][data-compress-image]')) attachImageCompression(el);
        if (el.querySelectorAll) el.querySelectorAll('input[type=file][data-compress-image]').forEach(attachImageCompression);
    });
});

// =========================================================
// Highlight de campos editados (vista Edit con ?highlight=...).
// Disparado desde /audit-logs al hacer click en el boton verde:
// se anexa ?highlight=prop1,prop2,... con los nombres de propiedades
// Livewire (no de columna DB). Aplica .field-audit-highlight al input
// y .field-audit-highlight-label al <label for=id> asociado.
// =========================================================
function _applyAuditHighlight() {
    var params = new URLSearchParams(window.location.search);
    var raw = params.get('highlight') || '';
    if (!raw) return [];

    var fields = raw.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
    if (!fields.length) return [];

    fields.forEach(function (name) {
        // Escapado simple del nombre para CSS selectors
        var esc = (window.CSS && CSS.escape) ? CSS.escape(name) : name.replace(/"/g, '\\"');
        // wire:model + modificadores, mas data-audit-field para campos
        // "virtuales" sin input directo (ej: image_path se mapea al
        // bloque multi-photos via data-audit-field="image_path").
        var selectors = [
            '[wire\\:model="' + esc + '"]',
            '[wire\\:model\\.defer="' + esc + '"]',
            '[wire\\:model\\.lazy="' + esc + '"]',
            '[wire\\:model\\.live="' + esc + '"]',
            '[wire\\:model\\.live\\.debounce="' + esc + '"]',
            '[data-audit-field="' + esc + '"]'
        ];
        document.querySelectorAll(selectors.join(',')).forEach(function (el) {
            el.classList.add('field-audit-highlight');
            if (el.id) {
                var lab = document.querySelector('label[for="' + esc + '"]');
                // tambien probemos por el id real del input (puede que el "for" use otro)
                if (!lab) lab = document.querySelector('label[for="' + el.id + '"]');
                if (lab) lab.classList.add('field-audit-highlight-label');
            }
        });
    });

    return fields;
}

document.addEventListener('DOMContentLoaded', function () {
    _applyAuditHighlight();
});
// Re-aplicar tras morfs de Livewire (los inputs pueden re-renderizarse).
document.addEventListener('livewire:init', function () {
    if (window.Livewire && Livewire.hook) {
        Livewire.hook('morph.added', function () { _applyAuditHighlight(); });
    }
});

// Permite quitar el highlight desde el banner (limpia query y clases).
window.clearAuditHighlight = function () {
    document.querySelectorAll('.field-audit-highlight')
        .forEach(function (el) { el.classList.remove('field-audit-highlight'); });
    document.querySelectorAll('.field-audit-highlight-label')
        .forEach(function (el) { el.classList.remove('field-audit-highlight-label'); });
    if (window.history && history.replaceState) {
        history.replaceState(null, '', window.location.pathname);
    }
};

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

function questionActivate(id, role, name) {
    var msg = (role && name)
        ? '¿Está seguro de activar al ' + role + ' <span style="color:green;font-weight:bold">' + name + '</span>?'
        : "¿Está seguro que desea activar el registro?";
    Swal.fire({
        title: "Se va a activar el registro",
        html: msg,
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#198754",
        cancelButtonColor: "#d33",
        confirmButtonText: "Activar"
    }).then(function (result) {
        if (result.isConfirmed) {
            Livewire.dispatch('register_activate', {id: id});
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

window.addEventListener('questionActivate', function (event) {
    var data = event.detail[0];
    questionActivate(data['id'], data['role'] || '', data['name'] || '');
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

// Bootstrap Tooltips (getOrCreateInstance evita duplicados al re-inicializar)
function initBsTooltips() {
    if (!window.bootstrap || !bootstrap.Tooltip) return;
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        bootstrap.Tooltip.getOrCreateInstance(el);
    });
}
document.addEventListener('DOMContentLoaded', initBsTooltips);
document.addEventListener('livewire:navigated', initBsTooltips);
// Re-inicializar tras cada actualizacion de Livewire (busquedas, filtros)
document.addEventListener('livewire:init', function () {
    Livewire.hook('commit', function (_ref) {
        _ref.succeed(function () { setTimeout(initBsTooltips, 0); });
    });
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

// ===== Historial de busqueda propio (datalist + localStorage) =====
// Chrome clasifica los buscadores como "search box" y no guarda su historial
// de autofill, asi que lo manejamos nosotros. Uso: agregar al input
// data-search-history="clave" y un <datalist> referenciado por list="...".
(function () {
    function readHistory(key) {
        try { return JSON.parse(localStorage.getItem(key)) || []; } catch (e) { return []; }
    }

    function initSearchHistory() {
        document.querySelectorAll('input[data-search-history]').forEach(function (input) {
            if (input.dataset.searchHistoryInit) return;
            input.dataset.searchHistoryInit = '1';

            var key = 'searchHist:' + input.dataset.searchHistory;
            var datalist = input.getAttribute('list') ? document.getElementById(input.getAttribute('list')) : null;
            if (!datalist) return;

            var render = function () {
                datalist.innerHTML = '';
                readHistory(key).forEach(function (v) {
                    var opt = document.createElement('option');
                    opt.value = v;
                    datalist.appendChild(opt);
                });
            };

            var record = function () {
                var v = (input.value || '').trim();
                if (v.length < 2) return;
                var items = readHistory(key).filter(function (x) {
                    return x.toLowerCase() !== v.toLowerCase();
                });
                items.unshift(v);
                localStorage.setItem(key, JSON.stringify(items.slice(0, 10)));
                render();
            };

            // Submit nativo (Enter) y perdida de foco con valor (p. ej. antes de
            // hacer click en "Buscar") registran el termino.
            if (input.form) input.form.addEventListener('submit', record);
            input.addEventListener('change', record);

            render();
        });
    }

    document.addEventListener('DOMContentLoaded', initSearchHistory);
    document.addEventListener('livewire:navigated', initSearchHistory);
})();
