<template>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Crear Vendedor</h4>
                    <form
                        method="POST"
                        @submit.prevent="onSubmit"
                        @change="dataForm.data.errors.clear($event.target.name)"
                        @keydown="
                            dataForm.data.errors.clear($event.target.name)
                        "
                        class="row"
                    >
                        <hr class="mb-5" />
                        <template v-for="val in fieldsJson">
                            <ComponentFormDefault
                                v-if="val.include"
                                :id="null"
                                :json="val"
                                :errors="dataForm.data.errors"
                                :key="val"
                                v-model="dataForm.data[val.field]"
                                @update-field="updateThisField"
                                @clear-error="clearError"
                            />
                        </template>

                        <div class="form-group text-center mb-2">
                            <a
                                class="btn btn-secondary me-2"
                                :href="backLocation"
                            >
                                Atras
                            </a>
                            <button
                                type="submit"
                                class="btn btn-primary"
                                :disabled="
                                    loadingToogle || dataForm.data.errors.any()
                                "
                            >
                                <span
                                    v-if="loadingToogle"
                                    class="spinner-border spinner-border-sm me-1"
                                ></span>
                                Crear Vendedor
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import {
    fieldsJson,
    dataForm,
    getfieldsJson,
    updateThisField,
    clearError,
} from "../../../../hook/crudHook.js";
import {
    loadingToogle,
    enableLoading,
    disabledLoading,
} from "../../../../hook/loadingHook";
import ComponentFormDefault from "../../../ComponentFormDefault.vue";
import { onMounted, computed } from "vue";
import Swal from "sweetalert2";

export default {
    name: "SupplierVendorCrear",
    components: { ComponentFormDefault },
    props: {
        action: String,
        id: String,
        supplier_id: Number,
    },
    setup(props) {
        const supplier_id = props.supplier_id;
        const backLocation = `/inventory/supplier/show/${supplier_id}`;
        onMounted(async () => {
            disabledLoading();
            await getfieldsJson("SupplierVendor");
            if (!dataForm.data.status) {
                dataForm.data.status = "active";
            }
        });

        function validateFormData() {
            dataForm.data.errors.clear();
            const d = dataForm.data;
            let valid = true;
            if (!d.name || String(d.name).trim() === "") {
                dataForm.data.errors.set(
                    "name",
                    "El nombre del vendedor es obligatorio."
                );
                valid = false;
            }
            if (d.email && String(d.email).trim() !== "") {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(String(d.email).trim())) {
                    dataForm.data.errors.set(
                        "email",
                        "El correo electrónico no es válido."
                    );
                    valid = false;
                }
            }

            if (!d.status) {
                if (!d.status) {
                    dataForm.data.errors.set(
                        "status",
                        "El estatus es obligatorio."
                    );
                    valid = false;
                }
            }

            return valid;
        }

        const buildErrorMessage = (error) => {
            if (error?.errors && typeof error.errors === "object") {
                const first = Object.values(error.errors)[0];
                if (Array.isArray(first) && first.length) return first[0];
            }
            if (typeof error?.message === "string" && error.message.length) return error.message;
            return "Ocurrió un error inesperado.";
        };

        async function onSubmit() {
            if (!validateFormData()) {
                Swal.fire("Error de validación", "Revisa los campos marcados.", "error");
                return;
            }
            enableLoading();
            try {
                await dataForm.data.submit(
                    "post",
                    `/inventory/supplier/${supplier_id}/vendors/add`,
                    props.action
                );
                await Swal.fire(
                    "Exito",
                    "Vendedor creado exitosamente",
                    "success"
                );
                window.location.href = `/inventory/supplier/show/${supplier_id}`;
            } catch (error) {
                Swal.fire("Error", buildErrorMessage(error), "error");
            } finally {
                disabledLoading();
            }
        }

        return {
            dataForm,
            updateThisField,
            clearError,
            onSubmit,
            loadingToogle,
            fieldsJson,
            supplier_id,
            backLocation,
        };
    },
};
</script>
