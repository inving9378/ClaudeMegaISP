@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Configuración"},{title:"Reglas"},{title:"Nueva",active:"active"}]></Breadcrumb>
    <form-rule :sellers-options="{{ $sellers_type }}" :contracts-durations="{{ $contracts_durations }}"/>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="InventoryItemType"></Message>
    @endif
@endsection
