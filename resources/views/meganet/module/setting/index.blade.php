@extends('meganet.layout.master')
@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Configuracion",active:"active"}]></Breadcrumb>
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <div class="row justify-content-center mt-3">
                        <div class="col-xl-5 col-lg-8">
                            <div class="text-center">
                                <h5>Configuracion</h5>
                            </div>
                        </div>
                    </div>
                    <hr>
                </div>
            </div>
        </div>
    </div>
    <debt-payment-client id="debtpaymentclient"></debt-payment-client>
    <Index-Setting url="{{ url('/') }}">
    </Index-Setting>
@endsection
