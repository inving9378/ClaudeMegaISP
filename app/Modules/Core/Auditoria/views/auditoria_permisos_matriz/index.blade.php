@extends('core-layout::master')

@section('title')
    Matriz de permisos por rol
@endsection

@section('content')
    <Breadcrumb :list=[{title:"Auditoría"},{title:"Matriz de permisos por rol",active:"active"}]></Breadcrumb>

    <auditoria-permisos-matriz></auditoria-permisos-matriz>
@endsection
