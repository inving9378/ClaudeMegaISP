@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Setting"},{title:"ServiceAddressList",active:"active"}]></Breadcrumb>
    <service-in-address-list-listar></service-in-address-list-listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="ServiceInAddressList"></Message>
    @endif
@endsection
