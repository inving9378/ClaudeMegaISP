@extends('core-layout::master')
@section('title') Métodos de Pago SPEI @endsection

@section('content')
    @if(auth()->user() && auth()->user()->can('payments_manage_providers'))
        <payment-methods></payment-methods>
    @endif
@endsection
