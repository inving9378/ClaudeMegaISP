@extends('core-layout::master')

@section('title')
    MegaFamilia · Clientes
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"MegaFamilia"},{"title":"Clientes","active":"active"}]'></Breadcrumb>
    <mega-familia-clientes
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/megafamilia') }}"
    ></mega-familia-clientes>
@endsection
