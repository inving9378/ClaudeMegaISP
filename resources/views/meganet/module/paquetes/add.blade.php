@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    <bundle-crud
        action="add"
    ></bundle-crud>
@endsection
