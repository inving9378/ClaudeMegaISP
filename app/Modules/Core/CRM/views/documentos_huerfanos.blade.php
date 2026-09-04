@extends('core-layout::master')
@section('title') Documentos huérfanos — CRM @endsection

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Crm"},{title:"Documentos huérfanos",active:"active"}]></Breadcrumb>
    <Crm-Orphan-Documents></Crm-Orphan-Documents>
@endsection
