@extends('core-layout::master')

@section('title', 'Mapa de Red')

@section('content')
    @if(auth()->user()->can('mapa_red_view'))
        <leaflet-map-red />
        @if (session()->has('message'))
            <Message message="{{ session()->get('message') }}"></Message>
        @endif
    @endif
@endsection
@push('scripts')
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script src="https://unpkg.com/dom-to-image@2.6.0/dist/dom-to-image.min.js"></script>
@endpush
