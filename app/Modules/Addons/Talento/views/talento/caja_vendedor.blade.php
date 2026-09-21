@extends('core-layout::master')
@section('title', 'Talento — Caja de vendedor')
@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="page-title-box">
            <h4 class="mb-0">Talento &mdash; Caja diaria de efectivo</h4>
        </div>
        <div><talento-caja-vendedor></talento-caja-vendedor></div>
    </div>
</div>
@endsection
