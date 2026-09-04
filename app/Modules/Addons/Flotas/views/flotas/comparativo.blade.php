@extends('core-layout::master')

@section('title')
    Flotas — Análisis comparativo de gastos
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Flotas","url":"/flotas"},{"title":"Análisis comparativo","active":"active"}]'></Breadcrumb>
    <fleet-expense-comparison base-url="{{ url('/flotas') }}"></fleet-expense-comparison>
@endsection
