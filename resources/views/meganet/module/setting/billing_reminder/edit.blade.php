@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Setting"},{title:"BillingReminder",active:"active"}]></Breadcrumb>
    <Billing-Reminder-Edit>

    </Billing-Reminder-Edit>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="BillingReminder"></Message>
    @endif
@endsection
