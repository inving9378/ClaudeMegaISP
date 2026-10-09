@extends('core-layout::master')

@section('title', 'Mapa de Red — Catálogos')

@section('content')
    @if(auth()->user()->can('mapa_red_catalogo_view'))
        <mapa-red-catalogos
            :puede-gestionar="{{ auth()->user()->can('mapa_red_catalogo_manage') ? 'true' : 'false' }}"
        />
    @else
        <div class="alert alert-warning m-3">No tienes permiso para ver los catálogos del Mapa de Red.</div>
    @endif
@endsection
