@extends('core-layout::master')

@section('title')
    Administración
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Administración","active":"active"}]'></Breadcrumb>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Administración del Sistema</h5>
                    <small class="text-muted">Secciones cargadas desde el registro de módulos activos</small>
                </div>
                <div class="card-body">
                    <admin-panel csrf-token="{{ csrf_token() }}"></admin-panel>
                </div>
            </div>
        </div>
    </div>
@endsection
