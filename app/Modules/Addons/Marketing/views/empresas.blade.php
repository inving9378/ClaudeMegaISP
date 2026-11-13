@extends('core-layout::master')
@section('title') Empresas y equipo · Marketing @endsection
@section('content')
    <marketing-empresas-equipo :auth-user-id="{{ auth()->id() }}"></marketing-empresas-equipo>
@endsection
