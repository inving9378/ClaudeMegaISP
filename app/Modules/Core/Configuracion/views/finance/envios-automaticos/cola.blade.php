@extends('core-layout::master')

@section('title')
    Detalle de la cola · Envíos automáticos
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Configuración"},{"title":"Finanzas"},{"title":"Envíos automáticos"},{"title":"Detalle de la cola","active":"active"}]'></Breadcrumb>
    <envios-cola-detalle :puede-gestionar="{{ $puedeGestionar ? 'true' : 'false' }}"></envios-cola-detalle>
@endsection
