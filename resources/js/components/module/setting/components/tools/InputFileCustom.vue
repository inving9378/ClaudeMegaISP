<template>
    <div
        :class="`${
            property.class_col === 'full'
                ? 'col-12'
                : 'col-6 partial-class-field'
        } row m-auto mb-2 ${errors.has(property.field) && 'has-danger'}`"
    >
        <label
            :for="`custom_${property.field}`"
            :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
        >
            {{ property.label }}
        </label>

        <div class="col-sm-12 col-lg-8" @click="clearError">
            <input
                type="file"
                class="custom-file-input"
                :id="`custom_${property.field}`"
                :name="property.field"
                @change="uploadFile"
            />
            <label
                class="custom-file-label"
                :for="`custom_${property.field}`"
                >{{ fileName }}</label
            >
        </div>
        <div
            v-if="errors.has(property.field)"
            class="pristine-error text-help"
            style="max-height: 10em; overflow-y: auto"
        >
            <div v-for="(error, index) in getErrors()" :key="error.fila">
                <template
                    v-if="
                        index === 0 ||
                        error.fila !== getErrors()[index - 1].fila
                    "
                >
                    <h5>Error en la fila {{ error.fila }} el documento</h5>
                </template>
                <span>{{ error.mensaje }}</span>
            </div>
        </div>

        <div
            v-if="showErrorsIportPassed"
            class="pristine-error text-help"
            style="max-height: 10em; overflow-y: auto"
        >
            <div v-for="(error, index) in errorsPass" :key="error.fila">
                <template
                    v-if="
                        index === 0 || error.fila !== errorsPass[index - 1].fila
                    "
                >
                    <h5>Error en la fila {{ error.fila }} el documento</h5>
                </template>
                <span
                    >Esta Fila no ha sido importada porque existe un usaurio
                    identico en BD</span
                >
            </div>
        </div>
    </div>
</template>

<script>
import { ref, watch } from "vue";
import { errorsPass } from "./helper";

export default {
    name: "InputFileCustom",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
    },
    emits: ["file", "clear-error", "update-field"],
    setup(props, { emit }) {
        const val = ref();
        const fileName = ref("");

        const uploadFile = (e) => {
            if (e.target.files.length) {
                fileName.value = e.target.files[0].name;
                val.value = e.target.files[0];
            }
        };

        watch(val, () => {
            if (!val.value) fileName.value = "";
            emit("update-field", { value: val, field: props.property.field });
        });

        const getErrors = () => {
            const errors = props.errors.errors.file;
            return errors;
        };

        const showErrorsIportPassed = ref(false);
        watch(errorsPass, () => {
            if (errorsPass.value) {
                showErrorsIportPassed.value = true;
            } else {
                showErrorsIportPassed.value = false;
            }
        });

        const clearError = () => {
            emit("clear-error", "file");
        };
        return {
            val,
            fileName,
            uploadFile,
            getErrors,
            clearError,
            showErrorsIportPassed,
            errorsPass,
        };
    },
};
</script>

<style scoped></style>
