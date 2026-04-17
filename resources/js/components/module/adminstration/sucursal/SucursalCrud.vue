<template>
    <form
        method="POST"
        @submit.prevent="onSubmit"
        @change="dataForm.data.errors.clear($event.target.name)"
        @keydown="dataForm.data.errors.clear($event.target.name)"
    >
        <div class="modal-body m-0 row">
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
        <div class="modal-footer">
            <a
                class="btn btn-secondary mr-3"
                href="javascript:void(0)"
                @click="closeModal"
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
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import {
    getfieldsJson,
    getfieldsEdited,
    updateThisField,
    clearError,
    fieldsJson,
    dataForm,
} from "../../../../hook/crudHook";
import ComponentFormDefault from "../../../ComponentFormDefault";

const props = defineProps({
    action: String,
});

const emits = defineEmits(["close-modal"]);

const id = ref(null);

onMounted(() => {
    initComponent(props.action);
});

watch(
    () => props.action,
    (action, actionBefore) => {
        initComponent(action);
    }
);

const initComponent = async (action) => {
    let partnerId = getIdByAction(action);
    if (action == "/administracion/sucursal/add") {
        id.value = null;
        await getfieldsJson("Sucursal");
    } else {
        id.value = partnerId;
        await getfieldsEdited("Sucursal", partnerId);
    }
};

const getIdByAction = (action) => {
    return _.trimStart(action, "/administracion/sucursal/update/");
};

const closeModal = () => {
    emits("close-modal", false);
};

const onSubmit = () => {
    dataForm.data
        .submit("post", `${props.action}`, props.action)
        .then((response) => {
            emits("close-modal", true);
        });
};
</script>

<style scoped></style>
