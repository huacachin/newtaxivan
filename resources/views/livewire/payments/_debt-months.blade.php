{{-- Pago DEUDA: deuda pendiente por mes. El pago se descuenta del mes más antiguo
     al más reciente (App\Services\DebtPaymentAllocator); la columna "Este pago"
     se calcula en el navegador con el monto escrito (debtPreview en custom.js). --}}
@once
    <style>
        .debt-months { max-width: 340px; margin-top: 4px; font-size: 12px; }
        .debt-months__table { width: 100%; border-collapse: collapse; margin: 2px 0; }
        .debt-months__table th { font-weight: 600; color: #6b7280; border-bottom: 1px solid #e5e7eb; padding: 2px 4px; }
        .debt-months__table td { padding: 2px 4px; border-bottom: 1px dashed #eef0f3; white-space: nowrap; }
        .debt-months__table tr.is-paid td { color: #15803d; }
        .debt-months__table tr.is-partial td { color: #b45309; font-weight: 600; }
    </style>
@endonce

@if(!is_null($pending_debt))
    @if($pending_debt <= 0)
        <small class="text-warning">Esta placa no tiene deuda pendiente.</small>
    @else
        <div class="debt-months" wire:key="debt-months-{{ md5(json_encode($debtMonths)) }}"
             x-data="debtPreview(@js(array_map(fn ($month) => (float) $month['pending'], $debtMonths)))">
            <div>Deuda pendiente: <b>S/ {{ number_format($pending_debt, 2) }}</b></div>
            <table class="debt-months__table">
                <thead>
                    <tr><th>Mes</th><th class="text-end">Debe</th><th class="text-end">Este pago</th></tr>
                </thead>
                <tbody>
                    @foreach($debtMonths as $i => $month)
                        <tr :class="rowClass({{ $i }})">
                            <td>{{ $month['label'] }}</td>
                            <td class="text-end">{{ number_format($month['pending'], 2) }}</td>
                            <td class="text-end" x-text="cell({{ $i }})">—</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <small class="text-muted d-block">Se descuenta primero del mes más antiguo.</small>
            <div class="text-danger small" x-show="excess() > 0" x-cloak x-text="'Excede la deuda en S/ ' + money(excess())"></div>
        </div>
    @endif
@endif
