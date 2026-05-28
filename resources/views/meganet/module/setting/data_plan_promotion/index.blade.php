@extends('core-layout::master')

@section('content')
    <Breadcrumb
        :list=[{title:"Página",link:"/"},{title:"Configuración",link:"/configuracion"},{title:"Promociones",active:"active"}]>
    </Breadcrumb>
    <data-plan-promotion-table :profiles="{{ $profiles }}" />
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="PlanPromotionTable" />
    @endif
@endsection
