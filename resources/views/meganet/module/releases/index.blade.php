@extends('meganet.layout.master')
@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <releases-index releases="{{ json_encode($releases) }}" next_page_url="{{ $next_page_url }}"></releases-index>
@endsection
