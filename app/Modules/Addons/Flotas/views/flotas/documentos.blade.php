@extends('core-layout::master')

@section('title')
    Flotas — Documentos
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Flotas","url":"/flotas"},{"title":"Documentos","active":"active"}]'></Breadcrumb>
    <fleet-documents-dashboard base-url="{{ url('/flotas') }}"></fleet-documents-dashboard>
@endsection
