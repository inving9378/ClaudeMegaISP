<template>
    <div class="container-administration-button-top">
        <q-btn class="btn-dark m-2" @click="toggleContainerAdministration(0)">
            Todos
        </q-btn>
        <q-btn
            class="btn-primary m-2"
            @click="toggleContainerAdministration(1)"
        >
            Meganet
        </q-btn>
        <q-btn
            class="btn-success m-2"
            @click="toggleContainerAdministration(2)"
        >
            Registros
        </q-btn>
        <q-btn
            class="btn-warning m-2"
            @click="toggleContainerAdministration(3)"
        >
            Información
        </q-btn>
        <q-btn class="btn-danger m-2" @click="toggleContainerAdministration(4)">
            Informes
        </q-btn>
        <q-btn class="btn-danger m-2" @click="showScripts">
            Scripts Ejecutables
        </q-btn>
    </div>

    <div class="container-administration">
        <div
            class="item-administration row"
            style="border-left-color: #1976d2"
            v-show="selectedDiv === 1 || selectedDiv === 0"
        >
            <h3>Meganet</h3>
            <div class="row d-flex">
                <div class="col-md-3">
                    <div class="cursor-pointer">
                        <a :href="`${url}/administracion/user`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-cogs"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Administradores</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer">
                        <a :href="`${url}/administracion/rol`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Roles</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer">
                        <a :href="`${url}/administracion/socios`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-handshake"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Socios</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer">
                        <a :href="`${url}/administracion/ubicacion`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-globe"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Ubicaciones</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer">
                        <a :href="`${url}/administracion/sucursal`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-building"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Sucursales</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-key"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Claves Api</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
            </div>
        </div>
        <div
            class="item-administration row"
            style="border-left-color: #2ab57d"
            v-show="selectedDiv === 2 || selectedDiv === 0"
        >
            <h3>Registros</h3>
            <div class="row d-flex">
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-success m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-wrench"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Operaciones</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-success m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-lock"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Interno</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-success m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-door-open"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Portal</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-success m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Archivos</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>

                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-success m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >E-Mail</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>

                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-success m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Sms</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>

                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-success m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Sesiones</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>

                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-success m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Api</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>

                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-success m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Cambiar los estados y los planes</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>

                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-success m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Estados y servicios pendientes</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>

                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-success m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Huawei Operation Logs</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
            </div>
        </div>
        <div
            class="item-administration row"
            style="border-left-color: #ffc107"
            v-show="selectedDiv === 3 || selectedDiv === 0"
        >
            <h3>Información</h3>
            <div class="row d-flex">
                <div class="col-md-3">
                    <div class="cursor-pointer">
                        <a :href="`${url}/administracion/activity_log`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-warning m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-scroll"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Logs</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-warning m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Licencia</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-warning m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Sitios</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div
                    class="col-md-3"
                    v-if="
                        hasPermission.data.canView(
                            `documentation_view_documentation`
                        )
                    "
                >
                    <div class="cursor-pointer">
                        <!-- <a :href="`${url}/administracion/documentation`" -->
                        <a :href="`${url}/administracion/documentation/documentation_menu`">
                            <div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-warning m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Documentación</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-warning m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Foto</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-warning m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Video Tutoriales</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-warning m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Deployments Videos</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-warning m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Facebook</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-warning m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Documentación de la Api</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
            </div>
        </div>
        <div
            class="item-administration row"
            style="border-left-color: #dc3545"
            v-show="selectedDiv === 4 || selectedDiv === 0"
        >
            <h3>Informes</h3>
            <div class="row d-flex">
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-danger m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Uso del plan de Internet</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-danger m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Exportacion de Servicios</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-danger m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Informe Financiero del Plan</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-danger m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Uso de Internet</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-danger m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Estadística de tarjeta de recarga</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-danger m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Reporte de (obtener de la imagen)</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-danger m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Registros de Finanzas</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-danger m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Declaraciones</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-danger m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Informe de impuestos</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>

                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-danger m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Informe de las Facturas</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-danger m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Informe de nuevos servicios</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-danger m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Precios Personalizados y
                                        descuentos</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-danger m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Categoria de Transacciones</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-danger m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Informes del ticket</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-danger m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Customer Contract</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-danger m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Agents</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
                <div class="col-md-3">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-danger m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Roles</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { onMounted, reactive, ref } from "vue";
import Permission from "../../../helpers/Permission";
import { allViewHasPermission } from "../../../helpers/Request";

export default {
    name: "IndexAdministration",
    props: {
        url: String,
    },
    components: {},
    setup(props) {
        const selectedDiv = ref(0);
        const toggleContainerAdministration = (index) => {
            if (index == selectedDiv.value) {
                selectedDiv.value = 0;
            } else {
                selectedDiv.value = index;
            }
        };

        const hasPermission = reactive({
            data: new Permission({}),
        });

        onMounted(async () => {
            hasPermission.data = new Permission(await allViewHasPermission());
        });

        const disabled = ref(false);

        const showScripts = () => {
            window.open("/administracion/show_scripts");
        };

        return {
            toggleContainerAdministration,
            selectedDiv,
            disabled,
            showScripts,
            hasPermission,
        };
    },
};
</script>

<style scoped>
.disabled {
    cursor: not-allowed;
    pointer-events: none;
}
</style>
