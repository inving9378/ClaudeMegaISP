@extends('core-layout::master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    @if(auth()->user() && auth()->user()->can('client_edit_client'))
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
            module-tabs="{{ $moduleTabs }}"
            id="{{ $id }}"
            authuserid="{{ $authuserid }}"
            office-lat="{{ config('app.office_lat') }}"
            office-lng="{{ config('app.office_lng') }}"
        ></client-crud>

        @if(auth()->user() && auth()->user()->can('payments_assign_clabe'))
            <div class="row q-mt-md">
                <div class="col-12">
                    <client-clabe-card :client-id="{{ $id }}"></client-clabe-card>
                </div>
            </div>
        @endif
    @endif
@endsection
