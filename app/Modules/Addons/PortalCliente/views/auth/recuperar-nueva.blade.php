{{-- Recuperar contraseña con código, paso 3: la contraseña nueva, ya con el código verificado. --}}
@php
    $ap = $apariencia ?? app(\App\Modules\Addons\PortalCliente\Services\AparienciaService::class)->vistaPublica('recuperar');
@endphp
@extends('addon-portal-cliente::auth.layout')

@section('titulo_pagina', 'Nueva contraseña')

@section('formulario')
    <form class="pl-form" data-pl-form data-texto-enviando="Guardando…" method="POST" action="{{ route('portal.password.restablecer') }}">
        @csrf
        <div class="pl-campo">
            <label for="pl-contrasena">Nueva contraseña</label>
            <div class="pl-control pl-control--pass">
                <svg class="pl-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <input type="password" id="pl-contrasena" name="contrasena" placeholder="Mínimo 8 caracteres"
                    autocomplete="new-password" required data-pl-pass @unless($ap['previa']) autofocus @endunless
                    @if($errors->has('contrasena')) aria-invalid="true" aria-describedby="pl-errores" @endif>
                @include('addon-portal-cliente::auth.partials.ojo', ['campo' => 'pl-contrasena'])
            </div>
        </div>
        <div class="pl-campo">
            <label for="pl-confirmacion">Confirmar contraseña</label>
            <div class="pl-control pl-control--pass">
                <svg class="pl-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <input type="password" id="pl-confirmacion" name="contrasena_confirmation" placeholder="Repite tu contraseña"
                    autocomplete="new-password" required data-pl-pass>
                @include('addon-portal-cliente::auth.partials.ojo', ['campo' => 'pl-confirmacion'])
            </div>
        </div>
        @include('addon-portal-cliente::auth.partials.boton', ['texto' => 'Establecer nueva contraseña'])
    </form>

    <nav class="pl-enlaces" aria-label="Otras opciones de acceso">
        <a href="{{ $ap['enlaces']['login'] }}">← Volver al inicio de sesión</a>
    </nav>
@endsection
