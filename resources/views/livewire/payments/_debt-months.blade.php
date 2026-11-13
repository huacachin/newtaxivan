{{-- Pago DEUDA: un pago es de UN mes, el que se elige (cualquiera pendiente), y no puede pasar
     lo que debe ese mes (App\Services\DebtPaymentAllocator::forMonth). Lo que queda del mes se
     calcula en el navegador con el monto escrito (debtPreview en custom.js). --}}
@once
    <style>
        .debt-months { max-width: 360px; margin-top: 6px; font-size: 12px; }
        .debt-months__head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; column-gap: 8px; margin-bottom: 4px; }
        .debt-months__title { font-weight: 700; color: #374151; }
        .debt-months__total { color: #6b7280; }
        .debt-months__list { display: grid; gap: 4px; }
        .debt-month {
            display: flex; align-items: center; gap: 8px; min-height: 38px; margin: 0; padding: 6px 10px;
            border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; cursor: pointer;
            transition: border-color .15s, background-color .15s, box-shadow .15s;
        }
        .debt-month:hover { border-color: rgba(var(--warning), .8); }
        .debt-month.is-selected { border-color: rgb(var(--warning)); background: #fffbeb; box-shadow: 0 0 0 2px rgba(var(--warning), .25); }
        .debt-month input { flex: 0 0 auto; margin: 0; }
        .debt-month__label { font-weight: 600; color: #111827; }
        .debt-month__owe { margin-left: auto; color: #92400e; white-space: nowrap; }
        .debt-months__result { min-height: 18px; margin-top: 6px; font-weight: 600; }
        .debt-months__result.is-paid { color: #15803d; }
        .debt-months__result.is-partial { color: #b45309; }
        .debt-months__hint { margin-top: 2px; color: #6b7280; }
    </style>
@endonce

@if(!is_null($pending_debt))
    @if($pending_debt <= 0)
        <small class="text-warning">Esta placa no tiene deuda pendiente.</small>
    @else
        <div class="debt-months" wire:key="debt-months-{{ md5(json_encode($debtMonths)) }}"
             x-data="debtPreview(@js(collect($debtMonths)->mapWithKeys(fn ($month) => [$month['id'] => ['label' => $month['label'], 'pending' => (float) $month['pending']]])))">
            <div class="debt-months__head">
                <span class="debt-months__title">Mes que paga</span>
                <span class="debt-months__total">Debe en total S/ {{ number_format($pending_debt, 2) }} · {{ count($debtMonths) }} {{ count($debtMonths) === 1 ? 'mes' : 'meses' }}</span>
            </div>
            <div class="debt-months__list" role="radiogroup" aria-label="Mes que paga">
                @foreach($debtMonths as $month)
                    <label class="debt-month" wire:key="debt-month-{{ $month['id'] }}" :class="{ 'is-selected': isSelected({{ $month['id'] }}) }">
                        <input type="radio" class="form-check-input" name="debt_month_choice" value="{{ $month['id'] }}" wire:model.live="debt_month_id">
                        <span class="debt-month__label">{{ $month['label'] }}</span>
                        <span class="debt-month__owe">debe S/ {{ number_format($month['pending'], 2) }}</span>
                    </label>
                @endforeach
            </div>
            <div class="debt-months__result" :class="resultClass()" x-text="result()"></div>
            <div class="text-danger small" x-show="excess() > 0" x-cloak x-text="excessText()"></div>
            <small class="debt-months__hint d-block" x-text="hint()">Puede pagar una parte o todo el mes, como máximo lo que debe. Un pago por mes: los otros meses van en pagos aparte.</small>
        </div>
    @endif
@endif
@error('debt_month_id') <span class="title-modules d-block">{{ $message }}</span> @enderror
