@extends('core-layout::master')

@section('title')
    MegaVoz — Asistente IA
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"VoIP"},{"title":"Asistente IA","active":"active"}]'></Breadcrumb>
    <div>
        <voip-ia-bot-manager
            csrf-token="{{ csrf_token() }}"
            base-url="{{ url('/voip') }}"
            can-config="{{ auth()->user()->can('voip.ia-bot.config') ? '1' : '0' }}"
            can-leads="{{ auth()->user()->can('voip.ia-bot.leads') ? '1' : '0' }}"
            can-kb="{{ auth()->user()->can('voip.ia-bot.kb') ? '1' : '0' }}"
        ></voip-ia-bot-manager>
    </div>
@endsection
