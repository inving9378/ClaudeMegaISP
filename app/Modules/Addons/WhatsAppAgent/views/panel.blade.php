@extends('core-layout::master')

@section('title')
    WhatsApp
@endsection

@section('content')
    <whatsapp-panel :fake-mode="{{ $fakeMode ? 'true' : 'false' }}"></whatsapp-panel>
@endsection
