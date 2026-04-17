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
        });

        const saveConfigModeInBD = (color) => {
            axios
                .post("/save-app-config-layout", {
                    color_mode: color,
                    user_id: props.user,
                })
                .then((response) => {
                    mode.value = response.data.color_mode;
                    if (mode.value == "dark") {
                        mode.value = "light";
                    } else {
                        mode.value = "dark";
                    }
                    darkMode.value = mode.value !== "dark";
                    setTimeout(() => {
                        disabledLoadingModal();
                    }, 1000);
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
