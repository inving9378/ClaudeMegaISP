@extends('core-layout::master')

@section('title')
    MegaFamilia · Dispositivos
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"MegaFamilia"},{"title":"Dispositivos","active":"active"}]'></Breadcrumb>
    <mega-familia-dispositivos
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/megafamilia') }}"
    ></mega-familia-dispositivos>
@endsection
