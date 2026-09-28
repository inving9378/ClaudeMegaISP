@extends('core-layout::master')

@section('title', 'Talento — Ficha del colaborador')

@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div>
            <talento-colaborador-ficha :id="{{ (int) $id }}" :permisos='@json($permisos)'></talento-colaborador-ficha>
        </div>
    </div>
</div>
@endsection
