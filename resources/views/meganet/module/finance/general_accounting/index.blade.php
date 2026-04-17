@extends('meganet.layout.master')
@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <General-Accounting-Index>

    </General-Accounting-Index>

       @if(session()->has('message'))
        <Message
            message="{{ session()->get('message') }}"
            module="GeneralAccounting"
        ></Message>
    @endif
@endsection
