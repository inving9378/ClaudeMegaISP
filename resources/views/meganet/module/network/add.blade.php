@extends('core-layout::master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    <Breadcrumb
        :list=[{title:"Red"},{title:"Ipv4"},{title:"Añadir",active:"active"}]
    ></Breadcrumb>
        <add-network-crud
            action="add"
        ></add-network-crud>
@endsection
