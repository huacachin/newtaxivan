<!-- Header Section starts -->
<header class="header-main">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6 d-flex align-items-center header-left">
                                <span class="header-toggle me-3">
                                  <i class="ti ti-menu"></i>
                                </span>
                            </div>

                            <div class="col-6 d-flex align-items-center justify-content-end header-right">
                                <ul class="d-flex align-items-center">
                                    <li class="header-search">
                                        <button type="button" class="d-block head-icon" data-bs-toggle="offcanvas"
                                                data-bs-target="#offcanvasTop" aria-controls="offcanvasTop" aria-label="Buscar">
                                            <i class="ti ti-search"></i>
                                        </button>

                                        <div class="offcanvas offcanvas-top search-canvas" tabindex="-1"
                                             id="offcanvasTop">
                                            <div class="offcanvas-body">
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-grow-1">
                                                        <form class="me-3 app-form app-icon-form " action="#">
                                                            <div class="position-relative">
                                                                <input type="search" class="form-control"
                                                                       placeholder="Search..."
                                                                       aria-label="Search">
                                                                <i class="ti ti-search f-s-15"></i>
                                                            </div>
                                                        </form>
                                                    </div>
                                                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                                                            aria-label="Close"></button>
                                                </div>
                                            </div>
                                        </div>
                                    </li>

                                    <li class="header-dark head-icon">
                                        <div class="sun-logo">
                                            <i class="ti ti-moon-off"></i>
                                        </div>
                                        <div class="moon-logo">
                                            <i class="ti ti-moon-filled"></i>
                                        </div>
                                    </li>

                                    <livewire:header.expiring-dropdown />


                                    <li class="header-profile">
                                        <div class="flex-shrink-0 dropdown">
                                            <button type="button" class="d-block head-icon pe-0" data-bs-toggle="dropdown"
                                                    aria-expanded="false" aria-label="Perfil">
                                                <img src="{{auth()->user()->avatar_url}}" alt=""
                                                     class="rounded-circle h-35 w-35">
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end header-card border-0 px-2">
                                                <li class="dropdown-item d-flex align-items-center p-2">
                                  <span class="h-35 w-35 d-flex-center b-r-50 position-relative">
                                    <img src="{{auth()->user()->avatar_url}}" alt=""
                                         class="img-fluid b-r-50">
                                    <span
                                        class="position-absolute top-0 end-0 p-1 bg-success border border-light rounded-circle animate__animated animate__fadeIn animate__infinite animate__fast"></span>
                                  </span>
                                                    <div class="flex-grow-1 ps-2">
                                                        <h6 class="mb-0"> {{auth()->user()->name}}</h6>
                                                        <p class="f-s-12 mb-0 text-secondary">{{auth()->user()->roles->first()->name}}</p>
                                                    </div>
                                                </li>

                                                <li class="app-divider-v dotted py-1"></li>
                                                <!--li>
                                                    <a class="dropdown-item" href="{{route('logout')}}">
                                                        <i class="ti ti-user-circle pe-1 f-s-18"></i> Profile Detaiils
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#">
                                                        <i class="ti ti-notification pe-1 f-s-18"></i> Notification
                                                    </a>
                                                </li>

                                                <li class="app-divider-v dotted py-1"></li>
                                                <li>
                                                    <a class="dropdown-item" href="#">
                                                        <i class="ti ti-help pe-1 f-s-18"></i> Help
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="{{('faq')}}">
                                                        <i class="ti ti-file-dollar pe-1 f-s-18"></i> FAQ
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="{{route('dashboard.index')}}">
                                                        <i class="ti ti-currency-dollar pe-1 f-s-18"></i> Pricing
                                                    </a>
                                                </li>
                                                <li class="app-divider-v dotted py-1"></li-->
                                                <li class="btn-light-danger b-r-5">
                                                    <livewire:auth.logout />
                                                </li>

                                            </ul>
                                        </div>

                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
<!-- Header Section ends -->
