@extends('core-layout::master')
@section('title') Simulador de renumeración IPv6 @endsection

@section('content')
    @if(auth()->user() && auth()->user()->can('ipv6.manage'))
        <ipv6-renumbering-simulator></ipv6-renumbering-simulator>
    @endif
@endsection
