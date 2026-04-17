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

            <LocationInformation
                :id="id"
                v-if="id"
            ></LocationInformation>
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

<script>
import {onMounted, ref, watch} from "vue";
import {
    getfieldsJson,
    getfieldsEdited,
    updateThisField,
    clearError,
    fieldsJson,
    dataForm,
} from "../../../../hook/crudHook";
import ComponentFormDefault from "../../../ComponentFormDefault";
import LocationInformation from "./LocationInformation";

export default {
    name: "LocationCrud",
    props: {
        action: String
    },
    components: {
        ComponentFormDefault,
        LocationInformation
    },
    setup(props, {emit}) {
        const id = ref(null);
        let submitButtonAction = props.action == '/administracion/ubicacion/add'
            ? "Crear Ubicacion"
            : "Salvar Ubicacion";

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
            if (action == '/administracion/ubicacion/add'){
                id.value = null;
                await getfieldsJson("Location");
            }else{
                id.value = partnerId;
                await getfieldsEdited("Location", partnerId);
            }
        };

        const getIdByAction = (action) => {
            return _.trimStart(action, '/administracion/ubicacion/update/')
        }

        const closeModal = () => {
            emit('close-modal');
        }

        const onSubmit = () => {
            dataForm.data
                .submit("post", `${props.action}`, props.action)
                .then((response) => {
                    emit('close-modal');
                });
        };

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            clearError,
            updateThisField,
            submitButtonAction,
            closeModal,
            id
        };
    },
};

</script>

<style scoped>

</style>
