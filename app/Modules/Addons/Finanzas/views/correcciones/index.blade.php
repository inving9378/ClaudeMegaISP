@extends('core-layout::master')

@section('title')
    Correcciones pendientes · Finanzas
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Finanzas"},{"title":"Correcciones pendientes","active":"active"}]'></Breadcrumb>
    <correcciones-facturacion :puede-gestionar="{{ $puedeGestionar ? 'true' : 'false' }}"></correcciones-facturacion>
@endsection
