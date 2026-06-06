@extends('core-layout::master')
@section('title', 'Talento — Órdenes de Trabajo')
@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">Talento &mdash; Órdenes de Trabajo</h4>
        </div>
        <div id="init-vue">
            <talento-ordenes></talento-ordenes>
        </div>
    </div>
</div>
@endsection
