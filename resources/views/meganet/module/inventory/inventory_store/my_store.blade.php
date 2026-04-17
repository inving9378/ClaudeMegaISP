@extends('meganet.layout.master')

@section('content')
    <store :store_id = "{{$id}}" url_base={{ asset('storage/') }}></store>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="InventoryStore"></Message>
    @endif
@endsection
