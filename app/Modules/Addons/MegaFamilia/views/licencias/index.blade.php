@extends('core-layout::master')

@section('title')
    MegaFamilia · Licencias
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"MegaFamilia"},{"title":"Licencias","active":"active"}]'></Breadcrumb>
    <mega-familia-licencias
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/megafamilia') }}"
    ></mega-familia-licencias>
@endsection
