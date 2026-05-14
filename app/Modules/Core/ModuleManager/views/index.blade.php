@extends('core-layout::master')

@section('title')
    Administrador de módulos
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Configuración"},{"title":"Add-ons","active":"active"}]'></Breadcrumb>

    <module-manager
        :initial-modules='@json($modules)'
        :total-modules="{{ $totalModules }}"
        :migrated-count="{{ $migratedCount }}"
        :pending-count="{{ $pendingCount }}"
        :total-cost-usd="{{ $totalCostUsd }}"
        csrf-token="{{ csrf_token() }}"
    ></module-manager>
@endsection
