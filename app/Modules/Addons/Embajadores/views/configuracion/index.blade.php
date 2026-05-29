@extends('core-layout::master')

@section('title')
    Embajadores Meganet · Configuración
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Embajadores Meganet"},{"title":"Configuración","active":"active"}]'></Breadcrumb>
    <embajadores-configuracion
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/embajadores') }}"
    ></embajadores-configuracion>
@endsection
