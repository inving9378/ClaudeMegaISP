<template>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Crear Proveedor</h4>
                    <form
                        method="POST"
                        @submit.prevent="onSubmit"
                        @change="dataForm.data.errors.clear($event.target.name)"
                        @keydown="dataForm.data.errors.clear($event.target.name)"
                        class="row"
                    >
                        <hr class="mb-5" />
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

                        <div class="form-group text-center mb-2">
                            <a class="btn btn-secondary me-2" href="/inventory/supplier">
                                Atras
                            </a>
                            <button
                                type="submit"
                                class="btn btn-primary"
                                :disabled="loadingToogle || dataForm.data.errors.any()"
                            >
                                <span v-if="loadingToogle" class="spinner-border spinner-border-sm me-1"></span>
                                Crear Proveedor
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { onMounted, computed } from "vue";
import Swal from "sweetalert2";
import ComponentFormDefault from "../../../ComponentFormDefault.vue";
import {
    fieldsJson,
    dataForm,
    getfieldsJson,
    updateThisField,
    clearError,
} from "../../../../hook/crudHook.js";
import { loadingToogle, enableLoading, disabledLoading } from "../../../../hook/loadingHook";

export default {
    name: "SupplierCrear",
    components: { ComponentFormDefault},
    props:{
        action: String,
        id: String,
    },
    setup(props) {

        onMounted(async () => {
            disabledLoading();
            await getfieldsJson("Supplier");
            if (!dataForm.data.status) {
                dataForm.data.status = "active";
            }
        });

        const validateClientSide = () => {
            dataForm.data.errors.clear();
            const d = dataForm.data;
            let valid = true;

            if (!d.name || String(d.name).trim() === "") {
                dataForm.data.errors.set("name", "El nombre del proveedor es obligatorio.");
                valid = false;
            }

            if (!d.rfc || String(d.rfc).trim() === "") {
                dataForm.data.errors.set("rfc", "El RFC es obligatorio.");
                valid = false;
            }

            const hasPhone = d.phone && String(d.phone).trim() !== "";
            const hasEmail = d.email && String(d.email).trim() !== "";
            if (!hasPhone && !hasEmail) {
                const msg = "Debe proporcionar al menos un teléfono o un correo electrónico.";
                dataForm.data.errors.set("phone", msg);
                dataForm.data.errors.set("email", msg);
                valid = false;
            } else if (hasEmail) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(String(d.email).trim())) {
                    dataForm.data.errors.set("email", "El correo electrónico no es válido.");
                    valid = false;
                }
            }

            if (!d.status) {
                dataForm.data.errors.set("status", "El estatus es obligatorio.");
                valid = false;
            }

            return valid;
        };

        const buildErrorMessage = (error) => {
            if (error?.errors && typeof error.errors === "object") {
                const first = Object.values(error.errors)[0];
                if (Array.isArray(first) && first.length) return first[0];
            }
            if (typeof error?.message === "string" && error.message.length) return error.message;
            return "Ocurrió un error inesperado.";
        };

        const onSubmit = async () => {
            if (!validateClientSide()) {
                Swal.fire("Error de validación", "Revisa los campos marcados.", "error");
                return;
            }
            enableLoading();
            try {
                await dataForm.data.submit("post", "/inventory/supplier/add", props.action);
                await Swal.fire("Éxito", "Proveedor creado exitosamente.", "success");
                window.location.href = "/inventory/supplier";
            } catch (error) {
                Swal.fire("Error", buildErrorMessage(error), "error");
            } finally {
                disabledLoading();
            }
        };

        return { dataForm, updateThisField, clearError, onSubmit, loadingToogle, fieldsJson, id: props.id };
    },
};
</script>
