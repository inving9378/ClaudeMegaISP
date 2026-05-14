@extends('core-layout::master')

@section('title')
    MegaFamilia · Perfiles
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"MegaFamilia"},{"title":"Perfiles","active":"active"}]'></Breadcrumb>
    <mega-familia-perfiles
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/megafamilia') }}"
    ></mega-familia-perfiles>
@endsection
