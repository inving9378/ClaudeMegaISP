@extends('core-layout::master')
@section('title') @lang('translation.Dashboard') @endsection

@section('content')
    <Breadcrumb
        :list=[{title:"Red"},{title:"Router",active:"active"}]
    ></Breadcrumb>
    <Datatable
        module="red/router"
        model="Router"
        @if(auth()->user() && auth()->user()->can($group.'_add_'.\Illuminate\Support\Str::lower($module)))
        add="Agregar Router"
        @endif
        list="Listado Routers"
    ></Datatable>
    @if(session()->has('message'))
        <Message
            message="{{ session()->get('message') }}"
            module="red/router"
        ></Message>
    @endif
@endsection


