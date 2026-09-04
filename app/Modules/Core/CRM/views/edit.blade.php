@extends('core-layout::master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    @if(auth()->user() && auth()->user()->can('crm_edit_crm'))
        <crm-crud
            action="update/{{$id}}"
            tabs="{{ $tabs }}"
            id="{{ $id }}"
        >
        </crm-crud>
    @endif
@endsection
