@extends('core-layout::master')

@section('title', 'Bandeja de Conciliación')

@section('styles')
    <link rel="stylesheet" href="{{ asset('plugins/quasar/extras/bootstrap-icons/bootstrap-icons.css') }}">
@endsection

@section('content')
    <div>
        <pagos-conciliacion></pagos-conciliacion>
    </div>
@endsection
