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

                           <select id="year" class="form-select form-select-sm w-80 mg-e-10" wire:model="year">
                               @for ($y = 2015; $y <= 2030; $y++)
                                   <option value="{{ $y }}">{{ $y }}</option>
                               @endfor
                           </select>





                           <button class="btn btn-sm btn-dark flex-shrink-0 align-self-end mg-e-10" wire:click="$refresh">
                                    <i class="ti ti-search f-s-12"></i>
                                </button>
                           <a href="{{ route('departures.index') }}" class="btn btn-sm btn-primary mg-e-10">
                               <i class="ti ti-arrow-back-up f-s-12"></i>
                           </a>
                           <button class="btn btn-sm btn-export mg-e-10" wire:click="export">
                               <i class="ti ti-file-analytics f-s-12"></i>
                           </button>
                           <button class="btn btn-sm btn-primary" id="down">
                               <i class="fa-solid fa-angle-down"></i>
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
                                             <td style="{{ $p['ingresos_mes'][$m] < 0 ? 'color:red !important;font-weight:bold;' : 'color:#000 !important;' }}">{{ number_format($p['ingresos_mes'][$m], 2, '.', ',') }}</td>
                                         @endfor
                                         <td style="{{ $p['total'] < 0 ? 'color:red !important;font-weight:bold;' : 'color:#000 !important;' }}">{{ number_format($p['total'], 2, '.', ',') }}</td>
                                     </tr>
                                     @php $first = false; @endphp
                                     @endforeach

                                     {{-- Egreso Pago --}}
                                     <tr>
                                         <th class="bg-primary text-white" colspan="2">Egreso Pago</th>
                                         @for ($m=1; $m<=12; $m++)
                                             <td style="color:red !important;font-weight:bold;">
                                                 {{ number_format($ctrl['egreso_pago'][$m], 2, '.', ',') }}
                                             </td>
                                         @endfor
                                         <td style="color:red !important;font-weight:bold;">
                                             {{ number_format($ctrl['tot_egr_pago'], 2, '.', ',') }}
                                         </td>
                                     </tr>

                                     {{-- Egreso Draco --}}
                                     <tr>
                                         <th class="bg-primary text-white" colspan="2">Egreso Draco</th>
                                         @for ($m=1; $m<=12; $m++)
                                             <td style="color:red !important;font-weight:bold;">
                                                 {{ number_format($ctrl['egreso_draco'][$m], 2, '.', ',') }}
                                             </td>
                                         @endfor
                                         <td style="color:red !important;font-weight:bold;">
                                             {{ number_format($ctrl['tot_egr_draco'], 2, '.', ',') }}
                                         </td>
                                     </tr>

                                     {{-- Saldo --}}
                                     <tr>
                                         <th class="bg-primary text-white" colspan="2">Saldo</th>
                                         @for ($m=1; $m<=12; $m++)
                                             <td style="{{ $ctrl['saldos'][$m] < 0 ? 'color:red !important;' : 'color:#000 !important;' }}font-weight:bold;">
                                                 {{ number_format($ctrl['saldos'][$m], 2, '.', ',') }}
                                             </td>
                                         @endfor
                                         <td style="{{ $ctrl['tot_saldo'] < 0 ? 'color:red !important;' : 'color:#000 !important;' }}font-weight:bold;">
                                             {{ number_format($ctrl['tot_saldo'], 2, '.', ',') }}
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
                         <tfoot>
                         @if (!empty($this->rows))
                             <tr style="background-color: #fff !important; color: #000 !important;">
                                 <td colspan="3" style="background-color: #fff !important; color: #000 !important;"><strong>SALDO A FAVOR</strong></td>
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
                                 <td style="color:red !important;font-weight:bold;">{{ number_format($r['dif_h'], 2, '.', ',') }}</td>

                                 <td>{{ number_format($r['a_v'], 2, '.', ',') }}</td>
                                 <td>{{ number_format($r['b_v'], 2, '.', ',') }}</td>
                                 <td style="color:red !important;font-weight:bold;">{{ number_format($r['dif_v'], 2, '.', ',') }}</td>

                                 <td>{{ number_format($r['dif_h_vs_v'], 2, '.', ',') }}</td>
                             </tr>
                         @endforeach

                         </tbody>
                         <tfoot>
                         @if (!empty($this->comparativo))
                             <tr style="background-color: #fff !important; color: #000 !important;">
                                 <td colspan="2" style="background-color: #fff !important; color: #000 !important;"><strong>Total</strong></td>
                                 <td>{{ number_format($this->comparativoTotales['a_h'], 2, '.', ',') }}</td>
                                 <td>{{ number_format($this->comparativoTotales['b_h'], 2, '.', ',') }}</td>
                                 <td style="color:red !important;font-weight:bold;">{{ number_format($this->comparativoTotales['dif_h'], 2, '.', ',') }}</td>

                                 <td>{{ number_format($this->comparativoTotales['a_v'], 2, '.', ',') }}</td>
                                 <td>{{ number_format($this->comparativoTotales['b_v'], 2, '.', ',') }}</td>
                                 <td style="color:red !important;font-weight:bold;">{{ number_format($this->comparativoTotales['dif_v'], 2, '.', ',') }}</td>

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
</div>
