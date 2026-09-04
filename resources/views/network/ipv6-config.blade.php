@extends('core-layout::master')
@section('title') Configuración IPv6 @endsection

@section('content')
    @if(auth()->user() && auth()->user()->can('ipv6.manage'))
        <ipv6-config></ipv6-config>
    @endif
@endsection
