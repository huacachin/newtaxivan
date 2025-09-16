<div class="container-fluid">
    <!-- Basic Table start -->
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title">Placa: {{$plate}} - {{ \Carbon\Carbon::create($year, $month, 1)->locale('es')->isoFormat('MMMM YYYY') }}</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Configuración</span>
                    </a>
                </li>
                <li class="d-flex">
                    <a href="#" class="f-s-14">Costo por placa</a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Calendario</a>
                </li>
            </ul>
        </div>
    </div>
    <!-- Basic Table end -->
    <div class="row table-section">

        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-7 mb-2 mb-md-0">
                            <form class="app-form app-icon-form" action="#">
                                <div class="position-relative">
                                    <input type="number" class="form-control" placeholder="0.00"
                                           aria-label="Apply" step="0.01" min="0"
                                           wire:model.live="bulk">
                                    <i class="ti ti-123 text-dark"></i>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-2 mb-2 mb-md-0">
                            <button class="btn btn-primary w-100" wire:click="fillAll" wire:loading.attr="disabled" wire:target="fillAll,saveAll" ><i class="ti ti-circle-check-filled f-s-17"></i>
                                Aplicar a todos
                            </button>
                        </div>
                        <div class="col-md-2 mb-2 mb-md-0">
                            <button class="btn btn-primary w-100" wire:click="saveAll" wire:loading.attr="disabled" wire:target="fillAll,saveAll"><i class="ti ti-device-floppy f-s-17"></i>
                                Guardar
                            </button>
                        </div>
                        <div class="col-md-1 mb-2 mb-md-0">
                            <button class="btn btn-primary w-100" wire:click="goBack" ><i class="ti ti-arrow-back-up f-s-17"></i>

                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-12 table-responsive">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>Calendario</h5>
                </div>
                <div class="card-body">

                           <div class="overflow-auto">
                               <table class="table table-sm table-bordered table-striped table-hover table-responsive">
                                   <thead class="table-primary">
                                   <tr>
                                       @foreach (['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $dow)
                                           <th width="10">{{ $dow }}</th>
                                       @endforeach
                                   </tr>
                                   </thead>
                                   <tbody>
                                   @foreach ($weeks as $week)
                                       <tr>
                                           @foreach ($week as $date)
                                               <td>
                                                   @if ($date)
                                                       @php
                                                           $day = \Carbon\Carbon::parse($date)->day;
                                                       @endphp
                                                       <div>{{ $day }}</div>
                                                       <div>
                                                           <span>S/</span>
                                                           <input
                                                               type="number" step="0.01" min="0"
                                                               wire:key="day-{{ $date }}"
                                                               wire:model.defer="values.{{ $date }}"
                                                               style="width: 70px"
                                                               class="p-1 border rounded text-sm"
                                                           />
                                                       </div>
                                                   @endif
                                               </td>
                                           @endforeach
                                       </tr>
                                   @endforeach
                                   </tbody>
                               </table>
                           </div>

                </div>
            </div>
        </div>



    </div>


</div>
