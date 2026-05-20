@extends('core-layout::master')
@section('title')
    Asistente IA
@endsection

@section('content')
    <ia-chat-index
        :proveedores-iniciales="{{ json_encode($proveedores) }}"
        :proyectos-iniciales="{{ json_encode($proyectos) }}"
    ></ia-chat-index>
@endsection
