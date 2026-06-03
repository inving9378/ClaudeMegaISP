@extends('core-layout::master')

@section('title')
    Flotas — Notificaciones
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Flotas","url":"/flotas"},{"title":"Notificaciones","active":"active"}]'></Breadcrumb>
    <fleet-notification-log base-url="{{ url('/flotas') }}"></fleet-notification-log>
@endsection
