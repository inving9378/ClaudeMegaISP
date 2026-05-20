@extends('core-layout::master')

@section('title')
    MegaFamilia · Dashboard
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"MegaFamilia"},{"title":"Dashboard","active":"active"}]'></Breadcrumb>
    <mega-familia-dashboard
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/megafamilia') }}"
    ></mega-familia-dashboard>
@endsection
