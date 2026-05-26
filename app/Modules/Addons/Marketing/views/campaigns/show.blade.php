@extends('core-layout::master')

@section('title')
    Campaña: {{ $campaign->title }}
@endsection

@section('content')
    <marketing-campaign-show :campaign-id="{{ $campaign->id }}"></marketing-campaign-show>
@endsection
