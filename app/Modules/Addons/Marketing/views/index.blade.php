@extends('core-layout::master')

@section('title')
    Marketing
@endsection

@section('content')
    <marketing-dashboard :auth-user-id="{{ auth()->id() ?? 'null' }}"></marketing-dashboard>
@endsection
