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
</template>

<script>
import { ref, onMounted } from "vue";
import { darkMode } from "../hook/appConfig";
export default {
    name: "ModeVisualBody",
    // props se conservan por compatibilidad con el tag del blade; ya no se usan
    // para persistir (el toggle es por-pestaña, no toca BD).
    props: {
        user: String,
        configlayout: String,
    },
    setup() {
        // `mode` = tema al que cambiará el PRÓXIMO clic (toggle).
        const mode = ref("dark");

        // Cambio de tema INSTANTÁNEO y SOLO en esta pestaña (sessionStorage).
        // No hace petición al servidor ni afecta el default ni otras pestañas.
        const applyTabTheme = (color) => {
            document.body.setAttribute("data-layout-mode", color);
            document.body.setAttribute("data-topbar", color);
            document.body.setAttribute("data-sidebar", color);
            try {
                sessionStorage.setItem("layout-mode", color);
            } catch (e) {}
            // Sincronizar el radio del right-sidebar si está abierto
            const radio = document.getElementById("layout-mode-" + color);
            if (radio) radio.checked = true;
            darkMode.value = color === "dark";
            mode.value = color === "dark" ? "light" : "dark";
        };

        const changeMode = (color) => {
            applyTabTheme(color);
        };

        onMounted(() => {
            // Reflejar el tema REAL de esta pestaña (ya aplicado por el script de
            // boot desde sessionStorage/BD), no el default de BD: el próximo clic
            // debe togglear desde lo que se ve.
            const current =
                document.body.getAttribute("data-layout-mode") || "light";
            mode.value = current === "dark" ? "light" : "dark";
            darkMode.value = current === "dark";
            // feather.replace() corre antes de que Vue monte este componente, así
            // que los <i data-feather="moon/sun"> no se convierten a SVG solos.
            if (typeof window.feather !== "undefined") {
                window.feather.replace();
            }
        });

        return {
            mode,
            changeMode,
        };
    },
};
</script>
