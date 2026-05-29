@extends('core-layout::master')

@section('title')
    Cobranza Blaster · Campañas
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Cobranza Blaster"},{"title":"Campañas","active":"active"}]'></Breadcrumb>
    <div id="init-vue">
        <cobranza-campanas
            csrf-token="{{ csrf_token() }}"
            base-url="{{ url('/cobranza') }}"
        ></cobranza-campanas>
    </div>
@endsection
