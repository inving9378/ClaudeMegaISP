@extends('core-layout::master')

@section('title')
    MegaFamilia · Alertas
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"MegaFamilia"},{"title":"Alertas","active":"active"}]'></Breadcrumb>
    <mega-familia-alertas
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/megafamilia') }}"
    ></mega-familia-alertas>
@endsection
