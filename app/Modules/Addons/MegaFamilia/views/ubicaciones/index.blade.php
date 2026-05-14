@extends('core-layout::master')

@section('title')
    MegaFamilia · Ubicaciones
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"MegaFamilia"},{"title":"Ubicaciones","active":"active"}]'></Breadcrumb>
    <mega-familia-ubicaciones
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/megafamilia') }}"
    ></mega-familia-ubicaciones>
@endsection
