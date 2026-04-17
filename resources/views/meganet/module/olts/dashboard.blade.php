@extends('meganet.layout.master')
@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"OLTs"},{title:"Dashboard"}]></Breadcrumb>
    <olts-dashboard />
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="red/router"></Message>
    @endif
@endsection
