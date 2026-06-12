@extends('core-layout::master')

@section('title')
    VoIP — Extensiones
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"VoIP"},{"title":"Extensiones","active":"active"}]'></Breadcrumb>
    <div>
        <voip-extensiones
            csrf-token="{{ csrf_token() }}"
            base-url="{{ url('/voip') }}"
        ></voip-extensiones>
    </div>
@endsection
