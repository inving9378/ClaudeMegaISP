@extends('core-layout::master')

@section('title')
    MegaFamilia · Solicitudes
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"MegaFamilia"},{"title":"Solicitudes","active":"active"}]'></Breadcrumb>
    <mega-familia-solicitudes
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/megafamilia') }}"
    ></mega-familia-solicitudes>
@endsection
