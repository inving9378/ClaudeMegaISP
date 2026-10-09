@extends('core-layout::master')

@section('title')
    Nomenclatura de ONU
@endsection

@section('content')
    @if(auth()->user()->can('client_edit_client'))
        <Breadcrumb :list='[{"title":"Inicio"},{"title":"Clientes"},{"title":"Nomenclatura de ONU","active":"active"}]'></Breadcrumb>
        <nomenclatura-onu-review></nomenclatura-onu-review>
    @else
        <div class="alert alert-warning m-3">No tienes permiso para ver esta pantalla.</div>
    @endif
@endsection
