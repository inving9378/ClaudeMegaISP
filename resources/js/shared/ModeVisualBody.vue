<template>
    <button
        type="button"
        class="btn header-item"
        id="mode-setting-btn"
        @click="changeMode(mode)"
    >
        <i data-feather="moon" class="icon-lg layout-mode-dark"></i>
        <i data-feather="sun" class="icon-lg layout-mode-light"></i>
    </button>
    <LoadingComponentModal></LoadingComponentModal>
</template>

<script>
import { ref, onMounted, watch } from "vue";
import LoadingComponentModal from "./LoadingComponentModal.vue";
import { enableLoadingModal, disabledLoadingModal } from "../hook/loadingHook";
import { darkMode } from "../hook/appConfig";
export default {
    name: "ModeVisualBody",
    components: {
        LoadingComponentModal,
    },

    props: {
        user: String,
        configlayout: String,
    },
    setup(props) {
        const configlayout = JSON.parse(props.configlayout);
        const mode = ref();

        const changeMode = async (color) => {
            enableLoadingModal();
            await saveConfigModeInBD(color);
        };

        onMounted(() => {
            if (configlayout == null) {
                mode.value = "dark";
            } else if (configlayout.color_mode == "dark") {
                mode.value = "light";
            } else {
                mode.value = "dark";
            }
            // feather.replace() corre antes de que Vue monte este componente,
            // así que los <i data-feather="moon/sun"> que renderizamos nunca
            // se convierten a SVG. Llamarlo de nuevo aquí lo arregla.
            if (typeof window.feather !== "undefined") {
                window.feather.replace();
            }
        });

        const saveConfigModeInBD = (color) => {
            axios
                .post("/save-app-config-layout", {
                    color_mode: color,
                    user_id: props.user,
                })
                .then((response) => {
                    const savedMode = response.data.color_mode; // "dark" o "light" recién guardado
                    // Aplicar tema visualmente sin reload
                    document.body.setAttribute('data-layout-mode', savedMode);
                    document.body.setAttribute('data-topbar', savedMode);
                    document.body.setAttribute('data-sidebar', savedMode);
                    // Sincronizar radio del right-sidebar si está abierto
                    const radio = document.getElementById('layout-mode-' + savedMode);
                    if (radio) radio.checked = true;

                    // Preparar el siguiente clic (toggle)
                    mode.value = savedMode === "dark" ? "light" : "dark";
                    darkMode.value = savedMode === "dark";
                    setTimeout(() => {
                        disabledLoadingModal();
                    }, 400);
                })
                .catch((error) => {
                    console.log(error);
                    setTimeout(() => {
                        disabledLoadingModal();
                    }, 1000);
                });

            //set timeout
        };

        return {
            mode,
            changeMode,
        };
    },
};
</script>
