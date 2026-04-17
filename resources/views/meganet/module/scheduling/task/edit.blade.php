@extends('meganet.layout.master')
@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <task-edit action="update/{{ $id }}" id="{{ $id }}" observations="{{ $observations }}"
        archived ="{{ $archived }}"></task-edit>
@endsection
