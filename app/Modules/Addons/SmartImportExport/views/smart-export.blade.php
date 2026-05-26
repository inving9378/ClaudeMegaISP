@extends('core-layout::master')

@section('title')
    Exportación Selectiva — MegaISP
@endsection

@section('content')
    <Breadcrumb :list=[{title:"Configuracion"},{title:"Herramientas"},{title:"Exportacion_Selectiva",active:"active"}]></Breadcrumb>
    <smart-export></smart-export>
@endsection
