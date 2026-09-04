@extends('core-layout::master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    @if(auth()->user() && auth()->user()->can('client_add_client'))
        <add-client-crud
            action="add"
        ></add-client-crud>
    @endif
@endsection
