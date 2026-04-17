@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    <div id="plan">
        <internet-crud
            action="update/{{$id}}"
            id="{{ $id }}"
        ></internet-crud>
    </div>
@endsection
