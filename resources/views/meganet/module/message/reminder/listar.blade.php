@extends('meganet.layout.master')

@section('content')
asdasdasd
     @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="StoreZone"></Message>
    @endif
@endsection
