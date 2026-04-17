<template>
    <div class="col-md-12">
        <button
            v-for="(key, index) in variables"
            :key="key"
            type="button"
            class="btn btn-primary m-1"
            @click="copiedIndex(index)"
        >
            {{ key }}
        </button>
    </div>
    <div v-if="errorTextKeys" class="col-sm-9 pristine-error text-help">
        <p>
            Hubo un error al Previsualizar el Documento las siguientes variables
            tienen error:
        </p>
        <ul>
            <li v-for="key in errorTextKeys">{{ key }}</li>
        </ul>
    </div>
    <textarea v-model="val" id="" cols="30" rows="10" v-if="showTextArea"></textarea>
</template>

<script>
import { onMounted, reactive, ref, watch } from "vue";
import { errorTextKeys } from "../components/module/client/info/comun_variable";

export default {
    name: "ContractTemplate",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
    },
    components: {},
    emits: ["update-show-load-button", "update-field"],
    setup(props, { emit }) {
        const val = ref("");
        const variables = ref([]);
        const showTextArea = ref(false);
        onMounted(() => {
            $(document).on("click", `#load-content`, function (e) {
                $.ajax({
                    url: `/cliente/document/load_content_template`,
                    method: "GET",
                    data: {
                        template: props.property.template,
                        idClient: props.property.idClient,
                    },
                    success: function (response) {
                        val.value = response.html;
                        variables.value = response.variables;
                        showTextArea.value = true;
                        emit("update-show-load-button");
                    },
                    error: function () {
                        alert("Hubo un error al cargar el contenido.");
                    },
                });
            });

            $(document).on("click", `#show-preview`, async function (e) {
                const response = await axios.post(
                    `/cliente/document/show_content_template`,
                    {
                        template: props.property.template,
                        idClient: props.property.idClient,
                        html: val.value,
                    }
                );
                if (response.data.status == "fail") {
                    errorTextKeys.value = response.data.keys;
                }
                if (response.data.status == "ok") {
                    showPreview(response.data.file_path);
                }
            });
        });

        watch(val, () => {
            emit("update-field", {
                value: val,
                field: props.property.field,
            });
        });

        const showPreview = (file_path) => {
            window.open(file_path, "_blank");
        };

        const copiedIndex = (index) => {
            let textToCopy = "${" + index + "}";
            navigator.clipboard.writeText(textToCopy);
        };

        return {
            val,
            errorTextKeys,
            variables,
            copiedIndex,
            showTextArea,
        };
    },
};
</script>

<style scoped></style>
