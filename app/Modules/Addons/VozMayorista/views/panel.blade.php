@extends('core-layout::master')

@section('content')
    <div id="init-vue">
        {{-- El permiso ya lo exige la ruta; el @if evita pintar el hueco si algún día
             la vista se monta desde otro lado. Nunca @can() (convención del proyecto). --}}
        @if(auth()->user()->can('voz.panel.view'))
            <voz-panel></voz-panel>
        @endif
    </div>
@endsection
