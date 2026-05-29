@extends('core-layout::master-without-nav')

@section('title')
    Meganet · Programa Embajadores
@endsection

@section('content')
<div class="auth-page">
    <div class="container py-5" style="max-width: 540px;">
        <div class="text-center mb-4">
            <img src="{{ asset('/images/logo_meganet_oficial.png') }}" alt="Meganet" height="70">
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-4 p-md-5 text-center">

                @if($profile)
                    <div class="mb-3">
                        <span class="badge bg-success fs-6 px-3 py-2">Código válido</span>
                    </div>
                    <h4 class="mb-2">¡Hola! Te invitaron a Meganet</h4>
                    <p class="text-muted mb-4">
                        Fuiste referido a través del programa <strong>Embajadores Meganet</strong>.
                        Comunícate con nosotros para contratar tu servicio y menciona el código:
                    </p>
                    <div class="alert alert-light border fs-4 fw-bold letter-spacing-1 mb-4">
                        {{ $code }}
                    </div>
                    <p class="text-muted small">
                        Al contratar tu servicio con este código, tu referidor obtiene beneficios
                        y tú te unes a la red de clientes Meganet.
                    </p>
                @elseif($code)
                    <div class="mb-3">
                        <span class="badge bg-warning text-dark fs-6 px-3 py-2">Código no encontrado</span>
                    </div>
                    <h4 class="mb-2">Código de referido inválido</h4>
                    <p class="text-muted mb-4">
                        El código <strong>{{ $code }}</strong> no corresponde a un embajador activo.
                        Si crees que es un error, pídele a quien te lo compartió que verifique su código en la app.
                    </p>
                @else
                    <h4 class="mb-2">Programa Embajadores Meganet</h4>
                    <p class="text-muted mb-4">
                        Si llegaste aquí a través de un enlace de referido, verifica que el enlace
                        esté completo e incluya el código <code>?ref=CÓDIGO</code>.
                    </p>
                @endif

                <hr class="my-4">
                <p class="mb-1 fw-semibold">¿Listo para contratar?</p>
                <p class="text-muted small">Contáctanos y menciona tu código de referido al momento del registro.</p>
            </div>
        </div>

        <p class="text-center text-muted small mt-4">
            &copy; {{ date('Y') }} Meganet Telecomunicaciones
        </p>
    </div>
</div>
@endsection
