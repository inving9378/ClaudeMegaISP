<template>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title"> Cliente </h4>
                    <form
                        method="POST"
                        @submit.prevent="onSubmit"
                        @change="dataForm.data.errors.clear($event.target.name)"
                        @keydown="
                            dataForm.data.errors.clear($event.target.name)
                        "
                        class="row"
                    >
                        <hr class="mb-5"/>

                        <template v-for="val in fieldsJson">
                            <ComponentFormDefault
                                v-if="val.include"
                                :id="id"
                                :json="val"
                                :errors="dataForm.data.errors"
                                :key="val"
                                v-model="dataForm.data[val.field]"
                                @update-field="actualizarCampo"
                                @clear-error="clearError"
                            />
                        </template>

                        <!-- end card -->
                        <search
                            :val="fieldsCrmClientJson"
                            :result="found"
                            :dataForm="dataForm.data"
                            module="cliente"
                        ></search>
                        <!-- end col -->
                        <div class="form-group text-center mb-2">
                            <a class="btn btn-secondary me-2" href="/cliente/listar">
                                Atras
                            </a>
                            <button
                                class="btn btn-info mx-2"
                                type="button"
                                :disabled="dataForm.data.errors.any()"
                                @click="wantedCustomer"
                            >
                                {{ searchButtonAction }}
                            </button>

                            <button
                                v-if="isCreate"
                                class="btn btn-primary"
                                type="submit"
                                :disabled="dataForm.data.errors.any()"
                            >
                                {{ submitButtonAction }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>

import {onMounted, ref} from "vue";
import {currentSearch} from "../../../hook/comunValues";
import {
    getfieldsJson,
    updateThisField,
    clearError,
    fieldsJson,
    dataForm,
} from "../../../hook/crudHook";
import Search from "../crm/Search";
import ComponentFormDefault from "../../ComponentFormDefault";
import {getCrmClientIfExistInDb} from "../crm/helpers/helper";

export default {
    name: "AddClientCrud",
    props: {
        action: String,
        id: {
            type: String,
            default: null,
        },
    },
    components: {
        ComponentFormDefault,
        getCrmClientIfExistInDb,
        Search
    },
    setup(props) {
        let submitButtonAction = 'Crear Cliente';
        let searchButtonAction = 'Buscar Cliente';
        let isCreate = ref(false);
        let found = ref(false);
        const fieldsCrmClientJson = ref({});


        // Desde el mini-teléfono al colgar con alguien que no es cliente (MegaVoz):
        // ?telefono= llega precargado y ?despues=ticket lleva a crear su ticket al guardar.
        const params = new URLSearchParams(currentSearch());
        const telefonoInicial = (params.get('telefono') || '').replace(/\D/g, '');
        const despuesTicket = params.get('despues') === 'ticket';

        // El teléfono que llega del mini-teléfono se pone cuando ya está el formulario
        // fresco: con navegación SPA el componente se dibuja primero con la definición
        // que quedó en memoria de una visita anterior y luego getfieldsJson la reemplaza
        // (y con ella los valores). Además InputText calcula su valor inicial de forma
        // asíncrona y lo emite: si esa emisión llegara después, pisaría el teléfono. Por
        // eso se pone en esa primera emisión (o, si ya pasó, poco después) y el campo se
        // vuelve a montar (objeto nuevo como :key) para que nazca con el número.
        let telefonoPendiente = telefonoInicial;
        let formularioListo = false;
        const ponerTelefono = () => {
            if (! telefonoPendiente) return;
            const telefono = telefonoPendiente;
            telefonoPendiente = '';
            dataForm.data.phone = telefono;
            if (fieldsJson.value?.phone) {
                fieldsJson.value.phone = { ...fieldsJson.value.phone };
            }
        };
        const actualizarCampo = (payload) => {
            updateThisField(payload);
            if (formularioListo && payload?.field === 'phone') ponerTelefono();
        };

        onMounted(async () => {
            await getfieldsJson("Client");
            formularioListo = true;
            if (telefonoPendiente) setTimeout(ponerTelefono, 400);
        });

        const getCrmClientIfExist = async () => {
            fieldsCrmClientJson.value = await getCrmClientIfExistInDb(dataForm.data);
            fieldsCrmClientJson.value.length ? found.value = true : found.value = false;
            isCreate.value = true;
        }

        const cleanSearch = () => {
            fieldsCrmClientJson.value = {};
        }

        const wantedCustomer = () => {
            cleanSearch();
            getCrmClientIfExist()
        }

        const onSubmit = () => {
            dataForm.data
                .submit(
                    "post",
                    `/cliente/${props.action}`,
                    props.action
                )
                .then((response) => location.href = despuesTicket
                    ? `/tickets/crear/${response.id}`
                    : `/cliente/success/${response.id}`);
        };

        return {
            fieldsCrmClientJson,
            fieldsJson,
            dataForm,
            onSubmit,
            clearError,
            updateThisField,
            actualizarCampo,
            submitButtonAction,
            searchButtonAction,
            isCreate,
            found,
            wantedCustomer,
        };
    },
};
</script>

<style scoped>

</style>
