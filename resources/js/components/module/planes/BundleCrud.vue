<template>
    <div>
        <form
            method="POST"
            @submit.prevent="onSubmit"
            @change="dataForm.data.errors.clear($event.target.name)"
            @keydown="dataForm.data.errors.clear($event.target.name)"
        >
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">Paquetes</h4>
                            <hr class="mb-5" />
                            <div class="row">
                                <div class="col-md-12 col-lg-6">
                                    <template
                                        v-for="val in fieldsJson['bundle_left']"
                                    >
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
                                <div class="col-md-12 col-lg-6">
                                    <template
                                        v-for="val in fieldsJson[
                                            'bundle_right'
                                        ]"
                                    >
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
                            </div>
                            <hr class="mb-4" />
                            <div class="form-group text-center">
                                <a
                                    class="btn btn-secondary me-2"
                                    href="/paquetes"
                                >
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
                        </div>
                    </div>
                    <!-- end card -->
                </div>
                <!-- end col -->
            </div>
            <div class="row">
                <template
                    v-for="val in fieldsJson['bundle_bottom']"
                    class="col-sm-12 col-md-4"
                >
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
        </form>
    </div>
</template>

<script>
import { onMounted, nextTick } from "vue";
import {
    clearError,
    dataForm,
    fieldsJson,
    getfieldsEditedWithMultipleModel,
    getfieldsWithMultipleModel,
    updateThisField,
} from "../../../hook/crudHook";

import ComponentFormDefault from "../../ComponentFormDefault";
import {
    changeFieldDiscountValueOrFixedDiscountValueDisableAndSetValue,
    ifEnablePromotionValidateIfDiscountValueOrDiscountValueFixedNotNull,
} from "../../../helpers/validationForm";

export default {
    name: "BundleCrud",
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
            ? "Salvar Plan de Paquete"
            : "Crear Plan de Paquete";

        onMounted(async () => {
            props.id
                ? await getfieldsEditedWithMultipleModel(
                      [
                          { bundle_left: "BundleLeft" },
                          { bundle_right: "BundleRight" },
                          { bundle_bottom: "BundleBottom" },
                      ],
                      props.id
                  )
                : await getfieldsWithMultipleModel([
                      { bundle_left: "BundleLeft" },
                      { bundle_right: "BundleRight" },
                      { bundle_bottom: "BundleBottom" },
                  ]);
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
                dataForm.data.onFail(dataForm.data.errors);
                return;
            }

            if (dataForm.data.partners === "[]") {
                dataForm.data.partners = [];
            }
            if (dataForm.data.types_of_billing === "[]") {
                dataForm.data.types_of_billing = [];
            }

            dataForm.data
                .submit("post", `/paquetes/${props.action}`, props.action)
                .then(
                    (response) =>
                        (location.href =
                            "/paquetes/success/" + props.id ?? null)
                );
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
