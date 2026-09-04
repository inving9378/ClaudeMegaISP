@extends('core-layout::master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    @if(auth()->user() && auth()->user()->can('crm_add_crm'))
        <add-crm-crud
            action="add"
        ></add-crm-crud>
    @endif
@endsection
