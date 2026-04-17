<template>
    <div class="col-sm-12">
        <div class="form-group">
            <h5 class="text-capitalize">Detalles del Cliente</h5>
            <div class="form-group mt-1">
                <div class="mb-2">
                    <img
                        class="avatar-xs mr-3 rounded-circle"
                        src="http://localhost:8000/assets/images/users/avatar-6.jpg"
                        alt=""
                    />
                    <span class="font-size-14"><a :href="`/cliente/editar/${userDataJson.id}`" target="_blank">{{ userDataJson.name }}</a></span>
                </div>
                <div>
                    <label for="email">Correo:</label>
                    <span class="ms-1" id="email">
                        {{ userDataJson.email }}</span
                    >
                </div>
                <div class="mb-2">
                    <label for="phone">Telefono:</label>
                    <span class="ms-1" id="phone">
                        {{ userDataJson.phone }}</span
                    >
                </div>
                <div>
                    <label for="fechadecorte">Fecha de Corte</label>
                    <span class="ms-1" id="fechadecorte">
                        {{ activeServiceExpiration }}</span
                    >
                </div>
            </div>
        </div>
        <div class="form-group">
            <h5 class="text-capitalize">Propiedades</h5>

            <form
                method="POST"
                @submit.prevent="onSubmit"
                @change="dataForm.data.errors.clear($event.target.name)"
                @keydown="dataForm.data.errors.clear($event.target.name)"
            >
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

                <div class="form-group text-center">
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
</template>

<script>
import ComponentFormDefault from "../../../ComponentFormDefault";
import { onMounted, ref } from "vue";
import {
    clearError,
    dataForm,
    fieldsJson,
    getfieldsEdited,
    updateThisField,
} from "../../../../hook/crudHook";
import { requestUserDataByTicket } from "../helper/request";
import { getActiveServiceExpiration } from "../../client/billing/helpers/request";

export default {
    name: "TicketDetails",
    props: {
        action: String,
        id: {
            type: String,
            default: null,
        },
    },
    components: {
        ComponentFormDefault,
    },
    setup(props, { emit }) {
        let submitButtonAction = "Salvar Ticket";
        const userDataJson = ref({});
        const activeServiceExpiration = ref(0);

        onMounted(async () => {
            await getfieldsEdited("TicketDetails", props.id);
            await getUserDataJson();
            activeServiceExpiration.value = await getActiveServiceExpiration(
                userDataJson.value.id
            );
        });

        const getUserDataJson = async () => {
            userDataJson.value = await requestUserDataByTicket(props.id);
        };

        const onSubmit = () => {
            dataForm.data
                .submit("post", `/tickets/${props.action}`, props.action)
                .then((response) => {
                    toastr.success("Ticket actualizado");
                });
            emit("reloadTicket");
        };

        return {
            fieldsJson,
            dataForm,
            userDataJson,
            onSubmit,
            clearError,
            updateThisField,
            submitButtonAction,
            activeServiceExpiration,
        };
    },
};
</script>

<style scoped></style>
