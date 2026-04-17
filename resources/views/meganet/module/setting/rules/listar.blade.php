@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Reglas"},{title:"Reglas",active:"active"}]></Breadcrumb>
    <rules-listar></rules-listar>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="InventoryItemType"></Message>
    @endif
@endsection
