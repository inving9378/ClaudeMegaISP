@extends('core-layout::master')

@section('title')
    Líneas WhatsApp
@endsection

@section('content')
    <whatsapp-instance-manager :fake-mode="{{ $fakeMode ? 'true' : 'false' }}"></whatsapp-instance-manager>
@endsection
