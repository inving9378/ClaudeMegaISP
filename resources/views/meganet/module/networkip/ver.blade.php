@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    <Breadcrumb :list="[
        {title: 'Red', link: '/red/ipv4/listar'},
        {title: 'Esta Red', active: true}
    ]"></Breadcrumb>

    <network-ver
        id="{{ $id }}"
    ></network-ver>

    @if(session()->has('message'))
        <Message
            message="{{ session()->get('message') }}"
            module="red/ipv4"
        ></Message>
    @endif
@endsection


