@extends('core-layout::master')

@section('title')
    MegaFamilia · Términos
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"MegaFamilia"},{"title":"Términos","active":"active"}]'></Breadcrumb>
    <mega-familia-terminos
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/megafamilia') }}"
    ></mega-familia-terminos>
@endsection
