
<!-- latest jquery-->
<!-- TODO: optimizar esto, datepicker no debería ir con jquery -->
@if (! request()->routeIs([
    'departures.index',
    'payments.index',
    'cash.incomes',
    'cash.expenses',
    'settings.owners.create',
    'settings.owners.edit',
    'settings.drivers.create',
    'settings.drivers.edit',
    'settings.vehicles.create',
    'settings.vehicles.edit',
    'settings.vehicles.expiration',
    'settings.owners.expiration',
    'settings.drivers.expiration',
    'departures.edit',
    'audit.logs.index'
]))
    <script src="{{ asset('assets/js/jquery-3.6.3.min.js') }}"></script>
@endif

<!-- Bootstrap js-->
<script src="{{asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')}}"></script>

<!-- Simple bar js-->
<script src="{{asset('assets/vendor/simplebar/simplebar.js')}}"></script>


<!-- Customizer js-->
<script src="{{ versioned_asset('assets/js/customizer.js') }}"></script>

<!-- prism js-->
<script src="{{asset('assets/vendor/prism/prism.min.js')}}"></script>

<!-- App js-->
<script src="{{ versioned_asset('assets/js/script.js') }}"></script>


@stack('scripts')
@yield('script')
