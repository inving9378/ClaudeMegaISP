@extends('core-layout::master')
@section('title')
    Configuración del Sistema
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Configuración","active":"active"}]'></Breadcrumb>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Configuración del Sistema</h5>
                    <small class="text-muted">Secciones cargadas desde el registro de módulos activos</small>
                </div>
                <div class="card-body">
                    <debt-payment-client id="debtpaymentclient"></debt-payment-client>
                    <module-config-panel base-url="{{ url('/') }}"></module-config-panel>
                </div>
            </div>
        </div>
    </div>
@endsection
