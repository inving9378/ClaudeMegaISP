@extends('core-layout::master')

@section('title')
    MegaFamilia · Configuración
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"MegaFamilia"},{"title":"Configuración","active":"active"}]'></Breadcrumb>
    <mega-familia-configuracion
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/megafamilia') }}"
    ></mega-familia-configuracion>
@endsection
