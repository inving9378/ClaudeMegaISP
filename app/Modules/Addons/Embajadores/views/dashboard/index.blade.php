@extends('core-layout::master')

@section('title')
    Embajadores Meganet · Dashboard
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Embajadores Meganet"},{"title":"Dashboard","active":"active"}]'></Breadcrumb>
    <embajadores-dashboard
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/embajadores') }}"
    ></embajadores-dashboard>
@endsection
