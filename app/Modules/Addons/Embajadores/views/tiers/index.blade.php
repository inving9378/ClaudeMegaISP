@extends('core-layout::master')

@section('title')
    Embajadores Meganet · Porcentajes de Comisión
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Embajadores Meganet"},{"title":"Porcentajes","active":"active"}]'></Breadcrumb>
    <embajadores-tiers
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/embajadores') }}"
    ></embajadores-tiers>
@endsection
