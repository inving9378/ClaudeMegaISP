@extends('core-layout::master')

@section('title', 'Portal de Pago')

@section('styles')
    <link rel="stylesheet" href="{{ asset('plugins/quasar/extras/bootstrap-icons/bootstrap-icons.css') }}">
@endsection

@section('content')
    <div>
        <pagos-dashboard></pagos-dashboard>
    </div>
@endsection
