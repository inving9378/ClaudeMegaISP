<template>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Internet</h4>

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
                                :key="val"
                                v-model="dataForm.data[val.field]"
                                @update-field="updateThisField"
                                @clear-error="clearError"
                            />
                        </template>

                        <div class="form-group text-center">
                            <a class="btn btn-secondary me-2" href="/internet">
                                Atras
                            </a>
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
            <!-- end card -->
        </div>
        <!-- end col -->
    </div>
</template>

<script>
import {onMounted} from "vue";
import {
    getfieldsJson,
    getfieldsEdited,
    updateThisField,
    clearError,
    fieldsJson,
    dataForm,
} from "../../../hook/crudHook";
import ComponentFormDefault from "../../ComponentFormDefault";
import { changeFieldDiscountValueOrFixedDiscountValueDisableAndSetValue, ifEnablePromotionValidateIfDiscountValueOrDiscountValueFixedNotNull } from "../../../helpers/validationForm";

export default {
    name: "InternetCrud",
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
    setup(props) {
        let submitButtonAction = props.id
            ? "Salvar Plan de Internet"
            : "Crear Plan de Internet";

        onMounted(async () => {
            props.id
                ? await getfieldsEdited("Internet", props.id)
                : await getfieldsJson("Internet");
        });

        const updateThisField = ({ field, value }) => {
            if (
                changeFieldDiscountValueOrFixedDiscountValueDisableAndSetValue(
                    field,
                    value,
                    dataForm
                )
            );
            dataForm.data[field] = value;
        };

        const onSubmit = () => {
            ifEnablePromotionValidateIfDiscountValueOrDiscountValueFixedNotNull(
                dataForm
            );
            if (dataForm.data.errors.any()) {
                return;
            }
            dataForm.data
                .submit(
                    "post",
                    `/internet/${props.action}`,
                    props.action
                )
                .then((response) => location.href = '/internet/success/' + props.id ?? null);
        };

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            clearError,
            updateThisField,
            submitButtonAction,
        };
    },
};
</script>

<style scoped></style>
