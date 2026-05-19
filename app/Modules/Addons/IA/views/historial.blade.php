@extends('core-layout::master')
@section('title')
    Historial IA
@endsection

@section('content')
    <ia-historial
        :proyectos-iniciales="{{ json_encode($proyectos) }}"
    ></ia-historial>
@endsection
