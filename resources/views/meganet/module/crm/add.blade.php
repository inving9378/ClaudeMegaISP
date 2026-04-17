@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    @can('crm_add_crm')
        <add-crm-crud
            action="add"
        ></add-crm-crud>
    @endcan
@endsection
