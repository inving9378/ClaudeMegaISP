@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    <voz-crud
        action="add"
    ></voz-crud>
@endsection
