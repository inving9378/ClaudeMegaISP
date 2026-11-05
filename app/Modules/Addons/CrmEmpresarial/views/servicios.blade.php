@extends('core-layout::master')

@section('title')
    CRM Empresarial — Servicios del expediente
@endsection

@section('content')
    <crme-servicios :expediente-id="{{ $expedienteId }}"></crme-servicios>
@endsection
