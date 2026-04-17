@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    <Breadcrumb
        :list=[{title:"Red"},{title:"Ipv4"},{title:"Listar",active:"active"}]
    ></Breadcrumb>

    <network-listar
    ></network-listar>

    @if(session()->has('message'))
        <Message
            message="{{ session()->get('message') }}"
            module="red/ipv4"
        ></Message>
    @endif
@endsection


