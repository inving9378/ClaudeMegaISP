@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    <ticket-crud
        action="add"
        client="{{ $clientId }}"
    ></ticket-crud>
@endsection

