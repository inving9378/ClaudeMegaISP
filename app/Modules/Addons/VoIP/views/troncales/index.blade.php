@extends('core-layout::master')

@section('title')
    VoIP — Troncales
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"VoIP"},{"title":"Troncales","active":"active"}]'></Breadcrumb>
    <div>
        <voip-troncales
            csrf-token="{{ csrf_token() }}"
            base-url="{{ url('/voip') }}"
        ></voip-troncales>
    </div>
@endsection
