@extends('core-layout::master')

@section('title', 'Ligas de Pago')

@section('styles')
    <link rel="stylesheet" href="{{ asset('plugins/quasar/extras/bootstrap-icons/bootstrap-icons.css') }}">
@endsection

@section('content')
    <div>
        <pagos-links :cuentas="{{ json_encode($cuentas) }}"></pagos-links>
    </div>
@endsection
