@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Configuración"},{title:"Reglas"},{title:"Editar",active:"active"}]></Breadcrumb>
    <form-rule :object="{{ $model }}" :sellers-options="{{ $sellers_type }}" :sellers-list="{{ $sellers_list }}" :contracts-durations="{{ $contracts_durations }}"/>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="InventoryItemType"></Message>
    @endif
@endsection
