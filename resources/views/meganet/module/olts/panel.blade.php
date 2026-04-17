@extends('meganet.layout.master')
@section('title')
    OLTs
@endsection

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"OLTs"}]></Breadcrumb>
    <olts-panel />
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}"></Message>
    @endif
@endsection
