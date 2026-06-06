@extends('core-layout::master')

@section('title', 'Talento — Custodia de Material')

@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-flex align-items-center justify-content-between">
                    <h4 class="mb-0">Talento &mdash; Custodia de Material</h4>
                </div>
            </div>
        </div>
        <div>
            <talento-custodia></talento-custodia>
        </div>
    </div>
</div>
@endsection
