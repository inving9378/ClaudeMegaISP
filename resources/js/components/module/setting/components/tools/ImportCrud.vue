<template>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Importar</h4>
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

                        <Select-Module
                            :modules="modules"
                            @module-id="getModuleId"
                            :errors="dataForm.data.errors"
                            @clear-error="clearError"
                        >
                        </Select-Module>

                        <div class="text-center">
                            <button
                                type="button"
                                class="btn btn-primary m-2"
                                v-show="showDownloadExample"
                                @click="showFormDefalut = true"
                            >
                                Ya tengo un ejemplo
                            </button>
                            <a
                                v-show="showDownloadExample"
                                :href="tableToDownload"
                                id="download-example-xlsx"
                                class="edit-message me-1"
                                data-toggle="tooltip"
                                data-placement="top"
                                download
                                title="Descargar Documento"
                            >
                                <button type="button" class="btn btn-primary">
                                    Descargar<i class="fas fa-download"></i>
                                </button>
                            </a>
                        </div>

                        <InputFileCustom
                            v-if="showFormDefalut"
                            :property="{
                                field: 'file',
                                label: 'Archivo a importar',
                                class_col: 'full',
                            }"
                            @update-field="updateThisField"
                            :errors="dataForm.data.errors"
                            v-model="dataForm.data['file']"
                            @clear-error="clearError"
                            @click="clearErrorPass"
                        />

                        <!-- end col -->
                        <div class="form-group text-center mb-2">
                            <button
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
    <LoadingComponentModal></LoadingComponentModal>
</template>

<script>
import { onMounted, reactive, ref, watch } from "vue";
import {
    getfieldsJson,
    updateThisField,
    clearError,
    fieldsJson,
    dataForm,
} from "../../../../../hook/crudHook";
import ComponentFormDefault from "../../../../ComponentFormDefault.vue";
import { errorsPass } from "./helper";

import SelectModule from "./SelectModule.vue";
import InputFileCustom from "./InputFileCustom.vue";
import LoadingComponentModal from "../../../../../shared/LoadingComponentModal.vue";
import {
    enableLoadingModal,
    disabledLoadingModal,
} from "../../../../../hook/loadingHook";

export default {
    name: "ImportCrud",
    props: {
        action: String,
        id: {
            type: String,
            default: null,
        },
        modules: String,
        url: String,
    },
    components: {
        ComponentFormDefault,
        SelectModule,
        InputFileCustom,
        LoadingComponentModal
    },
    setup(props) {
        let submitButtonAction = "Importar";
        const showDownloadExample = ref(false);
        const tableToDownload = ref();
        const showFormDefalut = ref(false);

        onMounted(async () => {
            await getfieldsJson("SettingToolsImport");
        });

        const onSubmit = async () => {
            enableLoadingModal();
            await dataForm.data
                .uploadFile(
                    `/configuracion/tools-import/${props.action}`,
                    props.action
                )
                .then((response) => {
                    if (response.errorsPass != null) {
                        errorsPass.value = response.errorsPass;
                        toastr.error(
                            "Archivo importado con Errores",
                            "Importar"
                        );
                    } else {
                        toastr.success(
                            "Archivo importado correctamente",
                            "Importar"
                        );
                        setTimeout(() => {
                            location.reload();
                        }, 3000);
                    }
                    disabledLoadingModal();
                }).catch(() => {
                    disabledLoadingModal();
                });
        };

        const getModuleId = (obj) => {
            dataForm.data["module_id"] = obj;
            getExampleForThisModule(obj);
        };

        const getExampleForThisModule = (idModule) => {
            let data = {
                id_module: idModule,
            };
            axios
                .post(
                    `/configuracion/tools-import/get-example-for-this-module`,
                    data
                )
                .then((response) => {
                    showDownloadExample.value = true;
                    tableToDownload.value = `${response.data.file}`;
                })
                .catch((error) => {
                    console.log(error);
                });
        };

        watch(showDownloadExample, (newValue) => {
            if (newValue == true) {
                const downloadLink = document.getElementById(
                    "download-example-xlsx"
                );
                downloadLink.addEventListener("click", function () {
                    showFormDefalut.value = true;
                });
            }
        });

        const clearErrorPass = () => {
            errorsPass.value = null;
        };

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            clearError,
            updateThisField,
            submitButtonAction,
            getModuleId,
            showDownloadExample,
            tableToDownload,
            showFormDefalut,
            clearErrorPass,
        };
    },
};
</script>

<style scoped></style>
