<template>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title"> Crm</h4>
                    <form
                        method="POST"
                        @submit.prevent="onSubmit"
                        @change="dataForm.data.errors.clear($event.target.name)"
                        @keydown="
                            dataForm.data.errors.clear($event.target.name)
                        "
                        class="row"
                    >
                        <hr class="mb-5" />

                        <template v-for="val in fieldsJson">
                            <ComponentFormDefault
                                v-if="val.include"
                                :id="id"
                                :json="val"
                                :errors="dataForm.data.errors"
                                :key="val"
                                v-model="dataForm.data[val.field]"
                                @update-field="updateThisField"
                                @clear-error="clearError"
                            />
                        </template>

                        <LoadingComponent
                            :loading="loadingToogle"
                        ></LoadingComponent>

                        <!-- end card -->
                        <search
                            :val="fieldsCrmClientJson"
                            :result="found"
                            :dataForm="dataForm.data"
                            module="crm"
                        ></search>
                        <!-- end col -->
                        <div class="form-group text-center mb-2">
                            <a class="btn btn-secondary me-2" href="/crm/listar">
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

import { onMounted,ref } from "vue";
import {
    getfieldsJson,
    getfieldsEdited,
    updateThisField,
    clearError,
    fieldsJson,
    dataForm,
} from "../../../hook/crudHook";

import {
    loadingToogle,
    enableLoading,
    disabledLoading,
} from "../../../hook/loadingHook";

import ComponentFormDefault from "../../ComponentFormDefault";
import Search from "./Search";
import {getCrmClientIfExistInDb} from "./helpers/helper";
import LoadingComponent from "../../../shared/LoadingComponent";

export default {
    name: "AddCrmCrud",
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
        Search,
        LoadingComponent
    },
    setup(props) {
        let submitButtonAction = 'Crear Crm';
        let searchButtonAction = 'Buscar Crm';
        let isCreate = ref(false);
        let found = ref(false);
        const fieldsCrmClientJson = ref({});

        onMounted(async () => {

            props.id
                ? await getfieldsEdited("Crm", props.id)
                : await getfieldsJson("Crm");
        });

        const getCrmClientIfExist = async () => {
            enableLoading();
            fieldsCrmClientJson.value = await getCrmClientIfExistInDb(dataForm.data);
            fieldsCrmClientJson.value.length ? found.value = true : found.value = false;
            isCreate.value = true;
            disabledLoading();
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
                    `/crm/${props.action}`,
                    props.action
                )
                .then((response) => location.href = `/crm/success/${response.id}`);
        };

        return {
            fieldsCrmClientJson,
            fieldsJson,
            dataForm,
            onSubmit,
            clearError,
            updateThisField,
            submitButtonAction,
            searchButtonAction,
            isCreate,
            found,
            wantedCustomer,
            loadingToogle
        };
    },
};
</script>

<style scoped>

</style>
