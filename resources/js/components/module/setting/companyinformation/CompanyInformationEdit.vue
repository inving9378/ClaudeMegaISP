<template>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <form
                    method="POST"
                    @submit.prevent="onSubmit"
                    @change="dataForm.data.errors.clear($event.target.name)"
                    @keydown="dataForm.data.errors.clear($event.target.name)"
                    id="form-list-template"
                >
                    <div class="mt-3">
                        <div class="card">
                            <div class="card-header">
                                <div
                                    class="row customer-billing-sticky-sidebar spl-sticky-sidebar"
                                >
                                    <div
                                        class="col-lg-12 customer-billing-sticky-sidebar-inner"
                                    >
                                        <div class="panel panel-default">
                                            <div class="panel-body">
                                                <div class="row">
                                                    <div class="">
                                                        <div
                                                            class="position-relative float-right customer-buttons-wrapper"
                                                        >
                                                            <button
                                                                class="btn btn-primary"
                                                                type="submit"
                                                                :disabled="
                                                                    dataForm.data.errors.any()
                                                                "
                                                            >
                                                                Guardar
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row justify-content-center">
                        <div class="col-md-12">
                            <div class="row">
                                <div class="col-xl-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6>Detalles</h6>
                                        </div>
                                        <div
                                            class="p-2 m-2 h-fix-content shadow-low d-flex flex-wrap"
                                        >
                                            <template v-for="val in fieldsJson">
                                                <ComponentFormDefault
                                                    v-if="val.include"
                                                    :id="id"
                                                    :json="val"
                                                    :errors="
                                                        dataForm.data.errors
                                                    "
                                                    :key="val"
                                                    v-model="
                                                        dataForm.data[val.field]
                                                    "
                                                    @update-field="
                                                        updateThisField
                                                    "
                                                    @clear-error="clearError"
                                                />
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <input type="hidden" id="module_id" :value="1" />
</template>

<script>
import { onMounted, reactive, ref } from "vue";
import { showLoading, hideLoading } from "../../../../helpers/loading";
import {
    clearError,
    dataForm,
    fieldsJson,
    getfieldsEdited,
    updateThisField,
} from "../../../../hook/crudHook";
import ComponentFormDefault from "../../../ComponentFormDefault";

import Permission from "../../../../helpers/Permission";
import { allViewHasPermission } from "../../../../helpers/Request";

export default {
    name: "CompanyInformationEdit",
    components: { ComponentFormDefault },
    props: {},
    setup(props) {
        const hasPermission = reactive({
            data: new Permission({}),
        });

        onMounted(async () => {
            hasPermission.data = new Permission(await allViewHasPermission());
            showLoading("showTextDef");
            await getfieldsEdited("CompanyInformation", 1);
            hideLoading();
        });

        const updateThisField = ({ field, value }) => {
            dataForm.data[field] = value;
        };

        const onSubmit = () => {
            dataForm.data
                .uploadFile(`/configuracion/company-information/update/1`)
                .then((response) => {
                    location.href = "/configuracion/company-information";
                });
        };

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            clearError,
            updateThisField,
        };
    },
};
</script>

<style scoped></style>
