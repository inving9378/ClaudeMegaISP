<template>
    <div class="col-xl-12">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-center">
                    <div class="clearfix"></div>
                    <div>
                        <img
                            :src="url_photography"
                            alt=""
                            class="avatar-lg rounded-circle img-thumbnail cursor-pointer"
                            @click="changeImage"
                            accept="image/*"
                            cond="image"
                        />
                        <input
                            ref="fileInput"
                            type="file"
                            class="d-none"
                            @change="onFileChange"
                            accept="image/*"
                        />
                        <p>(para cambiar la foto de perfil por favor haga click)</p>
                    </div>
                    <h5 class="mt-3 mb-1">{{ perfilRelationJson.name }}</h5>
                    <p class="text-muted">
                        {{ perfilRelationJson.rol_name }}
                    </p>
                </div>
                <hr class="my-4" />
                <form
                    method="POST"
                    @submit.prevent="onSubmit"
                    @change="dataForm.data.errors.clear($event.target.name)"
                    @keydown="dataForm.data.errors.clear($event.target.name)"
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

                    <div class="form-group text-center">
                        <a
                            class="btn btn-secondary me-2"
                            :href="`/perfil/${id}`"
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
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import {
    clearError,
    dataForm,
    fieldsJson,
    getfieldsEdited,
    updateThisField,
} from "../../hook/crudHook";
import { onMounted, ref } from "vue";
import ComponentFormDefault from "../ComponentFormDefault";
import { requestPerfilJson } from "./helper/request";
import { validateIfPasswordIsSecure } from "../../helpers/validationForm";

export default {
    name: "PerfilEdit",
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
        let submitButtonAction = "Salvar Perfil";
        const perfilRelationJson = ref({});
        const perfilJson = ref({});
        const url_photography = ref(
            "http://localhost:8000/assets/images/users/avatar-1.jpg"
        );

        onMounted(() => {
            initComponent();
            getPerfilJson();
        });

        const initComponent = async () => {
            await getfieldsEdited("Perfil", props.id);
        };

        const getPerfilJson = async () => {
            perfilRelationJson.value = await requestPerfilJson(props.id);
            perfilJson.value = !perfilRelationJson.value.perfil
                ? null
                : perfilRelationJson.value.perfil;
            url_photography.value = perfilRelationJson.value.url_photography;
        };

        const selectedImage = ref(null);
        const fileInput = ref(null); // Referencia para el input de tipo file

        // ✅ Método para abrir el selector de archivos
        const changeImage = () => {
            fileInput.value.click(); // Abre el selector de archivos
        };

        const onFileChange = (event) => {
            const file = event.target.files[0];
            if (file) {
                // Validar que el archivo sea una imagen
                if (!file.type.startsWith("image/")) {
                    alert("Por favor, selecciona un archivo de imagen válido.");
                    return;
                }

                // Crear una vista previa de la imagen
                selectedImage.value = URL.createObjectURL(file);
                perfilRelationJson.url_photography = selectedImage.value;
                url_photography.value = selectedImage.value;
                updateThisField({ field: "photography", value: file });
            }
        };

        const onSubmit = () => {
            validateIfPasswordIsSecure(dataForm);
            if (dataForm.data.errors.any()) {
                return;
            }
            dataForm.data
                .uploadFile(`/perfil/${props.action}`, props.action)
                .then((response) => (location.href = "/perfil/" + props.id));
        };

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            clearError,
            updateThisField,
            submitButtonAction,
            perfilRelationJson,
            perfilJson,
            changeImage,
            fileInput,
            onFileChange,
            url_photography,
        };
    },
};
</script>

<style scoped></style>
