@extends('core-layout::master')

@section('title')
    VoIP — KPIs
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"VoIP"},{"title":"KPIs","active":"active"}]'></Breadcrumb>
    <div>
        <voip-kpis
            csrf-token="{{ csrf_token() }}"
            base-url="{{ url('/voip') }}"
        ></voip-kpis>
    </div>
@endsection
