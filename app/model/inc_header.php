<!-- Header -->
<header id="header" data-aos="fade">
    <!-- Header Topbar -->
    <div class="header-topbar">
        <div class="container">
            <div class="row g-0">
                <div class="col-6 col-xl-7 col-md-8">
                    <div class="d-flex align-items-center">
                            <i class="hicon hicon-telephone me-1"></i>
                            <span>+51 965-654-987</span>
                        <span class="vr bg-white d-none d-md-inline ms-3 me-3"></span>
                            <i class="hicon hicon-email-envelope me-1"></i>
                            <span>develoweb.dw1@gmail.com</span>
                    </div>
                </div>
                <!-- <div class="col-6 col-xl-5 col-md-4">
                    <div class="text-end">
                        <a class="d-inline-flex align-items-center me-3" data-bs-toggle="modal" href="#mdlLanguage">
                            <img src="<?= FRONT_IMGS ?>flags/en.svg" height="14" class="me-1" alt="">
                            <span class="me-1">English</span>
                            <i class="hicon hicon-thin-arrow-down hicon-bold hicon-60"></i>
                        </a>
                    </div>
                </div> -->
            </div>
        </div>
    </div>
    <!-- /Header Topbar -->

    <!-- Header Navbar -->
    <div class="header-navbar">
        <nav class="navbar navbar-expand-xl">
            <div class="container">
                <button class="navbar-toggler me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar">
                    <i class="hicon hicon-bold hicon-hamburger-menu"></i>
                </button>
                <a class="navbar-brand" href="<?= URL_WEB ?>">
                    <img src="<?= FRONT_IMGS ?>logos/logos.png" srcset="<?= FRONT_IMGS ?>logos/logo@2x.png 2x" onerror="this.onerror=null;this.src='<?= FRONT_IMGS ?>logo-default-2.png'" alt="" style="max-height: 48px; width: auto;">
                </a>
                <div class="offcanvas offcanvas-navbar offcanvas-start border-end-0" tabindex="-1" id="offcanvasNavbar">
                    <div class="offcanvas-header border-bottom p-4 p-xl-0">
                        <a href="<?= URL_WEB ?>" class="d-inline-block">
                            <img src="<?= FRONT_IMGS ?>logos/menu-logo.png" srcset="<?= FRONT_IMGS ?>logos/menu-logo@2x.png 2x" onerror="this.onerror=null; this.src='<?= FRONT_IMGS ?>logo-default-2.png'" alt="" style="max-height: 40px; width: auto;">
                        </a>
                        <button type="button" class="btn-close shadow-none" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                    </div>
                    <div class="offcanvas-body p-4 p-xl-0">
                        <ul class="navbar-nav">
                          <!--   <li class="nav-item">
                                <a class="nav-link dropdown-toggle-hover" href="<?= URL_WEB . 'promociones' ?>" data-bs-display="static">
                                    <span>Promociones</span>
                                </a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle-hover" href="<?= URL_WEB . 'paquetes' ?>" data-bs-display="static">
                                    <span>Paquetes</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link dropdown-toggle-hover" href="<?= URL_WEB . 'destinos' ?>" data-bs-display="static">
                                    <span>Destinos</span>
                                </a>
                            </li> -->
                          <!--   <li class="nav-item">
                                <a class="nav-link dropdown-toggle-hover" href="<?= URL_WEB . 'blog' ?>" data-bs-display="static">
                                    <span>Blog</span>
                                </a>
                            </li> -->
                           <!--  <li class="nav-item">
                                <a class="nav-link dropdown-toggle-hover" href="<?= URL_WEB . 'contactanos' ?>" data-bs-display="static">
                                    <span>Contactanos</span>
                                </a>
                            </li> -->
                        </ul>
                        <div class="d-flex align-items-center ms-auto">
                            <!-- <a href="./shopping-cart.html" class="circle-icon cart-icon me-4">
                                <i class="hicon hicon-bold hicon-shopping-markets"></i>
                                <span>3</span>
                            </a>
                            <a href="./wishlist.html" class="circle-icon wishlist-icon me-4">
                                <i class="hicon hicon-bold hicon-menu-favorite"></i>
                                <span>5</span>
                            </a> -->
                        </div>
                    </div>
                </div>
                <div class="dropdown user-menu ms-xl-auto">
                    <button class="circle-icon circle-icon-link circle-icon-link-hover" data-bs-toggle="dropdown" data-bs-display="static">
                        <i class="hicon hicon-mmb-account"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end animate slideIn" data-bs-popper="static">
                        <li>
                                <i class="hicon hicon-edit me-1"></i>
                                <span>Register</span>
                        </li>
                        <li>
                                <i class="hicon hicon-aps-lock me-1"></i>
                                <span>Login</span>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </div>
    <!-- /Header Navbar -->

    <!-- Currency -->
    <div class="modal fade" id="mdlCurrency" tabindex="-1" aria-labelledby="h3Currency" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <span class="fs-3 modal-title text-body-emphasis fw-medium" id="h3Currency">Select currency</span>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="list-unstyled row mb-0">
                        <li class="col-12 col-lg-6">
                                <span class="d-block pt-2 pb-2"><strong>USD</strong> (United States Dollar)</span>
                        </li>
                        <li class="col-12 col-lg-6">
                                <span class="d-block pt-2 pb-2"><strong>EUR</strong> (Euro)</span>
                        </li>
                        <li class="col-12 col-lg-6">
                                <span class="d-block pt-2 pb-2"><strong>GBP</strong> (Pound Sterling)</span>
                        </li>
                        <li class="col-12 col-lg-6">
                                <span class="d-block pt-2 pb-2"><strong>AUD</strong> (Australian Dollar)</span>
                        </li>
                        <li class="col-12 col-lg-6">
                                <span class="d-block pt-2 pb-2"><strong>NZD</strong> (New Zealand Dollar)</span>
                        </li>
                        <li class="col-12 col-lg-6">
                                <span class="d-block pt-2 pb-2"><strong>CAD</strong> (Canadian Dollar)</span>
                        </li>
                        <li class="col-12 col-lg-6">
                                <span class="d-block pt-2 pb-2"><strong>JPY</strong> (Japanese Yen)</span>
                        </li>
                        <li class="col-12 col-lg-6">
                                <span class="d-block pt-2 pb-2"><strong>CNY</strong> (Chinese Yuan)</span>
                        </li>
                        <li class="col-12 col-lg-6">
                                <span class="d-block pt-2 pb-2"><strong>VND</strong> (Vietnam Dong)</span>
                        </li>
                        <li class="col-12 col-lg-6">
                                <span class="d-block pt-2 pb-2"><strong>SGD</strong> (Singapore Dollar)</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <!-- /Currency -->
</header>
<!-- /Header -->