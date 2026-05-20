@extends('core-layout::master')

@section('title')
    MegaFamilia · Soporte
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"MegaFamilia"},{"title":"Soporte","active":"active"}]'></Breadcrumb>
    <mega-familia-soporte
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/megafamilia') }}"
    ></mega-familia-soporte>
@endsection
