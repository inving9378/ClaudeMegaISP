@extends('core-layout::master')
@section('title') Servicios contratables @endsection

@section('content')
    <contratable-catalog-form id="{{ $id }}"></contratable-catalog-form>
@endsection
