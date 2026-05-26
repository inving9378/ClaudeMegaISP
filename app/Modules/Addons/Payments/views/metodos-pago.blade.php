@extends('core-layout::master')
@section('title') Métodos de Pago SPEI @endsection

@section('content')
    @can('payments_manage_providers')
        <payment-methods></payment-methods>
    @endcan
@endsection
