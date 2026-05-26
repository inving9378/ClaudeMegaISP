@extends('core-layout::master')

@section('title')
    Detalle de Lead — Marketing
@endsection

@section('content')
    <marketing-lead-detail lead-id="{{ request()->route('id') }}"></marketing-lead-detail>
@endsection
