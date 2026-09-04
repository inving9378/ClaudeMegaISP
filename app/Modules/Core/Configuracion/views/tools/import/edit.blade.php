@extends('core-layout::master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    @if(auth()->user() && auth()->user()->can('client_edit_client'))
        <Breadcrumb
            list="{{ $breadcrumb }}"
        ></Breadcrumb>
        <client-crud
            action="update/{{$id}}"
            show="{{ $show }}"
            id="{{ $id }}"
        ></client-crud>
    @endif
@endsection
