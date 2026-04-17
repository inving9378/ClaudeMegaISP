@extends('meganet.layout.master-without-nav')
@section('title')
Login
@endsection
@php
    $logo = $logoMeganet();
    $logoName = $logo['name'];
    if ($logoName) {
        $logoPath = $logo['url_logo'];
        //$logoPath = str_replace('public', '/storage', $logoPath);
    }
@endphp
@section('content')
    <div class="auth-page">
        <div class="container-fluid p-0">
            <div class="row g-0">
                <div class="col-xxl-3 col-lg-4 col-md-5">
                    <div class="auth-full-page-content d-flex p-sm-5 p-4">
                        <div class="w-100">
                            <div class="d-flex flex-column h-100">
                                <div class="auth-content my-auto">
                                    <div class="mb-5 mb-md-5 text-center">
                                        <img src="{{ $logoName ? asset($logoPath) : asset('/images/logo_meganet_oficial.png') }}"
                                        alt="Logo" height="70">
                                    </div>
                                    <div class="text-center">
                                        <h5 class="mb-0">Bienvenido!</h5>
                                        <p class="text-muted mt-2">Inicie sesión en Meganet..</p>
                                    </div>
                                    <form method="POST" class="mt-4 pt-2" action="{{ route('login') }}">
                                        @csrf
                                        <div class="mb-3">
                                            <label class="form-label">Correo o Usuario:</label>
                                            <input name="email" class="form-control" id="username" placeholder="correo" value="{{ old('email') }}">
                                        </div>
                                        <div class="mb-3">
                                            <div class="d-flex align-items-start">
                                                <div class="flex-grow-1">
                                                    <label class="form-label">Contraseña</label>
                                                </div>
                                            </div>

                                            <div class="input-group auth-pass-inputgroup">
                                                <input type="password" class="form-control" name="password"
                                                id="password"
                                                placeholder="contraseña" aria-label="Password" aria-describedby="password-addon">
                                                <button class="btn btn-outline-secondary" type="button" id="button-addon2" onclick="togglePasswordVisibility()">
                                                    <i class="fas fa-eye-slash" id="togglePasswordIcon"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div>
                                            <a href="/register-vendor">
                                                <p class="text-muted mt-2">Quiero registrarme como vendedor.</p>
                                            </a>
                                        </div>

                                        <div class="mb-3">
                                            <button class="btn btn-primary w-100 waves-effect waves-light" type="submit">Entrar</button>
                                        </div>
                                        @if(count( $errors ) > 0)
                                            @foreach ($errors->all() as $error)
                                                <h6 class="text-danger">{{ $error }}</h6>
                                            @endforeach
                                        @endif
                                    </form>
                                </div>
                                <div class="mt-4 mt-md-5 text-center">
                                    Desarrollado por <a href="#!" class="text-decoration-underline">Meganet</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end auth full page content -->
                </div>
                <!-- end col -->
                <div class="col-xxl-9 col-lg-8 col-md-7">
                    <div class="auth-bg pt-md-5 p-4 d-flex">
                        <div class="bg-overlay bg-primary"></div>
                        <ul class="bg-bubbles">
                            <li></li>
                            <li></li>
                            <li></li>
                            <li></li>
                            <li></li>
                            <li></li>
                            <li></li>
                            <li></li>
                            <li></li>
                            <li></li>
                        </ul>
                        <!-- end bubble effect -->
                        <div class="row justify-content-center align-items-center">
                            <div class="col-xl-7">
                                <div class="p-0 p-sm-4 px-xl-0">
                                    <div id="reviewcarouselIndicators" class="carousel slide" data-bs-ride="carousel">
                                        <div class="carousel-indicators carousel-indicators-rounded justify-content-start ms-0 mb-0">
                                            <button type="button" data-bs-target="#reviewcarouselIndicators" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                                            <button type="button" data-bs-target="#reviewcarouselIndicators" data-bs-slide-to="1" aria-label="Slide 2"></button>
                                            <button type="button" data-bs-target="#reviewcarouselIndicators" data-bs-slide-to="2" aria-label="Slide 3"></button>
                                        </div>
                                        <!-- end carouselIndicators -->
                                        <div class="carousel-inner">
                                            <div class="carousel-item active">
                                                <div class="testi-contain text-white">
                                                    <i class="bx bxs-quote-alt-left text-success display-6"></i>

                                                    <h4 class="mt-4 fw-medium lh-base text-white">“Me siento seguro de imponerme el cambio. Es mucho más divertido progresar que mirar hacia atrás.”
                                                    </h4>
                                                    <div class="mt-4 pt-3 pb-5">
                                                        <div class="d-flex align-items-start">
                                                            <div class="flex-shrink-0">
                                                                <img src="assets/images/users/avatar-1.jpg" class="avatar-md img-fluid rounded-circle" alt="...">
                                                            </div>
                                                            <div class="flex-grow-1 ms-3 mb-4">
                                                                <h5 class="font-size-18 text-white">Richard Drews
                                                                </h5>
                                                                <p class="mb-0 text-white-50">Web Designer</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="carousel-item">
                                                <div class="testi-contain text-white">
                                                    <i class="bx bxs-quote-alt-left text-success display-6"></i>

                                                    <h4 class="mt-4 fw-medium lh-base text-white">“Me siento seguro de imponerme el cambio. Es mucho más divertido progresar que mirar hacia atrás.”</h4>
                                                    <div class="mt-4 pt-3 pb-5">
                                                        <div class="d-flex align-items-start">
                                                            <div class="flex-shrink-0">
                                                                <img src="assets/images/users/avatar-2.jpg" class="avatar-md img-fluid rounded-circle" alt="...">
                                                            </div>
                                                            <div class="flex-grow-1 ms-3 mb-4">
                                                                <h5 class="font-size-18 text-white">Rosanna French
                                                                </h5>
                                                                <p class="mb-0 text-white-50">Web Developer</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="carousel-item">
                                                <div class="testi-contain text-white">
                                                    <i class="bx bxs-quote-alt-left text-success display-6"></i>

                                                    <h4 class="mt-4 fw-medium lh-base text-white">“Me siento seguro de imponerme el cambio. Es mucho más divertido progresar que mirar hacia atrás.”</h4>
                                                    <div class="mt-4 pt-3 pb-5">
                                                        <div class="d-flex align-items-start">
                                                            <img src="assets/images/users/avatar-3.jpg"
                                                                 class="avatar-md img-fluid rounded-circle" alt="...">
                                                            <div class="flex-1 ms-3 mb-4">
                                                                <h5 class="font-size-18 text-white">Ilse R. Eaton</h5>
                                                                <p class="mb-0 text-white-50">Manager
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- end carousel-inner -->
                                    </div>
                                    <!-- end review carousel -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end col -->
            </div>
            <!-- end row -->
        </div>
        <!-- end container fluid -->
    </div>
@endsection

<script>
    function togglePasswordVisibility() {
        var passwordInput = document.getElementById('password');
        var togglePasswordIcon = document.getElementById('togglePasswordIcon');

        if (passwordInput.type === "password") {
            passwordInput.type = "text";
            togglePasswordIcon.classList.remove('fa-eye-slash');
            togglePasswordIcon.classList.add('fa-eye');
        } else {
            passwordInput.type = "password";
            togglePasswordIcon.classList.remove('fa-eye');
            togglePasswordIcon.classList.add('fa-eye-slash');
        }
    }
</script>
