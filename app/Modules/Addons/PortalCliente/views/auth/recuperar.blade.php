{{-- Recuperar contraseña. Misma ruta, mismos campos y mismas reglas que siempre: solo cambia la presentación (auth/layout). --}}
@php
    $ap = $apariencia ?? app(\App\Modules\Addons\PortalCliente\Services\AparienciaService::class)->vistaPublica('recuperar');
@endphp
@extends('addon-portal-cliente::auth.layout')

@section('titulo_pagina', 'Recuperar Contraseña')

@section('formulario')
    <form class="pl-form" data-pl-form data-texto-enviando="Guardando…" method="POST" action="{{ route('portal.recuperar.post') }}">
        @csrf
        <div class="pl-campo">
            <label for="pl-numero">Número de cliente</label>
            <div class="pl-control">
                <svg class="pl-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <input type="number" id="pl-numero" name="numero_cliente" value="{{ old('numero_cliente') }}"
                    placeholder="Ej: 1234" min="1" required @unless($ap['previa']) autofocus @endunless
                    @if($errors->has('numero_cliente')) aria-invalid="true" aria-describedby="pl-errores" @endif>
            </div>
        </div>
        <div class="pl-campo">
            <label for="pl-telefono">Teléfono registrado</label>
            <div class="pl-control">
                <svg class="pl-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                <input type="text" id="pl-telefono" name="telefono" value="{{ old('telefono') }}"
                    placeholder="Ej: 5512345678" autocomplete="tel"
                    @if($errors->has('telefono')) aria-invalid="true" aria-describedby="pl-errores" @endif>
            </div>
        </div>
        <div class="pl-campo">
            <label for="pl-contrasena">Nueva contraseña</label>
            <div class="pl-control pl-control--pass">
                <svg class="pl-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <input type="password" id="pl-contrasena" name="contrasena" placeholder="Mínimo 8 caracteres"
                    autocomplete="new-password" required data-pl-pass
                    @if($errors->has('contrasena')) aria-invalid="true" aria-describedby="pl-errores" @endif>
                @include('addon-portal-cliente::auth.partials.ojo', ['campo' => 'pl-contrasena'])
            </div>
        </div>
        <div class="pl-campo">
            <label for="pl-confirmacion">Confirmar contraseña</label>
            <div class="pl-control pl-control--pass">
                <svg class="pl-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <input type="password" id="pl-confirmacion" name="contrasena_confirmation"
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
