<template>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h3>Scripts Ejecutables</h3>

                    <div class="panel">
                        <div class="seccion">
                            <div class="row d-flex justify-content-center">
                                <div class="col-md-3">
                                    <div class="cursor-pointer">
                                        <a
                                            @click="
                                                ejecutarScript(
                                                    'suspend_clients'
                                                )
                                            "
                                            ><div
                                                class="card-body position-relative"
                                            >
                                                <div class="faq-count d-flex">
                                                    <h5 class="text-danger m-0">
                                                        <i
                                                            class="fa fa-fw fa-1x circle-icon fa-user-lock"
                                                        ></i>
                                                    </h5>
                                                    <span
                                                        class="ms-1 align-self-center"
                                                        >Suspender
                                                        Clientes</span
                                                    >
                                                </div>
                                            </div></a
                                        >
                                    </div>
                                </div>
                            </div>
                            <div class="legend justify-content-center">
                                <p class="text-center">
                                    Suspende los Servicios de Todos los clientes
                                    que la fecha de facturación es hoy o ya pasó
                                    y no tienen periodo de gracia activo.
                                </p>
                            </div>
                        </div>

                        <div class="seccion">
                            <div class="row d-flex justify-content-center">
                                <div class="col-md-3">
                                    <div class="cursor-pointer">
                                        <a
                                            @click="
                                                ejecutarScript(
                                                    'billing_services'
                                                )
                                            "
                                            ><div
                                                class="card-body position-relative"
                                            >
                                                <div class="faq-count d-flex">
                                                    <h5 class="text-danger m-0">
                                                        <i
                                                            class="fa fa-fw fa-1x circle-icon fa-hand-holding-usd"
                                                        ></i>
                                                    </h5>
                                                    <span
                                                        class="ms-1 align-self-center"
                                                        >Cobrar Servicios</span
                                                    >
                                                </div>
                                            </div></a
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="legend justify-content-center">
                                <p class="text-center">
                                    Cobra los servicios que No están pagados,
                                    tienen dinero en la cuenta y el dia de
                                    facturación es hoy o ya pasó, los activa y
                                    crea una factura
                                </p>
                            </div>
                        </div>

                        <div class="seccion">
                            <div class="row d-flex justify-content-center">
                                <div class="col-md-3">
                                    <div class="cursor-pointer">
                                        <a @click="montarServiciosEnElMikrotik"
                                            ><div
                                                class="card-body position-relative"
                                            >
                                                <div class="faq-count d-flex">
                                                    <h5
                                                        class="text-primary m-0"
                                                    >
                                                        <i
                                                            class="fa fa-fw fa-2x fa-network-wired fa-align-left"
                                                        ></i>
                                                    </h5>
                                                    <span
                                                        class="ms-1 align-self-center"
                                                        >Montar Clientes Con
                                                        Servicios de Internet en
                                                        el Mikrotik</span
                                                    >
                                                </div>
                                            </div></a
                                        >
                                    </div>
                                </div>
                            </div>
                            <div class="legend justify-content-center">
                                <p class="text-center">
                                    Agrega al mikrotik los clientes que tengan
                                    servicios de Internet y no esten en el
                                    Mikrotik
                                </p>
                            </div>
                        </div>

                        <div class="seccion">
                            <div class="row d-flex justify-content-center">
                                <div class="col-md-3">
                                    <div class="cursor-pointer">
                                        <a @click="showDeleteClient"
                                            ><div
                                                class="card-body position-relative"
                                            >
                                                <div class="faq-count d-flex">
                                                    <h5
                                                        class="text-primary m-0"
                                                    >
                                                        <i
                                                            class="fa fa-fw fa-2x fa-user-slash fa-align-left"
                                                        ></i>
                                                    </h5>
                                                    <span
                                                        class="ms-1 align-self-center"
                                                        >Limpiar Datos de
                                                        Clientes</span
                                                    >
                                                </div>
                                            </div></a
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="legend justify-content-center">
                                <p class="text-center">
                                    Elimina los datos del cliente seleccionado
                                    completamente desde Base de Datos
                                </p>
                            </div>
                        </div>




                        <div class="seccion">
                            <div class="row d-flex justify-content-center">
                                <div class="col-md-3">
                                    <div class="cursor-pointer">
                                        <a @click="ejecutarScript('rectify_address_list')"
                                            ><div
                                                class="card-body position-relative"
                                            >
                                                <div class="faq-count d-flex">
                                                    <h5
                                                        class="text-primary m-0"
                                                    >
                                                        <i
                                                            class="fa fa-fw fa-2x fa-address-book fa-align-left"
                                                        ></i>
                                                    </h5>
                                                    <span
                                                        class="ms-1 align-self-center"
                                                        >Rectifica el Address List</span
                                                    >
                                                </div>
                                            </div></a
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="legend justify-content-center">
                                <p class="text-center">
                                    Toma los Clientes Bloqueados con Servicios de Internet que no Tengan registro en el address list y los pone en el address list
                                </p>
                            </div>
                        </div>


                        <div class="seccion">
                            <div class="row d-flex justify-content-center">
                                <div class="col-md-3">
                                    <div class="cursor-pointer">
                                        <a @click="ejecutarScript('billing_services_to_client_active_promise_payment')"
                                            ><div
                                                class="card-body position-relative"
                                            >
                                                <div class="faq-count d-flex">
                                                    <h5
                                                        class="text-primary m-0"
                                                    >
                                                        <i
                                                            class="fa fa-fw fa-2x fa-money-bill fa-align-left"
                                                        ></i>
                                                    </h5>
                                                    <span
                                                        class="ms-1 align-self-center"
                                                        >Cobrar Servicios A Clientes con Promesa de Pago activa</span
                                                    >
                                                </div>
                                            </div></a
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="legend justify-content-center">
                                <p class="text-center">
                                    A los clientes con promesa de Pago activa se le descuenta desde su balance el costo de todos los servicios
                                </p>
                            </div>
                        </div>



                    </div>
                </div>
            </div>
        </div>
        <!-- end col -->
    </div>

    <Remove-Client></Remove-Client>
</template>

<script>
import { onMounted, reactive, ref, watch } from "vue";
import { showLoading, hideLoading } from "../../../../helpers/loading";
import RemoveClient from "./helpers/RemoveClient.vue";

import axios from "axios";

export default {
    name: "ShowScripts",
    props: {
        url: String,
    },
    components: { RemoveClient },
    setup(props, { emit }) {
        onMounted(() => {});

        const ejecutarScript = async (url_script) => {
            if (confirm("Esta seguro que desea realizar esta acción")) {
                showLoading("showTextDef");
                let url = `${props.url}/administracion/${url_script}`;
                const responseMessage = ref("");
                const responseError = ref("");

                try {
                    const response = await axios.get(url);
                    responseMessage.value = response.data.output;
                } catch (error) {
                    responseError.value = error.response
                        ? error.response.data.error
                        : error.message;
                    console.error(responseError.value); // Maneja el error
                }
                hideLoading();
            }
        };

        const montarServiciosEnElMikrotik = async () => {
            if (confirm("Esta seguro que desea realizar esta acción")) {
                showLoading("showTextDef");
                let url = `${props.url}/administracion/add-clients-imported-to-mikrotik`;
                const responseMessage = ref("");
                const responseError = ref("");

                try {
                    const response = await axios.get(url);
                    responseMessage.value = response.data.output;
                } catch (error) {
                    responseError.value = error.response
                        ? error.response.data.error
                        : error.message;
                    console.error(responseError.value); // Maneja el error
                }
                hideLoading();
            }
        };

        const showDeleteClient = () => {
            $(`#modalDeleteClient`).modal("show");
        };

        return {
            ejecutarScript,
            montarServiciosEnElMikrotik,
            showDeleteClient,
        };
    },
};
</script>

<style scoped></style>
