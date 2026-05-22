@extends('meganet.layout.master')

@section('title')
    Instancias WhatsApp
@endsection

@section('content')
    <whatsapp-instance-manager :fake-mode="{{ $fakeMode ? 'true' : 'false' }}"></whatsapp-instance-manager>
@endsection
