@extends('core-layout::master')
@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Crm",active:"active"}]></Breadcrumb>
    <Crm-Datatable module="crm" model="Crm" @if(auth()->user() && auth()->user()->can($group . '_add_' . $module))
        add="Agregar Crm"
        @endif
        list="Listado de Crm">
        @can('crm_document_view_huerfanos')
            <template #header-extra>
                <a href="/crm/documentos-huerfanos" class="btn btn-outline-secondary waves-effect waves-light ms-2">
                    Documentos huérfanos
                </a>
            </template>
        @endcan
    </Crm-Datatable>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="Crm"></Message>
    @endif
@endsection
