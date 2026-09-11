@extends('core-layout::master')

@section('title', 'Mapa de Red')

@section('content')
    @if(auth()->user()->can('mapa_red_view'))
        <leaflet-map-red
            :flujo-animado-enabled="{{ config('mapared.flujo_animado_enabled') ? 'true' : 'false' }}"
            flujo-animado-pilot-route-id="{{ config('mapared.flujo_animado_pilot_route_id') }}"
            :flujo-animado-pilots="{{ json_encode(config('mapared.flujo_animado_pilots', [])) }}"
        />
        @if (session()->has('message'))
            <Message message="{{ session()->get('message') }}"></Message>
        @endif
    @endif
@endsection
@push('scripts')
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script src="https://unpkg.com/dom-to-image@2.6.0/dist/dom-to-image.min.js"></script>
@endpush
