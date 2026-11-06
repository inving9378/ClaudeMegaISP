{{-- Recuperar contraseña con código, paso 2. El aviso no afirma que se envió nada: no debe delatar si la cuenta existe. --}}
@php
    $ap = $apariencia ?? app(\App\Modules\Addons\PortalCliente\Services\AparienciaService::class)->vistaPublica('recuperar');
@endphp
@extends('addon-portal-cliente::auth.layout')

@section('titulo_pagina', 'Verificar código')

@section('nota')
    <div class="pl-nota">
        Escribe el <strong>código de 6 dígitos</strong> que recibiste por WhatsApp.
    </div>
@endsection

@section('formulario')
    <form class="pl-form" data-pl-form data-texto-enviando="Verificando…" method="POST" action="{{ route('portal.password.verificar-otp') }}">
        @csrf
        <div class="pl-campo">
            <label for="pl-otp">Código de 6 dígitos</label>
            <div class="pl-control">
                <svg class="pl-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <input type="text" id="pl-otp" name="otp" inputmode="numeric" maxlength="6" pattern="[0-9]{6}"
                    placeholder="000000" autocomplete="one-time-code" required @unless($ap['previa']) autofocus @endunless
                    @if($errors->has('otp')) aria-invalid="true" aria-describedby="pl-errores" @endif>
            </div>
        </div>
        @include('addon-portal-cliente::auth.partials.boton', ['texto' => 'Verificar código'])
    </form>

    <nav class="pl-enlaces" aria-label="Otras opciones de acceso">
        <a href="{{ $ap['enlaces']['recuperar'] }}">← Solicitar un nuevo código</a>
    </nav>
@endsection
