{{-- resources/views/layouts/sidebar.blade.php --}}

@php
    // 1) Menú con condiciones de permiso
    $sidebarItems = [
        [ 'type'  => 'title',   'title' => 'Dashboard' ],

        [
            'id'    => 'dashboard-simple',
            'title' => 'Dashboard',
            'icon'  => 'ti ti-home',
            'route' => 'dashboard.index',
            'can'   => 'dashboard.view',
        ],

        [
            'id'       => 'settings',
            'title'    => 'Configuración',
            'icon'     => 'ti ti-settings',
            // visible si tiene al menos UNO de estos permisos:
            'canAny'   => ['vehicles.view','owners.view','drivers.view','cost-per-plate.view','users.view','concepts.view'],
            'children' => [
                ['title' => 'Vehículos',     'route' => 'settings.vehicles.index',       'can' => 'vehicles.view'],
                ['title' => 'Propietarios',  'route' => 'settings.owners.index',         'can' => 'owners.view'],
                ['title' => 'Conductores',   'route' => 'settings.drivers.index',        'can' => 'drivers.view'],
                ['title' => 'Costo Placa',   'route' => 'settings.cost-per-plate.index', 'can' => 'cost-per-plate.view'],
                ['title' => 'Usuarios',      'route' => 'settings.users.index',          'can' => 'users.view'],
                ['title' => 'Conceptos',     'route' => 'settings.concepts.index',       'can' => 'concepts.view'],
            ],
        ],

        [
            'id'    => 'departures',
            'title' => 'Salidas',
            'icon'  => 'ti ti-door-exit',
            'route' => 'departures.index',
            'can'   => 'departures.view',
        ],

        [
            'id'    => 'payments',
            'title' => 'Pagos',
            'icon'  => 'ti ti-currency-dollar',
            'route' => 'payments.index',
            'can'   => 'payments.view',
        ],

        [
            'id'       => 'debts',
            'title'    => 'Deuda',
            'icon'     => 'ti ti-currency-dollar-off',
            'canAny'   => ['debts.report','debts.view'],
            'children' => [
                ['title' => 'Deuda x Días',  'route' => 'debts.debt-per-days', 'can' => 'debts.report'],
                ['title' => 'Deuda Mensual', 'route' => 'debts.monthly',       'can' => 'debts.report'],
                // agrega más si los usas:
                // ['title' => 'Generar deuda', 'route' => 'debts.generate', 'can' => 'debts.create'],
                // ['title' => 'Eliminar deuda','route' => 'debts.delete',  'can' => 'debts.delete'],
            ],
        ],

        [
            'id'       => 'caja',
            'title'    => 'Caja',
            'icon'     => 'ti ti-home-dollar',
            'canAny'   => ['cash.view','cash.report'],
            'children' => [
                ['title' => 'Apertura Caja',         'route' => 'cash.open',                  'can' => 'cash.view'],
                ['title' => 'Ingreso',               'route' => 'cash.incomes',               'can' => 'cash.view'],
                ['title' => 'Egreso',                'route' => 'cash.expenses',              'can' => 'cash.view'],
                ['title' => 'Reporte Movimiento',    'route' => 'cash.report.movement',       'can' => 'cash.report'],
                ['title' => 'Reporte General',       'route' => 'cash.report.general',        'can' => 'cash.report'],
                ['title' => 'Rep Est Draco Base',    'route' => 'cash.report.est-draco-base', 'can' => 'cash.report'],
                ['title' => 'Rep Esp Sal Pag Cont',  'route' => 'cash.report.est-sal-pag-cont','can' => 'cash.report'],
                ['title' => 'Rep Est Caja M.A',      'route' => 'cash.report.est-caja-ma',     'can' => 'cash.report'],
            ],
        ],
    ];
@endphp

<nav class="dark-sidebar">
    <div class="app-logo">
        <a class="logo d-inline-block" href="{{ route('dashboard.index') }}">
            <img width="1000px" src="{{ asset('assets/images/logo/logo1.png') }}" alt="#" class="dark-logo">
        </a>
        <span class="bg-light-light toggle-semi-nav">
            <i class="ti ti-chevrons-right f-s-20"></i>
        </span>
    </div>

    <div class="app-nav" id="app-simple-bar">
        @if(!empty($sidebarItems))
            @include('partials.sidebar-menu', ['items' => $sidebarItems])
        @else
            <p class="text-center text-muted m-3">
                Menú vacío o mal definido.
            </p>
        @endif
    </div>

    <div class="menu-navs">
        <span class="menu-previous"><i class="ti ti-chevron-left"></i></span>
        <span class="menu-next"><i class="ti ti-chevron-right"></i></span>
    </div>
</nav>
