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

        <div class="col-sm-12 col-lg-8">
            <input
                type="file"
                class="custom-file-input"
                :id="`custom_${property.field}`"
                :name="property.field"
                accept="image/*"
                @click="confirmBeforeOpen"
                @change="uploadFile"
            />
            <label
                class="custom-file-label"
                :for="`custom_${property.field}`"
                >{{ fileName }}</label
            >
        </div>
        <div v-if="errors.has(property.field)" class="pristine-error text-help">
            {{ errors.get(property.field) }}
        </div>

        <!-- Imagen seleccionada y botón de quitar -->
        <div
            v-if="imagePreview"
            class="col-12 mt-3 text-center d-flex flex-column"
        >
            <img
                :src="imagePreview"
                alt="Imagen seleccionada"
                class="mb-2 align-self-center"
                style="max-width: 250px; max-height: 250px"
            />
            <button
                @click="removeImage"
                class="btn btn-danger btn-sm align-self-center"
                style="max-width: 250px"
            >
                Quitar imagen
            </button>
        </div>
    </div>
</template>

<script>
import { ref, watch } from "vue";

export default {
    name: "InputFile",
    props: {
        errors: {
            type: Object,
            default: () => ({}),
        },
        property: Object,
        modelValue: [String, File],
    },
    setup(props, { emit }) {
        const val = ref(props.modelValue);
        const fileName = ref("");
        const imagePreview = ref("");

        try {
            let file = JSON.parse(props.modelValue);
            fileName.value = file.name;
        } catch (e) {
            if (typeof props.modelValue == "object") {
                let file = props.modelValue;
                if (file) fileName.value = file.name;
            } else {
                fileName.value = props.modelValue;
            }
        }

        const uploadFile = (e) => {
            if (e.target.files.length) {
                const file = e.target.files[0];
                fileName.value = file.name;
                val.value = file;

                // Crear un objeto FileReader para obtener la URL de la imagen
                const reader = new FileReader();
                reader.onload = () => {
                    imagePreview.value = reader.result; // Guardar la URL de la imagen
                };
                reader.readAsDataURL(file); // Leer el archivo como una URL
            }
        };

        const removeImage = () => {
            val.value = null; // Limpiar el valor del archivo
            fileName.value = ""; // Limpiar el nombre del archivo
            imagePreview.value = ""; // Limpiar la previsualización de la imagen
            emit("update-field", { value: null, field: props.property.field }); // Emitir el cambio
        };

        const confirmBeforeOpen = (event) => {
            const isConfirmed = confirm("¿Está seguro de cambiar el archivo?");
            if (!isConfirmed) {
                event.preventDefault(); // Evita que el cuadro de selección de archivo se abra
            }
        };
        watch(val, () => {
            if (!val.value) {
                fileName.value = "";
                imagePreview.value = ""; // Limpiar la previsualización si no hay archivo
            }
            emit("update-field", { value: val, field: props.property.field });
        });

        return {
            val,
            fileName,
            imagePreview,
            uploadFile,
            removeImage,
            confirmBeforeOpen,
        };
    },
};
</script>

<style scoped>
.img-fluid {
    max-width: 100%;
    height: auto;
    border: 1px solid #ccc;
    border-radius: 4px;
}
.btn-sm {
    font-size: 0.875rem;
    padding: 0.5rem 1rem;
}
</style>
