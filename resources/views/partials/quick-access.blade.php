{{-- Botones flotantes de acceso rápido --}}
@auth
<div class="quick-access">
    @if(!request()->routeIs('departures.index'))
        @can('departures')
        <a href="{{ route('departures.index') }}" class="quick-access-btn qa-departures" title="Salidas">
            <span>Sal.</span>
        </a>
        @endcan
    @endif

    @if(!request()->routeIs('payments.index'))
        @can('payments')
        <a href="{{ route('payments.index') }}" class="quick-access-btn qa-payments" title="Pagos">
            <span>Pag.</span>
        </a>
        @endcan
    @endif

    @if(!request()->routeIs('cash.incomes'))
        @can('cash.incomes')
        <a href="{{ route('cash.incomes') }}" class="quick-access-btn qa-incomes" title="Ingresos">
            <span>Ing.</span>
        </a>
        @endcan
    @endif
</div>
@endauth
