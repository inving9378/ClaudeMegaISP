@extends('core-layout::master')

@section('title')
    MegaFamilia · Auditoría
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"MegaFamilia"},{"title":"Auditoría","active":"active"}]'></Breadcrumb>
    <mega-familia-auditoria
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/megafamilia') }}"
    ></mega-familia-auditoria>
@endsection
