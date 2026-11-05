@extends('core-layout::master')

@section('title', 'Talento — Ficha del colaborador')

@section('content')
<div>
    <talento-colaborador-ficha :id="{{ (int) $id }}" :permisos='@json($permisos)' :es-propia="{{ $esPropia ? 'true' : 'false' }}" :es-uno-mismo-literal="{{ $esUnoMismoLiteral ? 'true' : 'false' }}" :secciones='@json($secciones)'></talento-colaborador-ficha>
</div>
@endsection
