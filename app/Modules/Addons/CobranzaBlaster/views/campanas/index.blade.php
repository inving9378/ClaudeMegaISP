@extends('core-layout::master')

@section('title')
    Campañas/Blaster · Campañas
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Campañas/Blaster"},{"title":"Campañas","active":"active"}]'></Breadcrumb>
    <div>
        <cobranza-campanas
            csrf-token="{{ csrf_token() }}"
            base-url="{{ url('/cobranza') }}"
            dashboard-url="{{ url('/cobranza/dashboard') }}"
            :puede-gestionar="{{ $puedeGestionar ? 'true' : 'false' }}"
            :puede-cortar="{{ $puedeCortar ? 'true' : 'false' }}"
        ></cobranza-campanas>
    </div>
@endsection
