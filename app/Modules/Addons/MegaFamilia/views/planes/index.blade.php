@extends('core-layout::master')

@section('title')
    MegaFamilia · Planes
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"MegaFamilia"},{"title":"Planes","active":"active"}]'></Breadcrumb>
    <mega-familia-planes
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/megafamilia') }}"
    ></mega-familia-planes>
@endsection
