<template>
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

<script>
import {onMounted, ref, watch} from "vue";
import {
    getfieldsJson,
    getfieldsEdited,
    updateThisField,
    clearError,
    fieldsJson,
    dataForm,
} from "../../../hook/crudHook";
import ComponentFormDefault from "../../ComponentFormDefault";

export default {
name: "NetworkCrud",
    props: {
        action: String
    },
    components: {
        ComponentFormDefault
    },
    setup(props, {emit}) {
        const id = ref(null);
        let submitButtonAction = "Salvar Red";

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
            let netId = getIdByAction(action);
                id.value = netId;
                await getfieldsEdited("NetworkEdit", netId);

        };
        const getIdByAction = (action) => {
            return _.trimStart(action, '/red/ipv4/update/')
        }

        const closeModal = () => {
            emit('close-modal', 'crudred');
        }

        const onSubmit = () => {
            dataForm.data
                .submit("post", `${props.action}`, props.action)
                .then((response) => {
                    toastr.success(`Rol ${id.value ? 'actualizado' : 'creado'} correctamente`);
                    emit('close-modal', 'crudred');
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
