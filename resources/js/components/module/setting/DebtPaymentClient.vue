<template>
    <div
        class="modal fade"
        :id="id"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">Configurar pago atrasado de clientes recurrentes</h6>
                </div>
                <form
                    method="POST"
                    @submit.prevent="onSubmit"
                    @change="dataForm.data.errors.clear($event.target.name)"
                    @keydown="dataForm.data.errors.clear($event.target.name)"
                >
                    <div class="modal-body m-0">
                        <template v-for="val in fieldsJson">
                            <ComponentFormDefault
                                v-if="val.include"
                                :json="val"
                                :errors="dataForm.data.errors"
                                :key="val"
                                v-model="dataForm.data[val.field]"
                                @update-field="updateThisField"
                                @clear-error="clearError"
                            />
                        </template>
                    </div>
                    <div class="modal-footer">
                        <a
                            class="btn btn-secondary mr-3"
                            href="javascript:void(0)"
                            data-bs-dismiss="modal"
                        >
                            Cerrar
                        </a>

                        <button
                            class="btn btn-primary"
                            type="submit"
                            :disabled="dataForm.data.errors.any()"
                        >
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import ComponentFormDefault from "../../ComponentFormDefault";
import {onMounted} from "vue";
import {
    clearError,
    dataForm,
    fieldsJson,
    getfieldsGeneralEdited,
    updateThisField
} from "../../../hook/crudHook";

export default {
    name: "DebtPaymentClient",
    props: {
        id: String
    },
    components: {
        ComponentFormDefault
    },
    setup(props){
        onMounted(async () => {
            await getfieldsGeneralEdited("SettingDebtPaymentClientRecurrent")
        });

        const onSubmit = () => {
            dataForm.data
                .submit(
                    "post",
                    "/configuracion/debt-payment-client-recurrent",
                    "edit"
                )
                .then((response) => {
                    toastr.success('Configuracion actualizada correctamente.')
                    $(`#${props.id}`).modal('hide');
                });
        };

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            clearError,
            updateThisField
        };
    }
}
</script>

<style scoped>

</style>
