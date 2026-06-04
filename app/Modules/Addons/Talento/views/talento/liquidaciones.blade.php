@extends('core-layout::master')
@section('title', 'Talento — Liquidaciones')
@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">Talento &mdash; Liquidaciones Semanales</h4>
        </div>
        <div id="init-vue">
            <talento-liquidaciones></talento-liquidaciones>
        </div>
    </div>
</div>
@endsection
