@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    <div id="router">
        <router-crud
            action="update/{{$id}}"
            id="{{ $id }}"
        ></router-crud>
    </div>
@endsection
