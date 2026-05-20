@extends('core-layout::master')

@section('title')
    MegaFamilia · Reportes
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"MegaFamilia"},{"title":"Reportes","active":"active"}]'></Breadcrumb>
    <mega-familia-reportes
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/megafamilia') }}"
    ></mega-familia-reportes>
@endsection
