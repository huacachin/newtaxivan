{{-- resources/views/livewire/cost-per-plate/days.blade.php --}}
<div class="container-fluid">
    <!-- Header -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">INGRESO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Configuración</span></a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Costo por placa</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <!-- Tabla -->
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body pb-2">

                    <div class="my-2 g-3 d-flex align-items-center justify-content-between">
                        <h5> Fecha: {{ $now->format('d/m/Y') }} </h5>
                            <div class="d-flex">
                                <input type="text" class="form-control form-control-sm w-120 mg-e-10" placeholder="Buscar por placa"
                                       wire:model="plate" aria-label="Buscar por placa">
                                <button class="btn btn-sm btn-dark mg-e-10" wire:click="$refresh">
                                    <i class="ti ti-search f-s-12"></i>
                                </button>
                                <button class="btn btn-sm btn-primary mg-e-10" wire:click="goBack">
                                    <i class="ti ti-arrow-back-up f-s-12"></i> Regresar
                                </button>
                                <button id="down"
                                        class="btn btn-sm btn-primary flex-shrink-0">
                                    <i class="ti ti-square-chevrons-down f-s-12"></i>
                                </button>
                            </div>

                    </div>
                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>Item</th>
                                <th>Mes</th>
                                <th>Año</th>
                                <th>Placa</th>
                                <th>Monto ({{ $now->format('d/m/Y') }})</th>
                                <th width="10"></th>
                            </tr>
                            </thead>

                            <tbody>
                            @if($result->count() > 0)
                                @foreach($result as $item)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ ucfirst(\Carbon\Carbon::create(null, $item->month, 1)->locale('es')->translatedFormat('F')) }}</td>
                                        <td>{{ $item->year }}</td>
                                        <td>{{ $item->plate }}</td>
                                        <td>{{ number_format($item->amount, 2) }}</td>
                                        <td>
                                            <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"
                                               wire:click="openCalendar('{{ $item->plate }}', {{ $item->year }}, {{ $item->month }})"></i>
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="6" class="py-4 text-muted">No se encontraron resultados</td>
                                </tr>
                            @endif
                            </tbody>

                            <tfoot class="bg-primary">
                            <tr>
                                <td></td>
                                <td>TOTAL</td>
                                <td></td>
                                <td>
                                    {{-- cantidad de registros (placas listadas) --}}
                                    {{ $result->count() }} registro{{ $result->count() === 1 ? '' : 's' }}
                                </td>
                                <td class="num">
                                    {{ number_format($result->sum('amount'), 2) }}
                                </td>
                                <td></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
