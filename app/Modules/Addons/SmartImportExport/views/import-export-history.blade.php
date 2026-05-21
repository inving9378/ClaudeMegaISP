@extends('core-layout::master')

@section('title')
    Historial Import / Export — MegaISP
@endsection

@section('content')
    <Breadcrumb :list=[{title:"Configuracion"},{title:"Herramientas"},{title:"Historial_Import_Export",active:"active"}]></Breadcrumb>
    <import-export-history></import-export-history>
@endsection
