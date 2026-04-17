@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('css')
    <link href="{{ URL::asset('assets/libs/toastr/toastr.min.css')}}" rel="stylesheet" type="text/css" />
@endsection

@section('content')
    <ver-ticket
        action="update/{{$id}}"
        id="{{ $id }}"
    ></ver-ticket>
@endsection
