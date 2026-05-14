@extends('core-layout::master')

@section('title')
    MegaFamilia · Ingresos
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"MegaFamilia"},{"title":"Ingresos","active":"active"}]'></Breadcrumb>
    <mega-familia-ingresos
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/megafamilia') }}"
    ></mega-familia-ingresos>
@endsection
