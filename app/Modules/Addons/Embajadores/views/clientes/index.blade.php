@extends('core-layout::master')

@section('title')
    Embajadores Meganet · Embajadores
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Embajadores Meganet"},{"title":"Embajadores","active":"active"}]'></Breadcrumb>
    <embajadores-clientes
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/embajadores') }}"
    ></embajadores-clientes>
@endsection
