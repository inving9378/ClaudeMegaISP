@extends('core-layout::master')
@section('title')
    Configuración IA
@endsection

@section('content')
    <ia-configuracion
        :proveedores-iniciales="{{ json_encode($proveedores) }}"
    ></ia-configuracion>
@endsection
