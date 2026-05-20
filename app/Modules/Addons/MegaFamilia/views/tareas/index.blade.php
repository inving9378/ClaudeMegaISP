@extends('core-layout::master')

@section('title')
    MegaFamilia · Tareas
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"MegaFamilia"},{"title":"Tareas","active":"active"}]'></Breadcrumb>
    <mega-familia-tareas
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/megafamilia') }}"
    ></mega-familia-tareas>
@endsection
