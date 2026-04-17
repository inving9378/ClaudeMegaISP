@extends('meganet.layout.master')
@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Calendario",active:"active"}]></Breadcrumb>
    <calendar-index module_id={{ $module_id }} imgbase="{{ asset('/') }}"></calendar-index>
@endsection
