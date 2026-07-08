@extends('core-layout::master')
@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Cliente",active:"active"}]></Breadcrumb>
    @if (!empty($openReconciliationCount) && $openReconciliationCount > 0)
        <div class="alert alert-warning d-flex align-items-center justify-content-between mb-3" role="alert">
            <span>
                <i class="fas fa-exclamation-triangle mr-2"></i>
                Hay <strong>{{ $openReconciliationCount }}</strong>
                {{ $openReconciliationCount == 1 ? 'pago/conciliación pendiente' : 'pagos/conciliaciones pendientes' }}
                por resolver.
            </span>
            <a href="/finanzas/conciliacion" class="btn btn-sm btn-warning">Ver cola</a>
        </div>
    @endif
    <Datatable-Client module="cliente" model="Client" list="Listado de Clientes" status="{{ json_encode($status) }}"
        color_datatable ="{{ $color_datatable }}" all_columns_by_module="{{ json_encode($allColumnsByModule) }}"
        header_columns_by_module ="{{ json_encode($columnsByUserAuthAndModule) }}"
        @if (isset($filters)) filters="{{ $filters }}" @endif
        array_all_status="{{ json_encode($allStatusToFilter) }}">
    </Datatable-Client>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="Cliente"></Message>
    @endif
@endsection
