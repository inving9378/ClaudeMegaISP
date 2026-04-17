@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    <voz-crud
        action="update/{{$id}}"
        id="{{ $id }}"
    ></voz-crud>
@endsection
