@push('styles')
    <style>
        .compact-table{ font-size:12px; line-height:1.15; table-layout:auto; }
        .compact-table th,.compact-table td{ padding:.25rem .4rem; vertical-align:middle; white-space:nowrap; }

        .sticky-top-0{ position:sticky; top:0; z-index:2; }
        .table thead th{ background: var(--bs-primary-bg-subtle,#e9f4ff); }

        .wk-header{ background:#f1f5f9; border-radius:.5rem; padding:.5rem .75rem; display:flex; align-items:center; gap:.5rem; }
        .wk-header .range{ color:#6b7280; font-size:.875rem; }

        .lw-holder{ position:relative; }
        .lw-overlay{ position:absolute; inset:0; background:rgba(255,255,255,.6); display:flex; align-items:center; justify-content:center; gap:.5rem; z-index:10; }
    </style>
@endpush

@php
    use Carbon\Carbon;
    $currMonth = null;
    try { $currMonth = Carbon::createFromFormat('Y-m', $month ?? now()->format('Y-m')); }
    catch (\Throwable $e) { $currMonth = now(); }
    $selY = (int)$currMonth->format('Y');
    $selM = (int)$currMonth->format('m');

    $yearStart = $selY - 5; $yearEnd = $selY + 1;
    $monthsList = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
@endphp

<div class="container-fluid">

    {{-- Header / migas --}}
    <div class="row">
        <div class="col-sm-6"><h4 class="main-title">Reporte General (semanal)</h4></div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Caja</span></a>
                </li>
                <li class="d-flex active"><a href="#" class="f-s-14">Reporte semanal</a></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">

        {{-- Filtros (Mes / Año) --}}
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row g-2 align-items-end">
                        <div class="col-sm-5 col-md-5">
                            <label class="form-label">Mes</label>
                            <select id="gr-month" class="form-select">
                                @foreach($monthsList as $mVal => $mLabel)
                                    <option value="{{ $mVal }}" @selected($mVal === $selM)>{{ $mLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-3 col-md-3">
                            <label class="form-label">Año</label>
                            <select id="gr-year" class="form-select">
                                @for($y=$yearStart;$y<=$yearEnd;$y++)
                                    <option value="{{ $y }}" @selected($y === $selY)>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-sm-3 col-md-3">
                            <button class="btn btn-primary w-100" wire:click="export">
                                <i class="ti ti-file-analytics f-s-16"></i> Exportar
                            </button>
                        </div>
                        <div class="col-sm-2 col-md-1">
                            <button id="down" class="btn btn-primary w-100" type="button" title="Ir abajo">
                                <i class="ti ti-square-chevrons-down f-s-17"></i>
                            </button>
                        </div>

                        {{-- Enlaza con Livewire (YYYY-MM) --}}
                        <input id="wireMonth" type="hidden" wire:model.live="month" value="{{ $currMonth->format('Y-m') }}"/>

                    </div>

                </div>
            </div>
        </div>

        {{-- Secciones semanales + overlay --}}
        <div class="col-12">
            <div class="card">
                <div class="card-body lw-holder">

                    {{-- Overlay que aparece SOLO cuando month está refrescando --}}
                    <div class="lw-overlay"
                         wire:loading
                         wire:target="month">
                        <div class="spinner-border" role="status" aria-hidden="true"></div>
                        <span class="text-muted">Cargando…</span>
                    </div>

                    @foreach($sections as $k => $sec)
                        <div wire:key="sec-{{ $k }}" class="mb-3">
                            <div class="wk-header">
                                <strong>{{ $sec['label'] }}</strong>
                                <span class="range">{{ $sec['start'] }} — {{ $sec['end'] }}</span>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-striped compact-table">
                                    <thead>
                                    <tr>
                                        <th class="sticky-top-0">#</th>
                                        <th class="sticky-top-0">Fecha</th>
                                        <th class="sticky-top-0">Usuario</th>
                                        <th class="sticky-top-0">Origen</th>
                                        <th class="sticky-top-0">Detalle</th>
                                        <th class="sticky-top-0 text-end">Ingreso (S/)</th>
                                        <th class="sticky-top-0 text-end">Egreso (S/)</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($sec['rows'] as $i => $r)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $r['date'] }}</td>
                                            <td>{{ $r['user'] }}</td>
                                            <td>{{ $r['source'] }}</td>
                                            <td class="text-truncate" style="max-width:520px;">{{ $r['detail'] }}</td>
                                            <td class="text-end">{{ number_format($r['income'], 2) }}</td>
                                            <td class="text-end">{{ number_format($r['expense'], 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">Sin movimientos en esta semana.</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                    <tfoot class="table-primary">
                                    <tr>
                                        <td colspan="5" class="text-end"><strong>Resumen semana</strong></td>
                                        <td class="text-end"><strong>{{ number_format($sec['summary']['income'], 2) }}</strong></td>
                                        <td class="text-end"><strong>{{ number_format($sec['summary']['expense'], 2) }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="text-end"><strong>Utilidad semana</strong></td>
                                        <td colspan="2" class="text-end"><strong>{{ number_format($sec['summary']['profit'], 2) }}</strong></td>
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    @endforeach

                    <div class="alert alert-info d-flex justify-content-between align-items-center mt-4">
                        <div><strong>Total del mes</strong></div>
                        <div class="ms-auto">
                            <span class="me-3">Ingresos: <strong>{{ number_format($grandIncome, 2) }}</strong></span>
                            <span class="me-3">Egresos: <strong>{{ number_format($grandExpense, 2) }}</strong></span>
                            <span>Utilidad: <strong>{{ number_format($grandProfit, 2) }}</strong></span>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

@push('scripts')
    <script>
        (function(){
            function pad2(n){ return (n<10 ? '0' : '') + n; }
            const mSel = document.getElementById('gr-month');
            const ySel = document.getElementById('gr-year');
            const hidden = document.getElementById('wireMonth');

            function syncHiddenMonth(){
                const y = (ySel?.value || '{{ $selY }}').toString();
                const m = pad2(parseInt(mSel?.value || '{{ $selM }}', 10));
                const val = `${y}-${m}`;
                if (hidden && hidden.value !== val) {
                    hidden.value = val;
                    hidden.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }

            mSel?.addEventListener('change', syncHiddenMonth);
            ySel?.addEventListener('change', syncHiddenMonth);
        })();
    </script>
@endpush
