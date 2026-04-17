@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Setting"},{title:"Debit",active:"active"}]></Breadcrumb>
    <Command-Config action="update" commands="{{ $commands->toJson() }}"
        frequency_has_time = "{{ $frequency_has_time->toJson() }}" url="{{ url('/') }}">
    </Command-Config>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="CommandConfig"></Message>
    @endif
@endsection
