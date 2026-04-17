<template>
    <div class="p-4 ml-xl-3 border shadow-low">
        <div class="d-flex justify-content-between">
            <div><h6>CONFIGURACIÓN DE RECORDATORIOS</h6></div>
            <div>
                <h3>
                    <i class="bx bxs-save cursor-pointer" @click="onSubmit"></i>
                </h3>
            </div>
        </div>
        <hr class="mb-2" />
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
    </div>
</template>

<script>
import ComponentFormDefault from "../../../ComponentFormDefault";
import { onMounted, reactive, ref } from "vue";
import Form from "../../../../helpers/Form";
import {requestFieldsByModuleIdRelation, requestReminderPaymentAmount} from "../../../../helpers/Request";

export default {
    name: "ClientRemindersConfiguration",
    props: {
        id: String,
    },
    components: { ComponentFormDefault },
    setup(props) {
        const fieldsJson = ref({});
        const dataForm = reactive({
            data: new Form({}),
        });

        onMounted(() => {
            initComponent();
        });

        const initComponent = async () => {
            await getfieldsJsonRelation(
                "ClientRemindersConfiguration",
                "Client",
                props.id,
                "reminder_configuration"
            );
            await getReminderPaymentAmount(props.id);
        };

        const getfieldsJsonRelation = async (
            module,
            parent_module,
            id,
            relation
        ) => {
            fieldsJson.value = await requestFieldsByModuleIdRelation(
                module,
                parent_module,
                id,
                relation
            );
            dataForm.data = new Form(fieldsJson.value);
        };

        const onSubmit = () => {
            dataForm.data
                .submit(
                    "post",
                    `/cliente/billing/update-reminders-configuration/${props.id}`,
                    "update"
                )
                .then((response) =>
                    toastr.success(
                        "CONFIGURACIÓN DE RECORDATORIOS actualizado satisfactoriamente",
                        "FACTURACIÓN"
                    )
                );
        };

        const getReminderPaymentAmount = async (id) => {
            dataForm.data.reminder_payment_amount = await requestReminderPaymentAmount(id);
        }

        const updateThisField = ({ field, value }) => {
            dataForm.data[field] = value;
        };

        const clearError = ({ field }) => {
            dataForm.data.errors.clear(field);
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
