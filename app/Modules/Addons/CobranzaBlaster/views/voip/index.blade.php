@extends('core-layout::master')

@section('title')
    Campañas/Blaster · Configuración VoIP
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Campañas/Blaster"},{"title":"Configuración VoIP","active":"active"}]'></Breadcrumb>
    <div>
        {{-- 28-nov: el grupo de timbrado se puede crear aquí mismo (con el alta de VoIP y sus permisos). --}}
        <cobranza-voip-config
            csrf-token="{{ csrf_token() }}"
            base-url="{{ url('/cobranza') }}"
            voip-url="{{ url('/voip') }}"
            :puede-crear-grupo="{{ (auth()->user()->can('voip.grupos.create') && auth()->user()->can('voip.grupos.view')) ? 'true' : 'false' }}"
        ></cobranza-voip-config>
    </div>
@endsection
