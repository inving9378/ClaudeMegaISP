@extends('core-layout::master')
@section('title')
    #{{ $item['id'] }} — Hoja de ruta
@endsection

@section('content')
    <roadmap-item-detalle item="{{ json_encode($item) }}"></roadmap-item-detalle>
@endsection
