@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    <div id="router">
        <add-router-crud
            action="add"
        ></add-router-crud>
    </div>
@endsection
