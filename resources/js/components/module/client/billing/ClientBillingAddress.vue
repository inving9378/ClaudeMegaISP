<template>
    <div class="p-4 ml-xl-3 border shadow-low">
        <div class="d-flex justify-content-between">
            <div><h6>DIRECCIÓN DE FACTURACIÓN</h6></div>
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

        <br />
        <div class="text-right">
            <span><small>Dirección de defecto que usa el cliente</small></span>
        </div>
    </div>
</template>

<script>
import ComponentFormDefault from "../../../ComponentFormDefault";
import { onMounted, reactive, ref } from "vue";
import Form from "../../../../helpers/Form";
import { requestFieldsByModuleIdRelation } from "../../../../helpers/Request";

export default {
    name: "ClientBillingAddress",
    props: {
        id: String,
        typeOfBilling: String,
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
                "ClientBillingAddress",
                "Client",
                props.id,
                "billing_address"
            );
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
                    `/cliente/billing/update-billing-address/${props.id}`,
                    "update"
                )
                .then((response) =>
                    toastr.success(
                        "DIRECCIÓN DE FACTURACIÓN actualizado satisfactoriamente",
                        "FACTURACIÓN"
                    )
                );
        };

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
