{{-- Formulario de "Bloquear" de Sorteos: modal #blockFormModal (todo el mes, rango, días
     específicos o hasta desbloquearla, con calendario flatpickr) y su script window.drawsBlockForm.
     Lo usan la grilla (escritorio) y la versión del celular; va dentro del componente Livewire. --}}
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/datepikar/flatpickr.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendor/datepikar/flatpickr.js') }}"></script>
    <script>
        // Formulario de "Bloquear" (modal #blockFormModal): todo el mes, rango de fechas (calendario),
        // días específicos (calendario, varios) o hasta desbloquearla, y el motivo.
        // opts = { minDate, maxDate, holidays: [...], months: [{ value, label }] } (lo da el servidor).
        // submit(form) devuelve una promesa con true si se guardó; recién ahí se cierra el modal.
        window.drawsBlockForm = (function () {
            var DAYS = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
            var es = {
                weekdays: { shorthand: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá'], longhand: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'] },
                months: {
                    shorthand: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
                    longhand: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
                },
                firstDayOfWeek: 1, rangeSeparator: ' a ', ordinal: function () { return 'º' },
            };
            var calendars = {};
            var submitFn = null;
            var holidays = [];

            function $(name) { return document.querySelector('#blockFormModal [data-blk="' + name + '"]') }
            function pad(n) { return (n < 10 ? '0' : '') + n }
            function iso(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) }
            function label(d) { return DAYS[d.getDay()] + ' ' + pad(d.getDate()) + '/' + pad(d.getMonth() + 1) }
            function workingDays(from, to) {
                var n = 0;
                for (var d = new Date(from); d <= to; d.setDate(d.getDate() + 1)) {
                    if (d.getDay() !== 0 && holidays.indexOf(iso(d)) === -1) { n++ }
                }
                return n;
            }
            function kind() { return document.querySelector('#blockFormModal input[name=blk-kind]:checked').value }
            function showPane() {
                document.querySelectorAll('#blockFormModal [data-blk-pane]').forEach(function (p) { p.hidden = p.dataset.blkPane !== kind() });
                $('error').hidden = true;
            }
            function error(message) { $('error').textContent = message; $('error').hidden = false }

            function noDraw(d) { return d.getDay() === 0 || holidays.indexOf(iso(d)) !== -1 }
            function calendar(name, mode, opts, onChange) {
                if (calendars[name]) { calendars[name].destroy() }
                $(name + '-input').value = ''; // si no, flatpickr vuelve a marcar los días de la vez anterior
                calendars[name] = flatpickr($(name + '-input'), {
                    inline: true, mode: mode, locale: es, dateFormat: 'Y-m-d', minDate: opts.minDate, maxDate: opts.maxDate, defaultDate: [],
                    // En el rango no se deshabilitan domingos ni feriados: flatpickr no deja que un rango
                    // pase por un día deshabilitado y no se podría cruzar un fin de semana. Solo se ven en gris.
                    disable: mode === 'range' ? [] : [noDraw],
                    onDayCreate: function (sel, str, fp, dayElem) {
                        if (noDraw(dayElem.dateObj)) { dayElem.classList.add('dr-no-draw'); dayElem.title = 'Sin sorteo' }
                    },
                    onChange: onChange,
                });
            }

            function bindOnce() {
                if ($('submit').dataset.bound) { return }
                $('submit').dataset.bound = '1';
                document.querySelectorAll('#blockFormModal input[name=blk-kind]').forEach(function (r) { r.addEventListener('change', showPane) });
                $('submit').addEventListener('click', function () {
                    var form = { kind: kind(), reason: document.getElementById('blk-reason').value.trim() };
                    if (form.kind === 'month') { form.month = $('month').value }
                    if (form.kind === 'range') {
                        var range = calendars.range.selectedDates;
                        if (range.length < 2) { return error('Marca en el calendario el primer y el último día.') }
                        form.from = iso(range[0]); form.to = iso(range[1]);
                    }
                    if (form.kind === 'dates') {
                        form.dates = calendars.dates.selectedDates.map(iso).sort();
                        if (! form.dates.length) { return error('Marca al menos un día en el calendario.') }
                    }
                    if (form.reason.length < 3) { return error('Escribe un motivo (mínimo 3 letras).') }
                    $('submit').disabled = true;
                    Promise.resolve(submitFn(form)).then(function (ok) {
                        if (ok) { bootstrap.Modal.getOrCreateInstance(document.getElementById('blockFormModal')).hide() }
                    }).finally(function () { $('submit').disabled = false });
                });
            }

            return function (count, plates, opts, submit) {
                bindOnce();
                submitFn = submit;
                holidays = opts.holidays || [];
                $('count').textContent = count;
                $('plates').textContent = plates;
                $('min-label').textContent = label(new Date(opts.minDate + 'T00:00:00'));
                $('month').innerHTML = '';
                opts.months.forEach(function (m) { $('month').add(new Option(m.label, m.value)) });
                document.getElementById('blk-kind-month').checked = true;
                document.getElementById('blk-reason').value = '';
                $('range-text').textContent = 'Marca el primer y el último día.';
                $('dates-text').textContent = 'Marca uno o varios días.';
                showPane();

                calendar('range', 'range', opts, function (sel) {
                    $('range-text').textContent = sel.length < 2 ? 'Marca el primer y el último día.'
                        : 'Del ' + label(sel[0]) + ' al ' + label(sel[1]) + ' · ' + workingDays(sel[0], sel[1]) + ' día(s) de sorteo';
                });
                calendar('dates', 'multiple', opts, function (sel) {
                    $('dates-text').textContent = sel.length ? sel.slice().sort(function (a, b) { return a - b }).map(label).join(', ') : 'Marca uno o varios días.';
                });

                bootstrap.Modal.getOrCreateInstance(document.getElementById('blockFormModal')).show();
            };
        })();
    </script>
@endpush

@push('styles')
    <style>
        #blockFormModal .flatpickr-calendar.inline { margin: 0 auto; box-shadow: none; border: 1px solid #e2e8f0; }
        #blockFormModal [data-blk$="-input"] { display: none !important; }
        #blockFormModal .flatpickr-day.flatpickr-disabled { color: #cbd5e1; }
        #blockFormModal .flatpickr-day.dr-no-draw:not(.selected):not(.startRange):not(.endRange) { color: #cbd5e1; }
        /* Hoy solo con borde: el tema lo pinta lleno y parece un día elegido */
        #blockFormModal .flatpickr-day.today:not(.selected):not(.startRange):not(.endRange):not(.inRange) {
            background: transparent !important; border-color: #94a3b8 !important; color: #1e293b !important; }
        /* En el celular (pantalla completa): botones, campos y Guardar más grandes */
        @media (max-width: 575.98px) {
            #blockFormModal .modal-body { font-size: 15px; }
            #blockFormModal [role=group] .btn { font-size: 15px !important; padding: 9px 12px; border-radius: 12px; flex: 1 1 45%; }
            #blockFormModal .form-control, #blockFormModal .form-select { font-size: 16px !important; min-height: 46px; border-radius: 12px; }
            #blockFormModal .modal-footer .btn { flex: 1; min-height: 50px; font-size: 16px !important; font-weight: 700; border-radius: 14px; }
        }
    </style>
@endpush

    {{-- Modal: formulario de "Bloquear" (lo maneja window.drawsBlockForm; wire:ignore para que Livewire no rehaga los calendarios) --}}
    @if($canBlock)
        <div class="modal fade" id="blockFormModal" tabindex="-1" aria-hidden="true" wire:ignore>
            <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="ti ti-lock"></i> Bloquear <span data-blk="count"></span> placa(s)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body small">
                        <p class="text-muted mb-2" data-blk="plates"></p>

                        <div class="d-flex flex-wrap gap-1 mb-3" role="group" aria-label="Cómo bloquear">
                            @foreach(\App\Models\DrawBlock::KIND_LABELS as $kind => $kindLabel)
                                <input type="radio" class="btn-check" name="blk-kind" id="blk-kind-{{ $kind }}" value="{{ $kind }}" @checked($loop->first)>
                                <label class="btn btn-sm btn-outline-dark" for="blk-kind-{{ $kind }}">{{ $kindLabel }}</label>
                            @endforeach
                        </div>

                        <div data-blk-pane="month">
                            <label class="form-label mb-1" for="blk-month">Mes</label>
                            <select class="form-select form-select-sm" id="blk-month" data-blk="month"></select>
                            <div class="form-text">No entra a ningún sorteo de ese mes. Después se puede desbloquear un día suelto con clic en su candado.</div>
                        </div>
                        <div data-blk-pane="range" hidden>
                            <input type="hidden" data-blk="range-input">
                            <div class="form-text text-center" data-blk="range-text"></div>
                        </div>
                        <div data-blk-pane="dates" hidden>
                            <input type="hidden" data-blk="dates-input">
                            <div class="form-text text-center" data-blk="dates-text"></div>
                        </div>
                        <div data-blk-pane="indefinite" hidden>
                            <div class="form-text mt-0">No entra a ningún sorteo desde el <b data-blk="min-label"></b> hasta que la desbloqueen.</div>
                        </div>

                        <label class="form-label mt-3 mb-1" for="blk-reason">Motivo (queda registrado)</label>
                        <input type="text" class="form-control form-control-sm" id="blk-reason" maxlength="255" placeholder="Ej. En taller">
                        <div class="text-danger mt-2" data-blk="error" hidden></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-sm btn-dark" data-blk="submit"><i class="ti ti-lock"></i> Bloquear</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

