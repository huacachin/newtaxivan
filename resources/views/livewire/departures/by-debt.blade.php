
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">SALIDAS — {{ $plate }} — {{ $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '' }}</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-door-exit f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Salidas</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Por deuda</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">

                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <a href="{{ route('debts.debt-per-days') }}" class="btn btn-sm btn-primary">
                            <i class="ti ti-arrow-back-up f-s-12"></i> Volver
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="text-center bg-primary">
                            <tr>
                                <th>N°</th>
                                <th>Placa</th>
                                <th>Fecha</th>
                                <th>Usuario</th>
                                <th>Sucursal</th>
                                <th>Hora</th>
                                <th>Salidas</th>
                                <th>T.S</th>
                            </tr>
                            </thead>

                            <tbody class="text-center">
                            @if(!empty($grouped))
                                @php $counter = 0; @endphp
                                @foreach($grouped as $userName => $userRows)
                                    @foreach($userRows as $idx => $r)
                                        @php $counter++; @endphp
                                        <tr>
                                            <td>{{ $counter }}</td>
                                            <td>{{ $plate }}</td>
                                            <td>{{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</td>
                                            <td>{{ $r->user_name ?? '-' }}</td>
                                            <td>{{ $r->headquarter_name ?? '-' }}</td>
                                            <td>{{ $r->hour ?? '-' }}</td>
                                            <td>{{ $r->salidas }}</td>

                                            @if($idx === 0)
                                                <td rowspan="{{ count($userRows) }}" class="align-middle fw-bold">
                                                    {{ $userTotals[$userName] }}
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="8">No se encontraron resultados para esta placa y fecha.</td>
                                </tr>
                            @endif
                            </tbody>

                            @if(!empty($grouped))
                            <tfoot class="text-center f-w-600 bg-primary">
                            <tr>
                                <td colspan="6">TOTAL</td>
                                <td>{{ $totalSalidas }}</td>
                                <td>{{ $totalSalidas }}</td>
                            </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
