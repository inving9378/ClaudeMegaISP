@extends('core-layout::master')

@section('title', 'Talento — Colaboradores')

@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-flex align-items-center justify-content-between">
                    <h4 class="mb-0">Talento &mdash; Colaboradores</h4>
                </div>
            </div>
        </div>
        <div>
            <talento-colaboradores></talento-colaboradores>
        </div>
    </div>
</div>
@endsection
