@extends('core-layout::master')

@section('title', 'Talento — Ficha del colaborador')

@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div>
            <talento-colaborador-ficha :id="{{ (int) $id }}" :permisos='@json($permisos)' :es-propia="{{ $esPropia ? 'true' : 'false' }}" :mostrar-portal="{{ $mostrarPortal ? 'true' : 'false' }}" :es-uno-mismo-literal="{{ $esUnoMismoLiteral ? 'true' : 'false' }}"></talento-colaborador-ficha>
        </div>
    </div>
</div>
@endsection
