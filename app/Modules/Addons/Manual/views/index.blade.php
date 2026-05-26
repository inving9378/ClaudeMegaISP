@extends('core-layout::master')

@section('title')
    Manual de Usuario
@endsection

@section('content')
    <manual-index
        :is-developer="{{ auth()->user()?->isDevelopment() ? 'true' : 'false' }}">
    </manual-index>
@endsection
