@extends('meganet.layout.master')
@section('title')
    Mapa
@endsection
@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Mapa"}]></Breadcrumb>
    <leaflet-map />
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}"></Message>
    @endif
@endsection
@push('scripts')
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script src="https://unpkg.com/dom-to-image@2.6.0/dist/dom-to-image.min.js"></script>
@endpush
