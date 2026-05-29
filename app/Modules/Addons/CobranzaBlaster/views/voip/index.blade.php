@extends('core-layout::master')

@section('title')
    Cobranza Blaster · Configuración VoIP
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Cobranza Blaster"},{"title":"Configuración VoIP","active":"active"}]'></Breadcrumb>
    <div id="init-vue">
        <cobranza-voip-config
            csrf-token="{{ csrf_token() }}"
            base-url="{{ url('/cobranza') }}"
        ></cobranza-voip-config>
    </div>
@endsection
