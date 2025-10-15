<div class="container-fluid">
    <div class="flex items-center gap-3">
        <div class="row">
            <div class="col-sm-6">
                <h4 class="main-title">REPORTE ESTADÍSTICO DE SALIDAS–PAGOS DE CONTROLADOR {{ $year }}</h4>
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
               <div class="card-header">
                   <div class="row d-flex justify-content-end">
                       <div class="col-md-4">
                           <label for="year" class="text-sm">Año</label>
                           <select id="year" class="form-select" wire:model.live="year">
                               @for ($y = 2015; $y <= 2030; $y++)
                                   <option value="{{ $y }}">{{ $y }}</option>
                               @endfor
                           </select>
                       </div>


                      <div class="col-md-2 d-flex align-items-end">
                          <button class="btn btn-primary w-100" wire:click="consultar">
                              Exportar
                          </button>
                      </div>

                   </div>
               </div>
               <div class="card-body">
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
                                         <th class="bg-primary text-white">Ingr. Sal.</th>
                                         @for ($m=1; $m<=12; $m++)
                                             <td class="text-center">{{ number_format($p['ingresos_mes'][$m], 2, '.', ',') }}</td>
                                         @endfor
                                         <td class="text-center font-semibold">{{ number_format($p['total'], 2, '.', ',') }}</td>
                                     </tr>
                                     @php $first = false; @endphp
                                     @endforeach

                                     {{-- Egreso Pago --}}
                                     <tr>
                                         <th class="bg-primary text-white" colspan="2">Egreso Pago</th>
                                         @for ($m=1; $m<=12; $m++)
                                             <td class="text-center">
                                                 {{ number_format($ctrl['egreso_pago'][$m], 2, '.', ',') }}
                                             </td>
                                         @endfor
                                         <td class="text-center">
                                             {{ number_format($ctrl['tot_egr_pago'], 2, '.', ',') }}
                                         </td>
                                     </tr>

                                     {{-- Egreso Draco --}}
                                     <tr>
                                         <th class="bg-primary text-white" colspan="2">Egreso Draco</th>
                                         @for ($m=1; $m<=12; $m++)
                                             <td class="text-center">
                                                 {{ number_format($ctrl['egreso_draco'][$m], 2, '.', ',') }}
                                             </td>
                                         @endfor
                                         <td class="text-center">
                                             {{ number_format($ctrl['tot_egr_draco'], 2, '.', ',') }}
                                         </td>
                                     </tr>

                                     {{-- Saldo --}}
                                     <tr>
                                         <th class="bg-primary" colspan="2">Saldo</th>
                                         @for ($m=1; $m<=12; $m++)
                                             <td class="text-center" id="saldo">
                                                 <strong>{{ number_format($ctrl['saldos'][$m], 2, '.', ',') }}</strong>
                                             </td>
                                         @endfor
                                         <td class="text-center font-semibold">
                                             <strong>{{ number_format($ctrl['tot_saldo'], 2, '.', ',') }}</strong>
                                         </td>
                                     </tr>
                                     @empty
                                         <tr>
                                             <td colspan="16" class="text-center">
                                                 Sin datos para {{ $year }}.
                                             </td>
                                         </tr>
                                     @endforelse

                                     @if (!empty($this->rows))
                                         <tr>
                                             <td colspan="3"><strong>SALDO A FAVOR</strong></td>
                                             @for ($m=1; $m<=12; $m++)
                                                 <td class="text-center">
                                                     <strong>{{ number_format($this->totalesSaldoMes[$m] ?? 0, 2, '.', ',') }}</strong>
                                                 </td>
                                             @endfor
                                             <td class="text-center">
                                                 <strong>{{ number_format($this->totalSaldoFavor, 2, '.', ',') }}</strong>
                                             </td>
                                         </tr>
                                     @endif
                         </tbody>
                     </table>
                 </div>
               </div>
           </div>
        </div>
    </div>

    {{-- Comparativo Luis vs Elmer --}}
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped w-50">
                            <thead class="bg-primary">
                            <tr>
                                <th rowspan="4" class="p-2 border">Item</th>
                                <th rowspan="4" class="p-2 border">Mes</th>
                                <th class="p-2 border">Luis</th>
                                <th class="p-2 border">Elmer</th>
                                <th rowspan="4" class="p-2 border">Diferencia</th>
                                <th class="p-2 border">Luis</th>
                                <th class="p-2 border">Elmer</th>
                                <th rowspan="4" class="p-2 border">Diferencia</th>
                                <th rowspan="4" class="p-2 border">Diferencia Huaycan / La victoria</th>
                            </tr>
                            <tr>
                                <th class="p-2 border">Huaycan</th>
                                <th class="p-2 border">Huaycan</th>
                                <th class="p-2 border">La victoria</th>
                                <th class="p-2 border">La victoria</th>
                            </tr>
                            <tr>
                                <th class="p-2 border">Ingreso</th>
                                <th class="p-2 border">Ingreso</th>
                                <th class="p-2 border">Ingreso</th>
                                <th class="p-2 border">Ingreso</th>
                            </tr>
                            <tr>
                                <th class="p-2 border">Salida</th>
                                <th class="p-2 border">Salida</th>
                                <th class="p-2 border">Salida</th>
                                <th class="p-2 border">Salida</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($this->comparativo as $r)
                                <tr>
                                    <td class="border p-2"><b>{{ $r['item'] }}</b></td>
                                    <td class="border p-2"><b>{{ $r['mes'] }}</b></td>

                                    <td class="border p-2 text-right">{{ number_format($r['a_h'], 2, '.', ',') }}</td>
                                    <td class="border p-2 text-right">{{ number_format($r['b_h'], 2, '.', ',') }}</td>
                                    <td class="border p-2 text-right text-red-600">{{ number_format($r['dif_h'], 2, '.', ',') }}</td>

                                    <td class="border p-2 text-right">{{ number_format($r['a_v'], 2, '.', ',') }}</td>
                                    <td class="border p-2 text-right">{{ number_format($r['b_v'], 2, '.', ',') }}</td>
                                    <td class="border p-2 text-right text-red-600">{{ number_format($r['dif_v'], 2, '.', ',') }}</td>

                                    <td class="border p-2 text-right">{{ number_format($r['dif_h_vs_v'], 2, '.', ',') }}</td>
                                </tr>
                            @endforeach

                            @if (!empty($this->comparativo))
                                <tr class="bg-slate-100 font-semibold">
                                    <td class="border p-2" colspan="2">Total</td>
                                    <td class="border p-2 text-right">{{ number_format($this->comparativoTotales['a_h'], 2, '.', ',') }}</td>
                                    <td class="border p-2 text-right">{{ number_format($this->comparativoTotales['b_h'], 2, '.', ',') }}</td>
                                    <td class="border p-2 text-right text-red-600">{{ number_format($this->comparativoTotales['dif_h'], 2, '.', ',') }}</td>

                                    <td class="border p-2 text-right">{{ number_format($this->comparativoTotales['a_v'], 2, '.', ',') }}</td>
                                    <td class="border p-2 text-right">{{ number_format($this->comparativoTotales['b_v'], 2, '.', ',') }}</td>
                                    <td class="border p-2 text-right text-red-600">{{ number_format($this->comparativoTotales['dif_v'], 2, '.', ',') }}</td>

                                    <td class="border p-2 text-right">{{ number_format($this->comparativoTotales['dif_h_vs_v'], 2, '.', ',') }}</td>
                                </tr>
                            @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
