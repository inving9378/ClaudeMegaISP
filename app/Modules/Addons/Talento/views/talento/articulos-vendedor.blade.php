@extends('core-layout::master')

@section('title', 'Talento — Artículos de Vendedor')

@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-flex align-items-center justify-content-between">
                    <h4 class="mb-0">Talento &mdash; Artículos de Vendedor</h4>
                </div>
            </div>
        </div>
        <div>
            <talento-articulos-vendedor></talento-articulos-vendedor>
        </div>
    </div>
</div>
@endsection
