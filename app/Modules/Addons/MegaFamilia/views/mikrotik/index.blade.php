@extends('core-layout::master')

@section('title')
    MegaFamilia · MikroTik
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"MegaFamilia"},{"title":"MikroTik","active":"active"}]'></Breadcrumb>
    <mega-familia-mikrotik
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/megafamilia') }}"
    ></mega-familia-mikrotik>
@endsection
