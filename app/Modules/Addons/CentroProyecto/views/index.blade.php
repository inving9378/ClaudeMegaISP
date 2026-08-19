@extends('core-layout::master')

@section('title')
    Centro de Proyecto
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Centro de Proyecto","active":"active"}]'></Breadcrumb>
    <div class="card">
        <div class="card-body">
            <h4 class="card-title">Centro de Proyecto</h4>
            <p class="text-muted">
                Microsistema de observabilidad del proyecto: salud del sistema, KPIs de negocio
                Meganet, mapa de arquitectura y superficie de trabajo del Roadmap.
            </p>
            <p class="text-muted mb-0">
                Este módulo se construye por fases (Hoja de Ruta #810). Los paneles de Salud del
                sistema, KPIs, Mapa del proyecto y la superficie de Roadmap se irán habilitando
                aquí conforme se completen sus items.
            </p>
        </div>
    </div>
@endsection
