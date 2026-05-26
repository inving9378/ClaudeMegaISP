@extends('core-layout::master')

@section('title')
    Importación Inteligente — MegaISP
@endsection

@section('content')
    <Breadcrumb :list=[{title:"Configuracion"},{title:"Herramientas"},{title:"Importacion_Inteligente",active:"active"}]></Breadcrumb>
    <smart-import></smart-import>
@endsection
