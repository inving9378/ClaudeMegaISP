@extends('meganet.layout.master')
@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Administracion",active:"active"}]></Breadcrumb>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <div class="row justify-content-center mt-3">
                        <div class="col-xl-5 col-lg-8">
                            <div class="text-center">
                                <h5>Administracion</h5>
                            </div>
                        </div>
                        <!-- end col -->
                    </div>
                    <hr>
                    <!-- end row -->

                    <div class="row d-flex">
                        <div style="width: 250px">
                            <div class="card cursor-pointer bx-tada-hover">
                                <a href="{{ url('/administracion/estado') }}">
                                    <div class="card-body overflow-hidden position-relative">
                                        <div>
                                            <i class="bx bx-help-circle widget-box-1-icon text-primary"></i>
                                        </div>
                                        <div class="faq-count d-flex">
                                            <h5 class="text-primary m-0"><i
                                                    class="fa fa-fw fa-2x fa-globe fa-align-left"></i></h5> <span
                                                class="ms-1 align-self-center">Estado</span>
                                        </div>
                                    </div>
                                </a>
                                <!-- end card body -->
                            </div>

                        </div>
                        <div style="width: 250px">
                            <div class="card cursor-pointer bx-tada-hover">
                                <a href="{{ url('/administracion/municipio') }}">
                                    <div class="card-body overflow-hidden position-relative">
                                        <div>
                                            <i class="bx bx-help-circle widget-box-1-icon text-primary"></i>
                                        </div>
                                        <div class="faq-count d-flex">
                                            <h5 class="text-primary m-0"><i
                                                    class="fa fa-fw fa-2x fa-globe fa-align-left"></i></h5> <span
                                                class="ms-1 align-self-center">Municipio</span>
                                        </div>
                                    </div>
                                </a>
                                <!-- end card body -->
                            </div>
                            <!-- end card -->
                        </div>
                        <div style="width: 250px">
                            <div class="card cursor-pointer bx-tada-hover">
                                <a href="{{ url('/administracion/colonia') }}">
                                    <div class="card-body overflow-hidden position-relative">
                                        <div>
                                            <i class="bx bx-help-circle widget-box-1-icon text-primary"></i>
                                        </div>
                                        <div class="faq-count d-flex">
                                            <h5 class="text-primary m-0"><i
                                                    class="fa fa-fw fa-2x fa-globe fa-align-left"></i></h5> <span
                                                class="ms-1 align-self-center">Colonia</span>
                                        </div>
                                    </div>
                                </a>
                                <!-- end card body -->
                            </div>
                            <!-- end card -->
                            <!-- end row -->
                        </div>
                        <div style="width: 250px">
                            <div class="card cursor-pointer bx-tada-hover">
                                <a href="{{ url('/administracion/metotdo-de-pago') }}">
                                    <div class="card-body overflow-hidden position-relative">
                                        <div>
                                            <i class="bx bx-help-circle widget-box-1-icon text-primary"></i>
                                        </div>
                                        <div class="faq-count d-flex">
                                            <h5 class="text-primary m-0"><i
                                                    class="fa fa-fw fa-2x fa-globe fa-align-left"></i></h5> <span
                                                class="ms-1 align-self-center">Método de Pago</span>
                                        </div>
                                    </div>
                                </a>
                                <!-- end card body -->
                            </div>
                            <!-- end card -->
                            <!-- end row -->
                        </div>

                        <div style="width: 250px">
                            <div class="card cursor-pointer bx-tada-hover">
                                <a href="{{ url('/administracion/ift') }}">
                                    <div class="card-body overflow-hidden position-relative">
                                        <div>
                                            <i class="bx bx-help-circle widget-box-1-icon text-primary"></i>
                                        </div>
                                        <div class="faq-count d-flex">
                                            <h5 class="text-primary m-0"><i
                                                    class="fa fa-fw fa-2x fa-globe fa-align-left"></i></h5> <span
                                                class="ms-1 align-self-center">Ift</span>
                                        </div>
                                    </div>
                                </a>
                                <!-- end card body -->
                            </div>
                            <!-- end card -->
                            <!-- end row -->
                        </div>
                    </div>
                    <!-- end card -->
                </div>
                <!-- end col -->
            </div>
        </div>
    </div>

    <Index-Administration
    url="{{ url('/') }}"
    >

    </Index-Administration>

@endsection
