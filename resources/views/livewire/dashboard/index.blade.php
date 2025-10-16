@push('styles')
    <style>
        .screen-overlay {
            position: fixed;
            inset: 0;                 /* full viewport */
            display: none;            /* Livewire lo pondrá en flex */
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,.35);
            backdrop-filter: blur(2px);
            z-index: 2000;            /* sobre modals/backdrops de Bootstrap */
            pointer-events: all;      /* bloquea clics */
        }
    </style>
@endpush
<div class="container-fluid">

    <div class="row project_dashboard">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Mes</label>
                            <select wire:model.live="month" class="form-select">
                                @for($m=1;$m<=12;$m++)
                                    <option value="{{ $m }}">{{ \Carbon\Carbon::create(null, $m, 1)->locale('es')->translatedFormat('F') }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Año</label>
                            <select wire:model.live="year" class="form-select">
                                @for($y=now()->year-5;$y<=now()->year+1;$y++)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Cards -->
        <div class="col-md-3">
            <div class="card project-cards">
                <div class="card-body d-flex justify-content-between">
                    <div>
                        <h6>Ingresos del Mes</h6>

                        <div class="d-flex align-items-center gap-2 mt-2">
                            <h4 class=" text-success f-w-600 counting" data-count='499'></h4>

                            <h2 class="m-0 text-secondary">{{ number_format($ingMes, 2) }}</h2>
                        </div>
                    </div>
                    <div class="project-card-icon project-success bg-light-success h-55 w-55 d-flex-center b-r-100">
                        <i class="ti ti-currency-dollar f-s-36 mb-1"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card project-cards">
                <div class="card-body d-flex justify-content-between">
                    <div>
                        <h6>Egreso del Mes</h6>

                        <div class="d-flex align-items-center gap-2 mt-2">
                            <h4 class=" text-warning f-w-600 counting" data-count='159'></h4>
                            <h2 class="m-0 text-secondary">{{ number_format($egrMes, 2) }}</h2>
                        </div>
                    </div>
                    <div class="project-card-icon project-secondary bg-light-warning h-55 w-55 d-flex-center b-r-100">
                        <i class="ti ti-currency-dollar f-s-36 mb-1"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card project-cards">
                <div class="card-body d-flex justify-content-between">
                    <div>
                        <h6>Utilidad del Mes</h6>

                        <div class="d-flex align-items-center gap-2 mt-2">
                            <h4 class=" text-danger f-w-600 counting" data-count='220'></h4>
                            <h2 class="m-0 text-secondary">{{ number_format($utilMes, 2) }}</h2>
                        </div>
                    </div>
                    <div class="project-card-icon project-danger bg-light-danger h-55 w-55 d-flex-center b-r-100">
                        <i class="ti ti-currency-dollar f-s-36 mb-1"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card project-cards">
                <div class="card-body d-flex justify-content-between">
                    <div>
                        <h6 class="text-secondary">Prom. saldo por día (c/ movimiento)</h6>

                        <div class="d-flex align-items-center gap-2 mt-2">
                            <h4 class=" text-success f-w-600 counting inline" data-count='199'></h4>
                            <h2 class="m-0 text-secondary">{{ number_format($promSaldoDia, 2) }}</h2>
                        </div>
                    </div>
                    <div class="project-card-icon project-primary bg-light-success h-60 w-60 d-flex-center b-r-100">
                        <i class="ti ti-currency-dollar f-s-36 mb-1"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card project-cards">
                <div class="card-body d-flex justify-content-between">
                    <div>
                        <h6>Ingresos Hoy</h6>

                        <div class="d-flex align-items-center gap-2 mt-2">
                            <h4 class=" text-warning f-w-600 counting" data-count='159'></h4>
                            <h2 class="m-0 text-secondary">{{ number_format($ingHoy, 2) }}</h2>
                        </div>
                    </div>
                    <div class="project-card-icon project-secondary bg-light-warning h-55 w-55 d-flex-center b-r-100">
                        <i class="ti ti-currency-dollar f-s-30 mb-1"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card project-cards">
                <div class="card-body d-flex justify-content-between">
                    <div>
                        <h6>Egresos hoy</h6>

                        <div class="d-flex align-items-center gap-2 mt-2">
                            <h4 class=" text-danger f-w-600 counting" data-count='220'></h4>
                            <h2 class="m-0 text-secondary">{{ number_format($egrHoy, 2) }}</h2>
                        </div>
                    </div>
                    <div class="project-card-icon project-danger bg-light-danger h-55 w-55 d-flex-center b-r-100">
                        <i class="ti ti-currency-dollar f-s-30 mb-1"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card project-cards">
                <div class="card-body d-flex justify-content-between">
                    <div>
                        <h6 class="text-secondary">Saldo Hoy</h6>

                        <div class="d-flex align-items-center gap-2 mt-2">
                            <h4 class=" text-success f-w-600 counting inline" data-count='199'></h4>
                            <h2 class="m-0 text-secondary">{{ number_format($saldoHoy, 2) }}</h2>
                        </div>
                    </div>
                    <div class="project-card-icon project-primary bg-light-success h-60 w-60 d-flex-center b-r-100">
                        <i class="ti ti-currency-dollar f-s-36 mb-1"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Top 5 Sedes por ingreso (mes)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="app-scroll table-responsive">
                        <table class="table table-bottom-border table-box-hover  project-team-table align-middle  mb-0 table-striped">
                            <thead>
                            <tr>
                                <th scope="col">Sede</th>
                                <th scope="col" class="text-center">Ingreso</th>
                            </tr>
                            </thead>
                            <tbody>

                            @forelse($topHQ as $row)
                                <tr>
                                    <td><h6>{{ $row['hq'] }}</h6></td>
                                    <td class="text-center">S/. {{ number_format($row['sum'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted">Sin datos</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Top tipos de pago (mes)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="app-scroll table-responsive">
                        <table class="table table-bottom-border table-box-hover  project-team-table align-middle  mb-0 table-striped">
                            <thead>
                            <tr>
                                <th scope="col">Tipo</th>
                                <th scope="col" class="text-center">Monto</th>
                            </tr>
                            </thead>
                            <tbody>

                            @forelse($topTypes as $row)
                                <tr>
                                    <td><h6>{{ strtoupper($row['type']) }}</h6></td>
                                    <td class="text-center">S/. {{ number_format($row['sum'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted">Sin datos</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Saldos por día (mes)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="app-scroll table-responsive">
                        @php
                            $daysWithMove = array_values(array_filter($days, fn($r) => ($r['income'] != 0 || $r['expense'] != 0)));
                            $last10 = array_slice($daysWithMove, -10);
                        @endphp
                        <table class="table table-bottom-border table-box-hover  project-team-table align-middle  mb-0 table-striped">
                            <thead>
                            <tr>
                                <th scope="col">Fecha</th>
                                <th scope="col" class="text-center">Ingresos</th>
                                <th scope="col" class="text-center">Egresos</th>
                                <th scope="col" class="text-center">Saldo del día</th>
                            </tr>
                            </thead>
                            <tbody>

                            @forelse($last10 as $r)
                                <tr>
                                    <td><h6>{{ $r['date'] }}</h6></td>
                                    <td class="text-center">{{ number_format($r['income'], 2) }}</td>
                                    <td class="text-center">{{ number_format($r['expense'], 2) }}</td>
                                    <td class="text-center fw-semibold">{{ number_format($r['balance'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">Sin datos</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- Cards end --></div>


    <div class="screen-overlay"
         wire:loading.delay.flex
         wire:target="year,month">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
            <div class="mt-2 text-white fw-semibold">Cargando…</div>
        </div>
    </div>

</div>
