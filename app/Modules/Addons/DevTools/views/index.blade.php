@extends('core-layout::master-without-nav')

@section('title')
    DevTools
@endsection

@section('content')
    <devtools-panel
        ttyd-url="{{ $ttydUrl }}"
        csrf-token="{{ $csrfToken }}"
        :user-name="{{ json_encode(auth()->user()->name ?? 'Dev') }}"
    ></devtools-panel>
@endsection
