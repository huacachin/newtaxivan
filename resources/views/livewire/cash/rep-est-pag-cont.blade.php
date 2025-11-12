@push('styles')
    <style>

        table {
            border-collapse: collapse; /* opcional */
            width: 100%;
        }

        th, td {
            padding: 1px !important;
            font-size: 10px !important;
            text-align: center !important;
            vertical-align: middle; /* <-- clave */
        }

        .btn, input, select {
            font-size: 10px !important;
        }
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
    <div class="flex items-center gap-3">
        <div class="row">
            <div class="col-sm-6">
                <h4 class="main-title title-modules">REPORTE ESTADÍSTICO DE SALIDAS–PAGOS DE CONTROLADOR {{ $year }}</h4>
            </div>
            <div class="col-sm-6 mt-sm-2">
                <ul class="breadcrumb breadcrumb-start float-sm-end">
                    <li class="d-flex">
                        <i class="ti ti-settings f-s-16"></i>
                        <a href="#" class="f-s-14 d-flex gap-2">
                            <span class="d-none d-md-block">Caja</span>
                        </a>
                    </li>
                    <li class="d-flex active">
                        <a href="#" class="f-s-14">Re Esp Sal Pag Cont</a>
                    </li>
                </ul>
            </div>
        </div>



    <div class="row">
        <div class="col-md-12">
           <div class="card">

               <div class="card-body">
                   <div class="row my-2 d-flex justify-content-end">
                       <div class="col-12 d-flex justify-content-end">

                           <select id="year" class="form-select w-80 mg-e-10" wire:model.live="year">
                               @for ($y = 2015; $y <= 2030; $y++)
                                   <option value="{{ $y }}">{{ $y }}</option>
                               @endfor
                           </select>
                           <button class="btn btn-sm btn-primary mg-e-10" wire:click="export">
                               <i class="ti ti-file-analytics f-s-12"></i>
                           </button>

                           <button class="btn btn-sm btn-primary" id="down">
                               <i class="ti ti-square-chevrons-down f-s-12"></i>
                           </button>
                       </div>



                   </div>
                 <div class="table-responsive">
                     <table class="table table-bordered table-striped">
                         <thead class="bg-primary">
                         <tr>
                             <th>CONTROL.</th>
                             <th colspan="2">SEDE</th>
                             @php
                                 $months = ['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
                             @endphp
                             @foreach ($months as $m)
                                 <th>{{ $m }}</th>
                             @endforeach
                             <th>TOTAL</th>
                         </tr>
                         </thead>
                         <tbody>
                         @forelse ($this->rows as $ctrl)
                             <tr>
                                 <th class="bg-primary text-white" rowspan="{{ count($ctrl['paraderos']) + 3 }}">
                                     {{ $ctrl['controlador'] }}
                                 </th>
                             @php $first = true; @endphp
                             @foreach ($ctrl['paraderos'] as $p)
                                 @if (!$first)
                                     <tr>
                                         @endif
                                         <th class="bg-primary text-white">{{ $p['sucursal'] }}</th>
                                         <th class="bg-primary text-white">Ingr. Sal1.</th>
                                         @for ($m=1; $m<=12; $m++)
                                             <td>{{ number_format($p['ingresos_mes'][$m], 2, '.', ',') }}</td>
                                         @endfor
                                         <td class="text-center font-semibold">{{ number_format($p['total'], 2, '.', ',') }}</td>
                                     </tr>
                                     @php $first = false; @endphp
                                     @endforeach

                                     {{-- Egreso Pago --}}
                                     <tr>
                                         <th class="bg-primary text-white" colspan="2">Egreso Pago</th>
                                         @for ($m=1; $m<=12; $m++)
                                             <td>
                                                 {{ number_format($ctrl['egreso_pago'][$m], 2, '.', ',') }}
                                             </td>
                                         @endfor
                                         <td>
                                             {{ number_format($ctrl['tot_egr_pago'], 2, '.', ',') }}
                                         </td>
                                     </tr>

                                     {{-- Egreso Draco --}}
                                     <tr>
                                         <th class="bg-primary text-white" colspan="2">Egreso Draco</th>
                                         @for ($m=1; $m<=12; $m++)
                                             <td>
                                                 {{ number_format($ctrl['egreso_draco'][$m], 2, '.', ',') }}
                                             </td>
                                         @endfor
                                         <td>
                                             {{ number_format($ctrl['tot_egr_draco'], 2, '.', ',') }}
                                         </td>
                                     </tr>

                                     {{-- Saldo --}}
                                     <tr>
                                         <th class="bg-primary title-modules" colspan="2">Saldo</th>
                                         @for ($m=1; $m<=12; $m++)
                                             <td id="saldo" class="title-modules">
                                                 <strong>{{ number_format($ctrl['saldos'][$m], 2, '.', ',') }}</strong>
                                             </td>
                                         @endfor
                                         <td class="text-center font-semibold">
                                             <strong>{{ number_format($ctrl['tot_saldo'], 2, '.', ',') }}</strong>
                                         </td>
                                     </tr>
                                     @empty
                                         <tr>
                                             <td colspan="16">
                                                 Sin datos para {{ $year }}.
                                             </td>
                                         </tr>
                                     @endforelse


                         </tbody>
                         <tfoot class="bg-primary">
                         @if (!empty($this->rows))
                             <tr>
                                 <td colspan="3"><strong>SALDO A FAVOR</strong></td>
                                 @for ($m=1; $m<=12; $m++)
                                     <td>
                                         <strong>{{ number_format($this->totalesSaldoMes[$m] ?? 0, 2, '.', ',') }}</strong>
                                     </td>
                                 @endfor
                                 <td>
                                     <strong>{{ number_format($this->totalSaldoFavor, 2, '.', ',') }}</strong>
                                 </td>
                             </tr>
                         @endif
                         </tfoot>
                     </table>
                     <table class="table table-bordered table-striped">
                         <thead class="bg-primary">
                         <tr>
                             <th rowspan="4">Item</th>
                             <th rowspan="4">Mes</th>
                             <th>Luis</th>
                             <th>Elmer</th>
                             <th rowspan="4">Diferencia</th>
                             <th>Luis</th>
                             <th>Elmer</th>
                             <th rowspan="4">Diferencia</th>
                             <th rowspan="4">Diferencia Huaycan / La victoria</th>
                         </tr>
                         <tr>
                             <th>Huaycan</th>
                             <th>Huaycan</th>
                             <th>La victoria</th>
                             <th>La victoria</th>
                         </tr>
                         <tr>
                             <th>Ingreso</th>
                             <th>Ingreso</th>
                             <th>Ingreso</th>
                             <th>Ingreso</th>
                         </tr>
                         <tr>
                             <th>Salida</th>
                             <th>Salida</th>
                             <th>Salida</th>
                             <th>Salida</th>
                         </tr>
                         </thead>
                         <tbody>
                         @foreach ($this->comparativo as $r)
                             <tr>
                                 <td><b>{{ $r['item'] }}</b></td>
                                 <td><b>{{ $r['mes'] }}</b></td>

                                 <td>{{ number_format($r['a_h'], 2, '.', ',') }}</td>
                                 <td>{{ number_format($r['b_h'], 2, '.', ',') }}</td>
                                 <td>{{ number_format($r['dif_h'], 2, '.', ',') }}</td>

                                 <td>{{ number_format($r['a_v'], 2, '.', ',') }}</td>
                                 <td>{{ number_format($r['b_v'], 2, '.', ',') }}</td>
                                 <td>{{ number_format($r['dif_v'], 2, '.', ',') }}</td>

                                 <td>{{ number_format($r['dif_h_vs_v'], 2, '.', ',') }}</td>
                             </tr>
                         @endforeach

                         </tbody>
                         <tfoot>
                         @if (!empty($this->comparativo))
                             <tr class="bg-primary">
                                 <td colspan="2">Total</td>
                                 <td>{{ number_format($this->comparativoTotales['a_h'], 2, '.', ',') }}</td>
                                 <td>{{ number_format($this->comparativoTotales['b_h'], 2, '.', ',') }}</td>
                                 <td>{{ number_format($this->comparativoTotales['dif_h'], 2, '.', ',') }}</td>

                                 <td>{{ number_format($this->comparativoTotales['a_v'], 2, '.', ',') }}</td>
                                 <td>{{ number_format($this->comparativoTotales['b_v'], 2, '.', ',') }}</td>
                                 <td>{{ number_format($this->comparativoTotales['dif_v'], 2, '.', ',') }}</td>

                                 <td>{{ number_format($this->comparativoTotales['dif_h_vs_v'], 2, '.', ',') }}</td>
                             </tr>
                         @endif
                         </tfoot>
                     </table>
                 </div>
               </div>
           </div>
        </div>
    </div>
        <div class="screen-overlay"
             wire:loading.delay.flex
             wire:target="year,export">
            <div class="text-center">
                <div class="spinner-border text-light" role="status" aria-label="Cargando…"></div>
                <div class="mt-2 text-white fw-semibold">Cargando…</div>
            </div>
        </div>
</div>
