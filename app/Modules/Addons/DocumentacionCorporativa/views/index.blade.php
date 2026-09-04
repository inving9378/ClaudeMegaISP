@extends('core-layout::master')

@section('title', 'Documentación Corporativa')

@section('styles')
    <link rel="stylesheet" href="{{ asset('plugins/quasar/extras/bootstrap-icons/bootstrap-icons.css') }}">
@endsection

@section('content')
    <div>
        {{-- Toda la pantalla vive en el componente; el Blade sólo lo monta y le
             pasa el contexto de empresa que ya resolvió el servidor. --}}
        <dc-expediente
            :empresa-id="{{ $empresa->id }}"
            empresa-etiqueta="{{ $empresa->etiqueta }}"
            :mostrar-selector="{{ $mostrarSelector ? 'true' : 'false' }}"
        ></dc-expediente>
    </div>
@endsection
