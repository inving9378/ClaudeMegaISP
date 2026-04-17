@extends('meganet.layout.master')
@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <import-crud action="create" modules={{ json_encode($modules) }} url="{{ url('/') }}" ></import-crud>
@endsection
