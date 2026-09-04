@extends('core-layout::master')

@section('title', 'Mapa de Red')

@section('content')
    @if(auth()->user()->can('mapa_red_view'))
        <div class="text-center py-5">
            <i class="fa fa-map fa-3x text-muted mb-3"></i>
            <h4>Mapa de Red — próximamente</h4>
            <p class="text-muted">Esta pantalla es el esqueleto del módulo. La funcionalidad se agrega en las siguientes fases.</p>
        </div>
    @endif
@endsection
