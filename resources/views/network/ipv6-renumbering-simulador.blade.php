@extends('core-layout::master')
@section('title') Simulador de renumeración IPv6 @endsection

@section('content')
    @can('ipv6.manage')
        <ipv6-renumbering-simulator></ipv6-renumbering-simulator>
    @endcan
@endsection
