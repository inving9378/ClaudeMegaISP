@extends('core-layout::master')
@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <task-edit action="update/{{ $id }}" id="{{ $id }}"
        archived ="{{ $archived }}"></task-edit>
@endsection
