@extends('core-layout::master')

@section('content')
    {{-- Ruta como Clientes (§2.13): mismo componente Breadcrumb y mismo lugar. --}}
    <Breadcrumb :list='[{"title":"Página"},{"title":"Marketing","link":"/marketing"},{"title":"Leads","link":"/marketing/leads"},{"title":"Conversaciones","active":true}]'></Breadcrumb>
<div>
    <marketing-conversations-view
        :initial-conversation-id="{{ isset($conversationId) ? (int)$conversationId : 'null' }}"
        :auth-user-id="{{ auth()->id() }}"
    ></marketing-conversations-view>
</div>
@endsection
