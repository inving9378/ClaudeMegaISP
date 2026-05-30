@extends('core-layout::master')

@section('title')
    Administración
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Administración","active":"active"}]'></Breadcrumb>

    <admin-panel csrf-token="{{ csrf_token() }}"></admin-panel>
@endsection
