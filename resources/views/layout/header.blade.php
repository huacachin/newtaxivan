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
                          <i class="ti ti-category"></i>
                        </span>

                        <div class="header-searchbar">
                          <form class="me-3 app-form app-icon-form " action="#">
                            <div class="position-relative">
                              <input type="search" class="form-control" placeholder="Search..." aria-label="Search">
                              <i class="ti ti-search text-dark"></i>
                            </div>
                          </form>
                        </div>
                      </div>

                      <div class="col-6 d-flex align-items-center justify-content-end header-right">
                        <ul class="d-flex align-items-center">
                          <li class="header-search">
                            <a href="#" class="d-block head-icon" role=button data-bs-toggle="offcanvas"
                              data-bs-target="#offcanvasTop" aria-controls="offcanvasTop">
                              <i class="ti ti-search"></i>
                            </a>

                            <div class="offcanvas offcanvas-top search-canvas" tabindex="-1" id="offcanvasTop">
                              <div class="offcanvas-body">
                                <div class="d-flex align-items-center">
                                  <div class="flex-grow-1">
                                    <form class="me-3 app-form app-icon-form " action="#">
                                      <div class="position-relative">
                                        <input type="search" class="form-control" placeholder="Search..."
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

                          <li class="header-notification">
                            <div class="flex-shrink-0 app-dropdown">
                              <a href="#" class="d-block head-icon position-relative" data-bs-toggle="dropdown"
                                data-bs-auto-close="outside" aria-expanded="false">
                                <i class="ti ti-bell"></i>
                                <span
                                  class="position-absolute translate-middle p-1 bg-success border border-light rounded-circle animate__animated animate__fadeIn animate__infinite animate__slower"></span>
                              </a>
                              <div class="dropdown-menu dropdown-menu-end bg-transparent border-0">
                                <div class="card">
                                  <div class="card-header bg-primary">
                                    <h5 class="text-white">Notification <span class="float-end">
                                        <i class="ti ti-bell text-white"></i></span></h5>
                                  </div>
                                  <div class="card-body p-0">
                                    <div class="head-container app-scroll">
                                      <div class="head-box">
                                        <span class="bg-secondary h-35 w-35 d-flex-center b-r-50 position-relative">
                                          <img src="{{asset('assets/images/ai_avtar/6.jpg')}}" alt="" class="img-fluid b-r-50">
                                          <span
                                            class="position-absolute bottom-0 end-0 p-1 bg-secondary border border-light rounded-circle"></span>
                                        </span>
                                        <div class="flex-grow-1 ps-2">
                                          <h6 class="mb-0 ">Gene Hart</h6>
                                          <p class="text-secondary f-s-13"> New account created</p>
                                        </div>
                                        <div class="text-end">
                                          <i class="ti ti-x text-dark f-s-15 close-btn"></i>
                                          <p class="f-s-12 text-muted">sep 23</p>
                                        </div>
                                      </div>
                                      <div class="head-box">
                                        <span class="text-light-primary h-40 w-40 d-flex-center b-r-50">
                                          <i class="ti ti-gift text-primary f-s-22"></i>
                                        </span>
                                        <div class="flex-grow-1 ps-2">
                                          <h6 class="mb-0">Gift-Voucher</h6>
                                          <p class="text-secondary f-s-13">50% sale active</p>
                                        </div>
                                        <div class="text-end">
                                          <i class="ti ti-x text-dark f-s-15 close-btn"></i>
                                          <p class="f-s-12 text-muted">min 02</p>
                                        </div>
                                      </div>
                                      <div class="head-box">
                                        <span class="bg-secondary h-35 w-35 d-flex-center b-r-50 position-relative">
                                          <img src="{{asset('assets/images/ai_avtar/4.jpg')}}" alt="" class="img-fluid b-r-50">
                                          <span
                                            class="position-absolute bottom-0 end-0 p-1 bg-success border border-light rounded-circle"></span>
                                        </span>
                                        <div class="flex-grow-1 ps-2">
                                          <h6 class="mb-0">Simon Young</h6>
                                          <p class="text-secondary f-s-13">Hello ..!</p>
                                        </div>
                                        <div class="text-end">
                                          <i class="ti ti-x text-dark f-s-15 close-btn"></i>
                                          <p class="f-s-12 text-muted">Oct 10</p>
                                        </div>
                                      </div>
                                      <div class="head-box">
                                        <span class="text-light-success h-40 w-40 d-flex-center b-r-50">
                                          <i class="ti ti-shopping-cart text-success f-s-22"></i>
                                        </span>
                                        <div class="flex-grow-1 ps-2">
                                          <h6 class="mb-0">Order Massage</h6>
                                          <p class="text-secondary f-s-13">Purchase ecommerce..</p>
                                        </div>
                                        <div class="text-end">
                                          <i class="ti ti-x text-dark f-s-15 close-btn"></i>
                                          <p class="f-s-12 text-muted">day 4</p>
                                        </div>
                                      </div>
                                      <div class="hidden-massage py-4 px-3">
                                        <img src="{{asset('assets/images/icons/bell.png')}}" class="w-50 h-50 mb-3 mt-2" alt="">
                                        <div>
                                          <h6 class="mb-0">Notification Not Found</h6>
                                          <p class="text-secondary">When you have any notifications added here,will
                                            appear here.
                                          </p>
                                        </div>
                                      </div>
                                    </div>
                                  </div>
                                  <div class="card-footer">
                                    <button type="button" class="btn btn-primary w-100">
                                      <i class="ti ti-plus"></i> View All</button>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </li>

                          <li class="header-profile">
                            <div class="flex-shrink-0 dropdown">
                              <a href="#" class="d-block head-icon pe-0" data-bs-toggle="dropdown"
                                aria-expanded="false">
                                <img src="{{asset('assets/images/avtar/woman.jpg')}}" alt="mdo" class="rounded-circle h-35 w-35">
                              </a>
                              <ul class="dropdown-menu dropdown-menu-end header-card border-0 px-2">
                                <li class="dropdown-item d-flex align-items-center p-2">
                                  <span class="h-35 w-35 d-flex-center b-r-50 position-relative">
                                    <img src="{{asset('assets/images/avtar/woman.jpg')}}" alt="" class="img-fluid b-r-50">
                                    <span
                                      class="position-absolute top-0 end-0 p-1 bg-success border border-light rounded-circle animate__animated animate__fadeIn animate__infinite animate__fast"></span>
                                  </span>
                                  <div class="flex-grow-1 ps-2">
                                    <h6 class="mb-0"> Ninja Monaldo</h6>
                                    <p class="f-s-12 mb-0 text-secondary">Web Designer</p>
                                  </div>
                                </li>

                                <li class="app-divider-v dotted py-1"></li>
                                <li>
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
                                <li class="app-divider-v dotted py-1"></li>
                                <li class="btn-light-danger b-r-5">
                                    <form action="{{ route('logout') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="dropdown-item mb-0 text-danger">
                                            <i class="ti ti-logout pe-1 f-s-18 text-danger"></i> Log Out
                                        </button>
                                    </form>
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
