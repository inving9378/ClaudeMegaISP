@extends('core-layout::master')

@section('title')
    VoIP — Grupos de timbrado
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"VoIP"},{"title":"Grupos de timbrado","active":"active"}]'></Breadcrumb>
    <div>
        <voip-grupos-timbrado
            csrf-token="{{ csrf_token() }}"
            base-url="{{ url('/voip') }}"
        ></voip-grupos-timbrado>
    </div>
@endsection
