@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    @can('crm_edit_crm')
        <crm-crud
            action="update/{{$id}}"
            tabs="{{ $tabs }}"
            id="{{ $id }}"
        >
        </crm-crud>
    @endcan
@endsection
