@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    <custom-crud
        action="add"
    ></custom-crud>
@endsection
