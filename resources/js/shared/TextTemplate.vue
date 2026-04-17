<template>
    <div>
        <button
            type="button"
            class="btn btn-primary"
            style="position: relative; float: right"
            @click="toogleFullScreen"
        >
            FullScreen
        </button>
    </div>
    <div class="col-md-12">
        <h2>Variables</h2>

        <button
            type="button"
            class="btn btn-primary m-1"
            @click="selectPanelPrincipal(`${module}`)"
        >
            {{ module }}
        </button>
        <q-tab-panels v-model="panelPrincipal">
            <q-tab-panel :name="module">
                <div class="d-flex">
                    <div v-for="(key, index) in variables">
                        <button
                            type="button"
                            class="btn btn-primary m-1"
                            @click="selectPanel(`${index}`)"
                        >
                            {{ index }}
                        </button>
                    </div>
                </div>
                <q-tab-panels
                    v-model="panel"
                    animated
                    class="shadow-2 rounded-borders"
                    v-for="(key, index) in variables"
                >
                    <q-tab-panel :name="index">
                        <div v-if="index != 'Hidden'">
                            <div class="text-h6">{{ index }}</div>
                            <button
                                v-for="(key2, index2) in key"
                                :key="key2"
                                type="button"
                                class="btn btn-primary m-1"
                                @click="copiedIndex(index2)"
                            >
                                {{ key2 }}
                            </button>
                        </div>
                    </q-tab-panel>
                </q-tab-panels>
            </q-tab-panel>
        </q-tab-panels>
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
    <textarea
        v-model="val"
        id=""
        ref="textarea"
        cols="30"
        rows="10"
        @click="updateCursorPosition"
        @keyup="updateCursorPosition"
    ></textarea>
</template>

<script>
import { onMounted, reactive, ref, watch, nextTick } from "vue";
import {
    errorTextKeys,
    cleanHtml,
} from "../components/module/client/info/comun_variable";

export default {
    name: "TextTemplate",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        module: String,
    },
    components: {},
    emits: ["update-show-load-button", "update-field"],
    setup(props, { emit }) {
        const val = ref("");
        const variables = ref([]);
        const showTextArea = ref(false);
        const cursorPosition = ref(0);
        const textarea = ref(null);
        const panel = ref("");
        const panelPrincipal = ref("");
        const fullScreen = ref(false);
        onMounted(() => {
            getVariables();
            $(document).on("click", `#load-content`, function (e) {
                $.ajax({
                    url: `/administracion/document_template/load_content_template`,
                    method: "GET",
                    data: {
                        template: props.property.template,
                    },
                    success: function (response) {
                        errorTextKeys.value = null;
                        val.value = response.html;
                        emit("update-show-load-button");
                    },
                    error: function () {
                        alert("Hubo un error al cargar el contenido.");
                    },
                });
            });

            $(document).on("click", `#show-preview`, async function (e) {
                let data = {
                    template: props.property.template,
                    html: val.value,
                };
                if (props.property.idClient) {
                    data.idClient = props.property.idClient;
                }

                if (props.module) {
                    data.module = props.module;
                }

                const response = await axios.post(
                    `/administracion/document_template/show_content_template`,
                    data,
                    { responseType: "blob" } // Especifica que esperas un blob como respuesta
                );

                if (response.headers["content-type"] === "application/json") {
                    const responseData = JSON.parse(await response.data.text());
                    if (responseData.status == "fail") {
                        errorTextKeys.value = responseData.keys;
                    }
                } else {
                    const blob = new Blob([response.data], {
                        type: "application/pdf",
                    });
                    const url = URL.createObjectURL(blob);
                    window.open(url, "_blank");
                }
            });
        });

        const updateCursorPosition = () => {
            cursorPosition.value = textarea.value.selectionStart;
        };

        const getVariables = async () => {
            await axios
                .get(`/administracion/document_template/get_variables`, {
                    params: {
                        module: props.module,
                    },
                })
                .then((response) => {
                    variables.value = response.data.variable;
                })
                .catch((error) => {
                    console.error("Error fetching data:", error);
                });
        };

        watch(val, () => {
            emit("update-show-load-button");
            emit("update-field", {
                value: val,
                field: props.property.field,
            });
        });

        watch(cleanHtml, () => {
            if (cleanHtml.value == true) {
                val.value = "";
                cleanHtml.value = false;
                errorTextKeys.value = null;
            }
        });

        const showPreview = (file_path) => {
            window.open(file_path, "_blank");
        };

        const copiedIndex = (index) => {
            let textToCopy = "${" + index + "}";

            const beforeCursor = val.value.slice(0, cursorPosition.value);
            const afterCursor = val.value.slice(cursorPosition.value);
            val.value = beforeCursor + textToCopy + afterCursor;
            // Actualiza la posición del cursor después de insertar la variable
            nextTick(() => {
                textarea.value.setSelectionRange(
                    cursorPosition.value + textToCopy.length,
                    cursorPosition.value + textToCopy.length
                );
                textarea.value.focus();
            });
        };

        const selectPanel = (selectedPanel) => {
            if (selectedPanel == panel.value) {
                panel.value = "";
            } else {
                panel.value = selectedPanel;
            }
        };

        const selectPanelPrincipal = (selectedPanel) => {
            if (selectedPanel == panelPrincipal.value) {
                panelPrincipal.value = "";
            } else {
                panelPrincipal.value = selectedPanel;
            }
        };

        const toogleFullScreen = () => {
            fullScreen.value = !fullScreen.value;
            if (fullScreen.value) {
                $("#modalDocumentTemplates_toChange").removeClass("modal-xl");
                $("#modalDocumentTemplates_toChange").addClass(
                    "modal-fullscreen"
                );
                $("#crud_template_manager_container").removeClass("container");
            } else {
                $("#modalDocumentTemplates_toChange").addClass("modal-xl");
                $("#modalDocumentTemplates_toChange").removeClass(
                    "modal-fullscreen"
                );
                $("#crud_template_manager_container").addClass("container");
            }
        };

        return {
            val,
            errorTextKeys,
            variables,
            copiedIndex,
            showTextArea,
            updateCursorPosition,
            textarea,
            panel,
            selectPanel,
            selectPanelPrincipal,
            panelPrincipal,
            toogleFullScreen,
            fullScreen,
        };
    },
};
</script>

<style scoped></style>
