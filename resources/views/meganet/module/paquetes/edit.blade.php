@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    <bundle-crud
        action="update/{{$id}}"
        id="{{ $id }}"
    ></bundle-crud>
@endsection
