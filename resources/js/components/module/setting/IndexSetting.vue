<template>
    <div class="container-administration-button-top">
        <q-btn class="btn-dark m-2" @click="toggleContainerAdministration(0)">
            Todos
        </q-btn>
        <q-btn
            v-if="hasPermission.data.canView(`config_view_system`)"
            class="btn-primary m-2"
            @click="toggleContainerAdministration(1)"
        >
            Sistema
        </q-btn>
        <q-btn
            v-if="hasPermission.data.canView(`config_view_main`)"
            class="btn-success m-2"
            @click="toggleContainerAdministration(2)"
        >
            Principal
        </q-btn>
        <q-btn
            v-if="hasPermission.data.canView(`config_view_finance`)"
            class="btn-warning m-2"
            @click="toggleContainerAdministration(3)"
        >
            Finanzas
        </q-btn>
        <q-btn
            v-if="hasPermission.data.canView(`config_view_network_management`)"
            class="btn-danger m-2"
            @click="toggleContainerAdministration(4)"
        >
            Gestion de Red
        </q-btn>
        <q-btn
            v-if="hasPermission.data.canView(`config_view_helpdesk`)"
            class="btn-lightblue m-2"
            @click="toggleContainerAdministration(5)"
        >
            Helpdesk
        </q-btn>
        <q-btn
            v-if="hasPermission.data.canView(`config_view_scheduling`)"
            class="btn-lightorange m-2"
            @click="toggleContainerAdministration(6)"
        >
            Actividades Programadas
        </q-btn>
        <q-btn
            v-if="hasPermission.data.canView(`config_view_potencial_customer`)"
            class="m-2 btn-green-custom"
            @click="toggleContainerAdministration(7)"
        >
            Clientes Potenciales
        </q-btn>
        <q-btn
            v-if="hasPermission.data.canView(`config_view_inventory`)"
            class="btn-primary m-2"
            @click="toggleContainerAdministration(8)"
        >
            Inventory
        </q-btn>
        <q-btn
            v-if="hasPermission.data.canView(`config_view_integrations`)"
            class="btn-warning m-2"
            @click="toggleContainerAdministration(9)"
        >
            Integraciones
        </q-btn>
        <q-btn
            v-if="hasPermission.data.canView(`config_view_voice`)"
            class="btn-success m-2"
            @click="toggleContainerAdministration(10)"
        >
            Voz
        </q-btn>
        <q-btn
            v-if="hasPermission.data.canView(`config_view_tools`)"
            class="btn-primary m-2"
            @click="toggleContainerAdministration(11)"
        >
            Herramientas
        </q-btn>
        <q-btn
            v-if="hasPermission.data.canView(`config_view_sales`)"
            class="btn-gray-custom m-2"
            @click="toggleContainerAdministration(12)"
        >
            Ventas
        </q-btn>
        <q-btn
            v-if="hasPermission.data.canView(`config_view_google_maps`)"
            class="btn-aqua-custom m-2"
            @click="toggleContainerAdministration(13)"
        >
            Leaflet Map
        </q-btn>
        <q-btn
            v-if="hasPermission.data.canView(`config_view_messages`)"
            class="btn-aqua-custom m-2"
            @click="toggleContainerAdministration(14)"
        >
            Mensajeria
        </q-btn>
    </div>

    <div class="container-administration">
        <div
            v-if="hasPermission.data.canView(`config_view_system`)"
            class="item-administration row"
            style="border-left-color: #1976d2"
            v-show="selectedDiv === 1 || selectedDiv === 0"
        >
            <h3>Sistema</h3>
            <div class="row d-flex">
                <div class="col-md-2">
                    <div class="cursor-pointer">
                        <a :href="`${url}/configuracion/additional-fields`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-clipboard-list"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Campos Adicionales</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-cogs"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Labels</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-cogs"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Traducciones Personalizadas</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-cogs"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Administrador de archivos</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer">
                        <a :href="`${url}/administracion/document_template`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-file-alt"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Plantillas</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="cursor-pointer">
                        <a
                            :href="`${url}/administracion/document_type_template`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-file-alt"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Tipos de Plantillas</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer">
                        <a :href="`${url}/configuracion/company-information`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-cogs"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Informacion de la Empresa</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
            </div>
        </div>
        <div
            v-if="hasPermission.data.canView(`config_view_main`)"
            class="item-administration row"
            style="border-left-color: #2ab57d"
            v-show="selectedDiv === 2 || selectedDiv === 0"
        >
            <h3>Principal</h3>
            <div class="row d-flex">
                <div class="col-md-2">
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
                                        >Preferencias</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Ubicacion</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >HTTP/SSL</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >API</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Modo Oculto</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Mapas</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Site Customization</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Clientes</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >CAP</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >SMS</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Portal</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Voz</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Tarjetas</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Lognotate</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
            </div>
        </div>
        <div
            v-if="hasPermission.data.canView(`config_view_finance`)"
            class="item-administration row"
            style="border-left-color: #ffc107"
            v-show="selectedDiv === 3 || selectedDiv === 0"
        >
            <h3>Finanzas</h3>
            <div class="row d-flex">
                <div class="col-md-2">
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
                                        >Automatización</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Configuraciones</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Emparejamiento</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Cambiar el plan</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer">
                        <a :href="`${url}/configuracion/metodos-de-pago`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-warning m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Métodos de pagos</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Categoría de transacciones</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Cuentas de Pago</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Categoría de coste</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Coste Fijo</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Impuestos</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="cursor-pointer">
                        <a :href="`${url}/configuracion/debitcustom`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-warning m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-cog"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Pago de deudas para clientes
                                        custom</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="cursor-pointer">
                        <a
                            href="javascript:void(0);"
                            data-bs-toggle="modal"
                            data-bs-target="#debtpaymentclient"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-warning m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-sync-alt"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Pago de deudas para clientes
                                        recurrentes</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
            </div>
        </div>
        <div
            v-if="hasPermission.data.canView(`config_view_network_management`)"
            class="item-administration row"
            style="border-left-color: #dc3545"
            v-show="selectedDiv === 4 || selectedDiv === 0"
        >
            <h3>Gestion de Red</h3>
            <div class="row d-flex">
                <div class="col-md-2">
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
                                        >Radius</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Radius Advanced</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Radius Fañover</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >MikroTok Api</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >IPv4</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >IPv6</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Netflow accounting</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >FUP</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Tipos de NAS</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Categorias de redes IP</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Categorias de host Ip</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Monitorización</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Tr-069(ACS)</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Trafic Class</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Huawei Supported Boards</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div
                    class="col-md-2"
                    v-if="
                        hasPermission.data.canView(
                            `nomenclature_view_nomenclature`
                        )
                    "
                >
                    <div class="cursor-pointer">
                        <a :href="`${url}/configuracion/nomenclature`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-danger m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-tags"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Nomenclatura</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>

                <div
                    class="col-md-2"
                    v-if="
                        hasPermission.data.canView(
                            `service_in_address_list_view_service_in_address_list`
                        )
                    "
                >
                    <div class="cursor-pointer">
                        <a
                            :href="`${url}/configuracion/service_in_address_list`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-danger m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-wrench"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Servicios en Address List</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
            </div>
        </div>
        <div
            v-if="hasPermission.data.canView(`config_view_helpdesk`)"
            class="item-administration row"
            style="border-left-color: #4e73df"
            v-show="selectedDiv === 5 || selectedDiv === 0"
        >
            <h3>Helpdesk</h3>
            <div class="row d-flex">
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-lightblue m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Tikets</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-lightblue m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Notificaciones de Tikets</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-lightblue m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Canned responses</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-lightblue m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Configuración de ticket general</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-lightblue m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Widget del ticket</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-lightblue m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Bandeja de entradas</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-lightblue m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Automatización de Tikets</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
            </div>
        </div>
        <div
            v-if="hasPermission.data.canView(`config_view_scheduling`)"
            class="item-administration row"
            style="border-left-color: #ffa726"
            v-show="selectedDiv === 6 || selectedDiv === 0"
        >
            <h3>Actividades Programadas</h3>
            <div class="row d-flex">
                <div class="col-md-2">
                    <div class="cursor-pointer">
                        <a :href="`${url}/configuracion/work-flow`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-lightorange m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Flujos de trabajo</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer">
                        <a :href="`${url}/configuracion/team`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-lightorange m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-friends"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Equipos</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer">
                        <a :href="`${url}/configuracion/template-task`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-lightorange m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-copy"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Plantillas de tareas</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer">
                        <a
                            :href="`${url}/configuracion/list-template-verification`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-lightorange m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-check-square"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Plantillas de la vista de
                                        verificaciones</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-lightorange m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Tipos de Proyectos</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-lightorange m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Categorias del Proyecto</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-lightorange m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Notificaciones</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer">
                        <a :href="`${url}/configuracion/command`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-lightorange m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-clock"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Tareas Programadas</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="hasPermission.data.canView(`config_view_potencial_customer`)"
            class="item-administration row"
            style="border-left-color: #198754"
            v-show="selectedDiv === 7 || selectedDiv === 0"
        >
            <h3>Clientes Potenciales</h3>
            <div class="row d-flex">
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-green-custom m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Preferencias</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-green-custom m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Finanzas</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-green-custom m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Widget de Registro</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-green-custom m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >IMAP</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-green-custom m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Campos de los clientes
                                        potenciales</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-green-custom m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Notificaciones</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-green-custom m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Configuración de conversion de Cliente
                                        Potencial</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-green-custom m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Pipeline de Clientes Potenciales</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
            </div>
        </div>
        <div
            v-if="hasPermission.data.canView(`config_view_inventory`)"
            class="item-administration row"
            style="border-left-color: #1976d2"
            v-show="selectedDiv === 8 || selectedDiv === 0"
        >
            <h3>Inventory</h3>
            <div class="row d-flex">
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Ubicaciones del stock</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Notificaciones</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
            </div>
        </div>
        <div
            v-if="hasPermission.data.canView(`config_view_integrations`)"
            class="item-administration row"
            style="border-left-color: #ffc107"
            v-show="selectedDiv === 9 || selectedDiv === 0"
        >
            <h3>Integraciones</h3>
            <div class="row d-flex">
                <div class="col-md-2">
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
                                        >Módulos principales</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Adicionales</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Instalar el módulo</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Lista de Módulos</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Hocks</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
            </div>
        </div>
        <div
            v-if="hasPermission.data.canView(`config_view_voice`)"
            class="item-administration row"
            style="border-left-color: #2ab57d"
            v-show="selectedDiv === 10 || selectedDiv === 0"
        >
            <h3>Voz</h3>
            <div class="row d-flex">
                <div class="col-md-2">
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
                                        >Procesamiento automático de C...</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
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
                                        >Importar Fuente de Datos</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="hasPermission.data.canView(`config_view_tools`)"
            class="item-administration row"
            style="border-left-color: #1976d2"
            v-show="selectedDiv === 11 || selectedDiv === 0"
        >
            <h3>Herramientas</h3>
            <div class="row d-flex">
                <div class="col-md-2">
                    <div class="cursor-pointer">
                        <a :href="`${url}/configuracion/tools-import`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-database"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Importar</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Exportar</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Activar Servicios</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >OpenVpn</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Caché de las facturas</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Copia de Seguridad y Restauración</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Update GPS</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Restaurar Clientes Eliminados</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Migrar Servicios</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer disabled">
                        <a :href="`${url}/`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Administrador</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="cursor-pointer">
                        <a
                            :href="`${url}/administracion/set-state-municipality-and-colony`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-primary m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-globe"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Repoblar Las Tablas de Estado municipio
                                        y colonia</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="hasPermission.data.canView(`config_view_sales`)"
            class="item-administration row"
            style="border-left-color: #34495e"
            v-show="selectedDiv === 12 || selectedDiv === 0"
        >
            <h3>Ventas</h3>
            <div class="row d-flex">
                <div class="col-md-2">
                    <div class="cursor-pointer">
                        <a
                            :href="`${url}/configuracion/credencial/modificar-credencial`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-gray-custom m-0">
                                        <i class="fas fa-upload"></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Configuración de credencial</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="cursor-pointer">
                        <a :href="`${url}/configuracion/medios-de-venta`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-gray-custom m-0">
                                        <i class="fas fa-poll"></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Modificar medios de venta</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="cursor-pointer">
                        <a :href="`${url}/configuracion/rules`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-gray-custom m-0">
                                        <i class="fas fa-money-bill"></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Comisiones</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>

                <div class="col-md-2">
                    <div class="cursor-pointer">
                        <a :href="`${url}/configuracion/tipos-vendedores`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-gray-custom m-0">
                                        <i class="fas fa-user-tie"></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Tipos de vendedores</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>

                <div class="col-md-2">
                    <div class="cursor-pointer">
                        <a :href="`${url}/configuracion/estados-vendedores`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-gray-custom m-0">
                                        <i class="fas fa-traffic-light"></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Status de los vendedores</span
                                    >
                                </div>
                            </div></a
                        ><!-- end card body -->
                    </div>
                    <!-- end card -->
                </div>

                <div class="col-md-2">
                    <div class="cursor-pointer">
                        <a :href="`${url}/configuracion/rangos-venta`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-gray-custom m-0">
                                        <i class="fas fa-cogs"></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Rangos de venta</span
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
            v-if="hasPermission.data.canView(`config_view_google_maps`)"
            class="item-administration row"
            style="border-left-color: #24c3c3"
            v-show="selectedDiv === 13 || selectedDiv === 0"
        >
            <h3>Leaflet map</h3>
            <div class="row d-flex">
                <div class="col-md-2">
                    <div class="cursor-pointer">
                        <a
                            :href="`${url}/configuracion/credenciales-google-maps`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-aqua-custom m-0">
                                        <i class="fas fa-map"></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Configurar mapa</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="hasPermission.data.canView(`config_view_finance`)"
            class="item-administration row"
            style="border-left-color: #ffc107"
            v-show="selectedDiv === 14 || selectedDiv === 0"
        >
            <h3>Mensajeria</h3>
            <div class="row d-flex">
                <div class="col-md-2">
                    <div class="cursor-pointer">
                        <a :href="`${url}/configuracion/email-setting/editar/1`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-success m-0">
                                        <i
                                            class="fas fa-envelope fa-1x circle-icon"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Configuracion de Correo</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div
                    class="col-md-2"
                    v-if="
                        hasPermission.data.canView(
                            `config_view_finance_notification`
                        )
                    "
                >
                    <div class="cursor-pointer">
                        <a :href="`${url}/configuracion/finance-notification`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-warning m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-bell"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Notificaciones Finanzas</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="cursor-pointer">
                        <a
                            :href="`${url}/configuracion/billing-reminder/editar/1`"
                            ><div class="card-body position-relative">
                                <div class="faq-count d-flex">
                                    <h5 class="text-warning m-0">
                                        <i
                                            class="fa fa-fw fa-1x circle-icon fa-user-secret"
                                        ></i>
                                    </h5>
                                    <span class="ms-1 align-self-center"
                                        >Recordatorios de Pagos</span
                                    >
                                </div>
                            </div></a
                        >
                    </div>
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
    name: "IndexSetting",
    props: {
        url: String,
    },
    components: {},
    setup(props) {
        const selectedDiv = ref(0);

        const hasPermission = reactive({
            data: new Permission({}),
        });
        onMounted(async () => {
            hasPermission.data = new Permission(await allViewHasPermission());
        });

        const toggleContainerAdministration = (index) => {
            if (index == selectedDiv.value) {
                selectedDiv.value = 0;
            } else {
                selectedDiv.value = index;
            }
        };

        return {
            toggleContainerAdministration,
            selectedDiv,
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
