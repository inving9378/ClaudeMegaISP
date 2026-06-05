@extends('core-layout::master')

@section('title')
    Flotas — Suscripciones SaaS
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Flotas","url":"/flotas"},{"title":"Suscripciones","active":"active"}]'></Breadcrumb>
    <fleet-subscription-dashboard base-url="{{ url('/flotas') }}"></fleet-subscription-dashboard>
@endsection
