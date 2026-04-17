@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    @can('client_add_client')
        <add-client-crud
            action="add"
        ></add-client-crud>
    @endcan
@endsection
