@extends('core-layout::master')

@section('content')
    <Breadcrumb :list="[{title:'Pagina'},{title:'Inventario'},{title:'Facturas de Proveedores',href:'/inventory/supplier-invoice'},{title:'Nueva Factura',active:true}]"></Breadcrumb>
    <supplier-invoice-create></supplier-invoice-create>
@endsection
