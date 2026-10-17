{{-- Iniciar sesión. Misma ruta, mismos campos y mismo CSRF que siempre: solo cambia la presentación (auth/layout). --}}
@php
    $ap = $apariencia ?? app(\App\Modules\Addons\PortalCliente\Services\AparienciaService::class)->vistaPublica('login');
@endphp
@extends('addon-portal-cliente::auth.layout')

@section('titulo_pagina', 'Iniciar Sesión')

@section('formulario')
    <form class="pl-form" id="pl-form" data-pl-form data-texto-enviando="Ingresando…" method="POST" action="{{ route('portal.login.post') }}">
        @csrf
        <div class="pl-campo">
            <label for="pl-identificador">Número de cliente o Email</label>
            <div class="pl-control">
                <svg class="pl-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <input type="text" id="pl-identificador" name="identificador" value="{{ old('identificador') }}"
                    placeholder="Ej: 1234 o correo@ejemplo.com" autocomplete="username" required @unless($ap['previa']) autofocus @endunless
                    @if($errors->has('identificador')) aria-invalid="true" aria-describedby="pl-errores" @endif>
            </div>
        </div>
        <div class="pl-campo">
            <label for="pl-contrasena">Contraseña</label>
            <div class="pl-control pl-control--pass">
                <svg class="pl-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <input type="password" id="pl-contrasena" name="contrasena" placeholder="Tu contraseña del portal"
                    autocomplete="current-password" required data-pl-pass
                    @if($errors->has('contrasena')) aria-invalid="true" aria-describedby="pl-errores" @endif>
                @include('addon-portal-cliente::auth.partials.ojo', ['campo' => 'pl-contrasena', 'id' => 'pl-ojo'])
            </div>
        </div>
        @include('addon-portal-cliente::auth.partials.boton', ['texto' => 'Iniciar Sesión'])
    </form>

    <nav class="pl-enlaces" aria-label="Otras opciones de acceso">
        <a href="{{ $ap['enlaces']['registro'] }}">¿Primera vez? Crear cuenta</a>
        <a href="{{ $ap['enlaces']['recuperar'] }}">Olvidé mi contraseña</a>
    </nav>
@endsection
