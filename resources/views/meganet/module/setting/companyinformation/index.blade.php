@extends('meganet.layout.master')

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Setting"},{title:"CompanyInformation",active:"active"}]></Breadcrumb>
    <Company-Information-Index imgbase="{{ asset('/') }}">

    </Company-Information-Index>
    @if (session()->has('message'))
        <Message message="{{ session()->get('message') }}" module="CompanyInformation"></Message>
    @endif
@endsection
