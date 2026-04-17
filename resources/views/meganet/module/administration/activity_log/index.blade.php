@extends('meganet.layout.master')
@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"LogActivity",active:"active"}]></Breadcrumb>
    <Datatable module="administracion/activity_log" model="ActivityLog" list="Logs">
    </Datatable>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="LogActivity"></Message>
    @endif
    <Show-Activity></Show-Activity>
@endsection
