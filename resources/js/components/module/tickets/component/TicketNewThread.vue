<template>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form
                        method="POST"
                        @submit.prevent="onSubmit"
                        @change="dataForm.data.errors.clear($event.target.name)"
                        @keydown="
                            dataForm.data.errors.clear($event.target.name)
                        "
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
                            <a class="btn btn-secondary me-3"
                               href="javascript:void(0)"
                               @click="hideNewAnswer"
                            >
                                Cancelar
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
        </div>
    </div>
</template>

<script>
import ComponentFormDefault from "../../../ComponentFormDefault";
import {onMounted, reactive, ref, watch} from "vue";
import Form from "../../../../helpers/Form";
import {requestFieldsByModule} from "../../../../helpers/Request";

export default {
    name: "TicketNewThread",
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
    setup(props, {emit}) {
        const fieldsJson = ref({});
        const dataForm = reactive({
            data: new Form({}),
        });
        let submitButtonAction = 'Responder';
        const disabledButton = ref(false);

        onMounted(() => {
            initComponent(props.id);
        });

        watch(
            () => props.id,
            (id, idBefore) => {
                initComponent(id);
            }
        );

        const initComponent = async (id) => {
            await getfieldsJson("TicketThread");
        }

        const getfieldsJson = async (model) => {
            fieldsJson.value = await requestFieldsByModule(model);
            dataForm.data = new Form(fieldsJson.value);
        };

        const updateThisField = ({field, value}) => {
            dataForm.data[field] = value;
        };

        const clearError = ({field}) => {
            dataForm.data.errors.clear(field);
        };

        const hideNewAnswer = () =>{
            emit("hideNewAnswer")
        };

        const onSubmit = () => {
            disabledButton.value = true;
            dataForm.data
                .uploadFile(
                    `/tickets/mensaje/${props.action}`,
                    props.action
                )
                .then((response) =>
                    toastr.success(
                        "Respondido",
                        "TicketThread"
                    )
                );
            disabledButton.value = false;
            emit('reloadTicket')
        };

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            clearError,
            updateThisField,
            submitButtonAction,
            hideNewAnswer,
            disabledButton
        };
    }
}
</script>

<style scoped>

</style>
