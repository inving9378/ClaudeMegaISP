<template>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Ticket</h4>
                    <form
                        method="POST"
                        @submit.prevent="onSubmit"
                        @change="dataForm.data.errors.clear($event.target.name)"
                        @keydown="
                            dataForm.data.errors.clear($event.target.name)
                        "
                    >
                        <hr class="mb-5" />

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

                        <div class="form-group text-center">
                            <a
                                class="btn btn-secondary me-2"
                                href="/tickets/abiertos"
                            >
                                Atras
                            </a>
                            <button
                                class="btn btn-primary"
                                type="submit"
                                :disabled="
                                    dataForm.data.errors.any() || disabledButton
                                "
                            >
                                {{ submitButtonAction }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <!-- end card -->
        </div>
        <!-- end col -->
    </div>
</template>

<script>
import { onMounted, ref } from "vue";
import {
    getfieldsJson,
    clearError,
    fieldsJson,
    dataForm,
} from "../../../hook/crudHook";
import ComponentFormDefault from "../../ComponentFormDefault";
import { getDataClient } from "./helper/request";

export default {
    name: "TicketCrud",
    props: {
        action: String,
        client: String,
    },
    components: {
        ComponentFormDefault,
    },
    setup(props) {
        let submitButtonAction = "Crear Ticket";
        const disabledButton = ref(false);

        onMounted(async () => {
            await getfieldsJson("Ticket");
            if (props.client) {
                let client = await getDataClient(props.client);
                asignValuesFromClient(client);
            }
        });

        const onSubmit = () => {
            disabledButton.value = true;
            dataForm.data
                .uploadFile(`/tickets/${props.action}`, props.action)
                .then(
                    (response) =>
                        (location.href = `/tickets/success/${response.id}`)
                )
                .catch(() => {
                    disabledButton.value = false;
                });
        };

        const updateThisField = async ({ field, value }) => {
            if (field == "customer_lead" && value.value != "null") {
                let client = await getDataClient(value.value);
                asignValuesFromClient(client);
            }

            if (field == "customer_lead" && value.value == "null") {
                dataForm.data["customer_lead"] = null;
                dataForm.data["phone"] = null;
                dataForm.data["phone2"] = null;
                dataForm.data["address"] = null;
            }
            dataForm.data[field] = value;
        };

        //TODO arreglar no sale el nombre
        const asignValuesFromClient = (client) => {
            dataForm.data["phone"] = client.client_main_information.phone;
            dataForm.data["phone2"] = client.client_main_information.phone2;
            dataForm.data[
                "colony_id"
            ] = `${client.client_main_information.colony_id}`;
            dataForm.data[
                "customer_lead"
            ] = `${client.client_main_information.client_id}`;
            updateThisField({
                field: "colony_id",
                value: `${client.client_main_information.colony_id}`,
            });
        };

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            clearError,
            updateThisField,
            submitButtonAction,
            disabledButton,
        };
    },
};
</script>

<style scoped></style>
