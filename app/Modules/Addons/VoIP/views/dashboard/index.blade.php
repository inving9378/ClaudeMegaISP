@extends('core-layout::master')

@section('title')
    VoIP — Llamadas
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"VoIP"},{"title":"Dashboard","active":"active"}]'></Breadcrumb>
    <div>
        <voip-dashboard
            base-url="{{ url('/voip') }}"
            :puede-escuchar="{{ $puedeEscuchar ? 'true' : 'false' }}"
        ></voip-dashboard>
    </div>
@endsection
