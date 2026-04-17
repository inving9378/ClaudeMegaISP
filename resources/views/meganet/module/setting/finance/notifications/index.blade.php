@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Setting"},{title:"ConfigFinanceNotification",active:"active"}]></Breadcrumb>


    <Config-Finance-Notification-Index>

    </Config-Finance-Notification-Index>





    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="ConfigFinanceNotification"></Message>
    @endif
@endsection
