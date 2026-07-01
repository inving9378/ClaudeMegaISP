@extends('core-layout::master')
@section('title') Registrar pago reportado @endsection

@section('content')
    @can('payments_capture_manage')
        <manual-payment-capture
            :methods="{{ json_encode($methods) }}"
            :accounts="{{ json_encode($accounts) }}"
            :default-method-id="{{ (int) $defaultMethodId }}">
        </manual-payment-capture>
    @endcan
@endsection
