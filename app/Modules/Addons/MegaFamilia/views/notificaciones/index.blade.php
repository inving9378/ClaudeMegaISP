@extends('core-layout::master')

@section('title')
    MegaFamilia · Notificaciones
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"MegaFamilia"},{"title":"Notificaciones","active":"active"}]'></Breadcrumb>
    <mega-familia-notificaciones
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/megafamilia') }}"
    ></mega-familia-notificaciones>
@endsection
