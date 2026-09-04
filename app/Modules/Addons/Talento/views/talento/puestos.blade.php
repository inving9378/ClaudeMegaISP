@extends('core-layout::master')
@section('title', 'Talento — Catálogo de puestos')
@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="page-title-box">
            <h4 class="mb-0">Talento &mdash; Catálogo de puestos</h4>
        </div>
        <div><talento-puestos></talento-puestos></div>
    </div>
</div>
@endsection
