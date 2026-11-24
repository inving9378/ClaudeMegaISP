@extends('core-layout::master')

@section('title')
    Campañas/Blaster · Dashboard
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Campañas/Blaster"},{"title":"Dashboard","active":"active"}]'></Breadcrumb>
    <div>
        <cobranza-dashboard
            base-url="{{ url('/cobranza/dashboard') }}"
            grabaciones-url="{{ url('/voip/grabaciones') }}"
            :puede-escuchar="{{ $puedeEscuchar ? 'true' : 'false' }}"
        ></cobranza-dashboard>
    </div>
@endsection
