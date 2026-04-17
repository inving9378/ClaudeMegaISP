<template>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title"> Red </h4>
                    <form
                        method="POST"
                        @submit.prevent="onSubmit"
                        @change="dataForm.data.errors.clear($event.target.name)"
                        @keydown="
                            dataForm.data.errors.clear($event.target.name)
                        "
                    >
                        <hr class="mb-5"/>
                        <template v-for="val in fieldsJson">
                            <ComponentFormDefault
                                v-if="val.include"
                                :id="id"
                                :json="val"
                                :errors="dataForm.data.errors"
                                :key="val.value"
                                v-model="dataForm.data[val.field]"
                                @update-field="updateThisField"
                                @clear-error="clearError"
                            />
                        </template>

                        <div class="form-group text-center">
                            <a class="btn btn-secondary me-2"
                               data-bs-toggle="modal" data-bs-target="#ip_calculadora">
                                Calculadora
                            </a>
                            <button
                                class="btn btn-primary"
                                type="submit"
                                :disabled="dataForm.data.errors.any() || disabledButton"
                            >
                                {{ submitButtonAction }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <!-- end card -->
        </div>
    </div>
    <div class="modal fade bs-example-modal-lg"
         tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true"
         id="ip_calculadora"
         data-backdrop="static"
         data-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">Calculadora de IPv4</h6>
                </div>
                <IpCalculator
                    @use-this-red="useThisRed"
                    @close-modal="closeModal"
                ></IpCalculator>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->
</template>

<script>
import {
    getfieldsJson,
    updateThisField,
    clearError,
    fieldsJson,
    dataForm,
} from "../../../hook/crudHook";
import ComponentFormDefault from "../../ComponentFormDefault";
import {onMounted, ref} from "vue";
import IpCalculator from "./IpCalculator";

export default {
    name: "AddNetworkCrud",
    props: {
        action: String,
        id: {
            type: String,
            default: null,
        },
    },
    components: {
        IpCalculator,
        ComponentFormDefault,
    },
    setup(props) {
        let submitButtonAction = "Crear";
        const disabledButton = ref(false);
        onMounted(async () => {
            await getfieldsJson("Network");
        });


        const onSubmit = () => {
            disabledButton.value = true;
            dataForm.data
                .submit(
                    "post", "/red/ipv4/add", "create"
                )
                 .then((response) => location.href = `/red/ipv4/success`)
                   .catch(() => {
                     disabledButton.value = false;
                 });
        };

        const closeModal = () => {
            $("#ip_calculadora").modal('hide');
        };

        const useThisRed = (data) => {
            updateThisField({field: 'network', value: data.network_calculator})
            updateThisField({field: 'bm', value: data.bm_calculator})

            fieldsJson.value.network.value = data.network_calculator;
            fieldsJson.value.bm.value = data.bm_calculator;
            closeModal();
        }

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            clearError,
            updateThisField,
            submitButtonAction,
            closeModal,
            useThisRed,
            disabledButton
        };
    },
};
</script>

<style scoped></style>
