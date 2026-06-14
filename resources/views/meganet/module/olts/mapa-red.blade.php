@extends('core-layout::master')
@section('title')
    Mapa de Red OLT
@endsection

@section('content')
    <Breadcrumb :list=[{title:"OLTs"},{title:"Mapa de red"}]></Breadcrumb>
    <olt-red-map />
@endsection
