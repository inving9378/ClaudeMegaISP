@extends('meganet.layout.master')
@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <releases-description release="{{ json_encode($release) }}" descriptions="{{ json_encode($descriptions) }}"></releases-description>
@endsection
