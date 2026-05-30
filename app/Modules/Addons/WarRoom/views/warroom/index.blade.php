@extends('core-layout::master')

@section('title')
    War Room Ejecutivo · MegaISP
@endsection

@section('content')
    <warroom-dashboard
        csrf-token="{{ csrf_token() }}"
        base-url="{{ url('/warroom') }}"
    ></warroom-dashboard>
@endsection
