{{-- Aviso: la placa tiene deuda de meses anteriores, con cualquier tipo de pago.
     Con tipo Deuda no se muestra: debajo del monto se elige el mes (_debt-months).
     Un pago es de un solo mes: tocar un mes lo cobra (AddPayment::payDebtMonth, tipo
     Deuda con lo que debe ese mes); "Cobrar deuda" empieza por el más antiguo. --}}
@once
    <style>
        .debt-alert {
            display: flex; align-items: flex-start; gap: 12px;
            max-width: 760px; margin-bottom: 1rem; padding: 12px 14px;
            border: 1px solid rgba(var(--warning), .55); border-left: 5px solid rgb(var(--warning));
            border-radius: 12px;
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            box-shadow: 0 8px 20px -14px rgba(146, 64, 14, .6);
            animation: debt-alert-in .25s ease-out;
        }
        .debt-alert__icon {
            flex: 0 0 40px; height: 40px; border-radius: 50%;
            display: grid; place-items: center;
            background: rgb(var(--warning)); color: #fff; font-size: 21px;
            box-shadow: 0 0 0 5px rgba(var(--warning), .22);
        }
        .debt-alert__body { flex: 1 1 0; min-width: 0; }
        .debt-alert__head { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; column-gap: 12px; }
        .debt-alert__title { color: #78350f; font-size: 14px; font-weight: 700; line-height: 1.35; }
        .debt-alert__plate { letter-spacing: .04em; }
        .debt-alert__count { font-weight: 400; white-space: nowrap; }
        .debt-alert__total { color: #92400e; font-size: 22px; font-weight: 800; line-height: 1.15; white-space: nowrap; }
        .debt-alert__total small { font-size: 12px; font-weight: 600; opacity: .8; }
        .debt-alert__months { display: flex; flex-wrap: wrap; gap: 6px; margin: 8px 0 0; padding: 0; list-style: none; }
        .debt-alert__month {
            display: inline-flex; align-items: baseline; gap: 6px; padding: 3px 10px;
            border: 1px solid rgba(var(--warning), .7); border-radius: 999px;
            background: #fff; color: #78350f; font: inherit; font-size: 12px; white-space: nowrap; cursor: pointer;
            transition: background-color .15s, border-color .15s;
        }
        .debt-alert__month:hover, .debt-alert__month:focus-visible { background: #fef3c7; border-color: rgb(var(--warning)); }
        .debt-alert__month b { font-weight: 700; }
        .debt-alert__hint { margin-top: 8px; color: #92400e; font-size: 12px; }
        .debt-alert__action { align-self: center; font-weight: 600; white-space: nowrap; }
        @keyframes debt-alert-in { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) { .debt-alert { animation: none; } }
        @media (max-width: 767.98px) {
            .debt-alert { flex-wrap: wrap; }
            .debt-alert__icon { flex-basis: 34px; height: 34px; font-size: 18px; box-shadow: 0 0 0 4px rgba(var(--warning), .22); }
            .debt-alert__action { width: 100%; min-height: 42px; }
        }
    </style>
@endonce

@php $monthCount = count($debtMonths); @endphp
<div class="debt-alert" role="alert">
    <div class="debt-alert__icon" aria-hidden="true"><i class="ti ti-alert-triangle"></i></div>

    <div class="debt-alert__body">
        <div class="debt-alert__head">
            <div class="debt-alert__title">
                <span class="debt-alert__plate">{{ $plate }}</span> tiene deuda pendiente
                <span class="debt-alert__count">· {{ $monthCount }} {{ $monthCount === 1 ? 'mes' : 'meses' }}</span>
            </div>
            <div class="debt-alert__total"><small>S/</small> {{ number_format($pending_debt, 2) }}</div>
        </div>

        <ul class="debt-alert__months">
            @foreach($debtMonths as $month)
                <li wire:key="debt-alert-month-{{ $month['id'] }}">
                    <button type="button" class="debt-alert__month" title="Cobrar {{ $month['label'] }}"
                            wire:click="payDebtMonth({{ $month['id'] }})" wire:loading.attr="disabled">
                        {{ $month['label'] }} <b>S/ {{ number_format($month['pending'], 2) }}</b>
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="debt-alert__hint">Se cobra con el tipo <b>Deuda</b>, <b>un pago por cada mes</b>. Toca el mes que paga.</div>
    </div>

    <button type="button" class="btn btn-sm btn-warning debt-alert__action"
            wire:click="payDebtMonth({{ $debtMonths[0]['id'] }})" wire:loading.attr="disabled">
        <i class="ti ti-cash"></i> Cobrar deuda
    </button>
</div>
