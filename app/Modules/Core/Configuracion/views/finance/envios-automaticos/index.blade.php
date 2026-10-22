@extends('core-layout::master')

@section('title')
    Envíos automáticos · Configuración
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Configuración"},{"title":"Finanzas"},{"title":"Envíos automáticos","active":"active"}]'></Breadcrumb>
    <envios-automaticos :puede-gestionar="{{ $puedeGestionar ? 'true' : 'false' }}"></envios-automaticos>
@endsection
