{{-- resources/views/layouts/sidebar.blade.php --}}

@php
    // 1) Menú con condiciones de permiso
    $sidebarItems = [
        [ 'type'  => 'title',   'title' => '' ],

        [
            'id'    => 'dashboard-simple',
            'title' => 'Panel de control',
            'icon'  => 'ti ti-home',
            'route' => 'dashboard.index',
            'can'   => 'dashboard',
        ],

        [
            'id'       => 'settings',
            'title'    => 'Configuración',
            'icon'     => 'ti ti-settings',
            // visible si tiene al menos UNO de estos permisos:
            'canAny'   => ['configuracion.vehicles','configuracion.drivers','configuracion.owners','configuracion.cost-per-plate','configuracion.concepts','configuracion.headquarters'],
            'children' => [
                ['title' => 'Vehículos',     'route' => 'settings.vehicles.index',       'can' => 'configuracion.vehicles'],
                ['title' => 'Propietarios',  'route' => 'settings.owners.index',         'can' => 'configuracion.owners'],
                ['title' => 'Conductores',   'route' => 'settings.drivers.index',        'can' => 'configuracion.drivers'],
                ['title' => 'Costo Placa',   'route' => 'settings.cost-per-plate.index', 'can' => 'configuracion.cost-per-plate'],
                ['title' => 'Usuarios',      'route' => 'settings.users.index',          'can' => 'configuracion.users'],
                ['title' => 'Conceptos',     'route' => 'settings.concepts.index',       'can' => 'configuracion.concepts'],
                ['title' => 'Sucursales',    'route' => 'settings.headquarters.index',  'can' => 'configuracion.headquarters'],
            ],
        ],

        [
            'id'    => 'departures',
            'title' => 'Salidas',
            'icon'  => 'ti ti-door-exit',
            'route' => 'departures.index',
            'can'   => 'departures',
        ],

        [
            'id'    => 'payments',
            'title' => 'Pagos',
            'icon'  => 'ti ti-currency-dollar',
            'route' => 'payments.index',
            'can'   => 'payments',
        ],

        [
            'id'       => 'debts',
            'title'    => 'Deuda',
            'icon'     => 'ti ti-currency-dollar-off',
            'canAny'   => ['debts.day','debts.monthly'],
            'children' => [
                ['title' => 'Deuda x Días',  'route' => 'debts.debt-per-days', 'can' => 'debts.days'],
                ['title' => 'Deuda Mensual', 'route' => 'debts.monthly',       'can' => 'debts.monthly'],
                // agrega más si los usas:
                // ['title' => 'Generar deuda', 'route' => 'debts.generate', 'can' => 'debts.create'],
                // ['title' => 'Eliminar deuda','route' => 'debts.delete',  'can' => 'debts.delete'],
            ],
        ],

        [
            'id'       => 'caja',
            'title'    => 'Caja',
            'icon'     => 'ti ti-home-dollar',
            'canAny'   => ['cash.incomes','cash.expenses','cash.report-general','cash.report-draco','cash.report-sal-pag-cont','cash.report-caja-ma'],
            'children' => [
                ['title' => 'Ingreso Otros',         'route' => 'cash.incomes',                'can' => 'cash.incomes'],
                ['title' => 'Egreso',                'route' => 'cash.expenses',               'can' => 'cash.expenses'],
                ['title' => 'Reporte General',       'route' => 'cash.report.general',         'can' => 'cash.report-general'],
                ['title' => 'Rep Est Draco Base',    'route' => 'cash.report.est-draco-base',  'can' => 'cash.report-draco'],
                ['title' => 'Rep Esp Sal Pag Cont',  'route' => 'cash.report.est-sal-pag-cont','can' => 'cash.report-sal-pag-cont'],
                ['title' => 'Rep Est Caja M.A',      'route' => 'cash.report.est-caja-ma',     'can' => 'cash.report-caja-ma'],
            ],
        ],

        [
            'id'    => 'audit-logs',
            'title' => 'Auditoría',
            'icon'  => 'ti ti-clipboard-list',
            'route' => 'audit.logs.index',
            'role'  => 'director',
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
