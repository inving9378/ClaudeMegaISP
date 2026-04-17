@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    @can('client_edit_client')
        <Breadcrumb
            list="{{ $breadcrumb }}"
        ></Breadcrumb>

        <div class="d-flex" style="position: absolute;right: 25px;top: 125px;font-size: 20px">
            @if($after) <a href="/cliente/editar/{{$after}}" class="me-2"> < </a> @endif
            @if($next) <a href="/cliente/editar/{{$next}}"> > </a> @endif
        </div>
        <client-crud
            action="update/{{$id}}"
            tabs="{{ $tabs }}"
            id="{{ $id }}"
            authuserid="{{ $authuserid }}"
        ></client-crud>
    @endcan
@endsection
