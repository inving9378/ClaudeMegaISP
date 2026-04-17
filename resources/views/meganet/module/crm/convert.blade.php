
@extends('meganet.layout.master')
@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <convert-to-client action="update/{{ $id }}"
        id="{{ $id }}"></convert-to-client>
@endsection
