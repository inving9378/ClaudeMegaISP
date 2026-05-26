@extends('core-layout::master')

@section('title')
    Editor de formulario — Marketing
@endsection

@section('content')
    <marketing-lead-form-editor form-id="{{ request()->route('id') ?? '' }}"></marketing-lead-form-editor>
@endsection
