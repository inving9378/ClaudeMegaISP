@extends('core-layout::master')
@section('title', 'Talento — Compensación')
@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">Talento &mdash; Compensación</h4>
        </div>
        <div id="init-vue">
            <talento-compensacion></talento-compensacion>
        </div>
    </div>
</div>
@endsection
