{{-- Botones flotantes de acceso rápido (desktop/tablet) --}}
@auth
<div class="quick-access d-none d-md-flex">
    @if(!request()->routeIs('departures.index'))
        @can('departures')
        <a href="{{ route('departures.index') }}" class="quick-access-btn qa-departures" title="Salidas">
            <i class="ti ti-door-exit"></i>
            <span>Sal.</span>
        </a>
        @endcan
    @endif

    @if(!request()->routeIs('payments.index'))
        @can('payments')
        <a href="{{ route('payments.index') }}" class="quick-access-btn qa-payments" title="Pagos">
            <span class="qa-icon-text">S/.</span>
            <span>Pag.</span>
        </a>
        @endcan
    @endif

    @if(!request()->routeIs('cash.incomes'))
        @can('cash.incomes')
        <a href="{{ route('cash.incomes') }}" class="quick-access-btn qa-incomes" title="Ingresos">
            <i class="ti ti-home-dollar"></i>
            <span>Ing.</span>
        </a>
        @endcan
    @endif
</div>
@endauth
