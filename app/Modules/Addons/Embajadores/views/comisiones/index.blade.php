@extends('core-layout::master')

@section('title')
    Embajadores Meganet · Comisiones
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Embajadores Meganet"},{"title":"Comisiones","active":"active"}]'></Breadcrumb>
    <embajadores-comisiones
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/embajadores') }}"
    ></embajadores-comisiones>
@endsection
