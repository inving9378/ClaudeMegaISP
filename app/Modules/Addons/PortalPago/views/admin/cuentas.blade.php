@extends('core-layout::master')

@section('title', 'Cuentas de Cobro')

@section('styles')
    <link rel="stylesheet" href="{{ asset('plugins/quasar/extras/bootstrap-icons/bootstrap-icons.css') }}">
@endsection

@section('content')
    <div>
        <pagos-cuentas></pagos-cuentas>
    </div>
@endsection
