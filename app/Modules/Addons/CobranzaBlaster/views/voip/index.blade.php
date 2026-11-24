@extends('core-layout::master')

@section('title')
    Campañas/Blaster · Configuración VoIP
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Campañas/Blaster"},{"title":"Configuración VoIP","active":"active"}]'></Breadcrumb>
    <div>
        <cobranza-voip-config
            csrf-token="{{ csrf_token() }}"
            base-url="{{ url('/cobranza') }}"
        ></cobranza-voip-config>
    </div>
@endsection
