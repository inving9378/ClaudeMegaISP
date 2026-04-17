import { ref, watch } from "vue";

export const tableShow = ref("one");

watch(tableShow, () => {
    if (tableShow.value == 'two') {
        $(".datatable_base").addClass("d-none");
        $(".task_grid_table").removeClass("d-none");
    } else {
        $(".datatable_base").removeClass("d-none");
        $(".task_grid_table").addClass("d-none");
    }
});


/* TODO Si se Cambia aqui Cambiarlo en el Modelo Task.php */
const TASK_COLOR = {
    priority: {
        Alta: {
            background: "red",
            color: "white",
        },
        Media: {
            background: "yellow",
            color: "black",
        },
        Baja: {
            background: "blue",
            color: "white",
        },
    },
    status: {
        ToDo: {
            background: "blue",
            color: "white",
        },
        InProgress: {
            background: "yellow",
            color: "black",
        },
        Done: {
            background: "green",
            color: "white",
        },
        Archivado: {
            background: "gray",
            color: "white",
        },
        PostponedByClient: {
            background: "red",
            color: "white",
        },
    },
};

export const updateColorField = (value, field) => {
    if (value != undefined && value != "") {
        // Obtener el valor real (puede ser un Proxy)
        const actualValue = value.value || value;

        // Verificar si existe la configuración de colores
        if (TASK_COLOR[field] && TASK_COLOR[field][actualValue]) {
            const { background, color } = TASK_COLOR[field][actualValue];

            // Buscar los selects por name
            const selects = document.getElementsByName(field);

            for (let select of selects) {
                // Encontrar el contenedor principal que tiene los estilos
                const divContainer = select.closest('.choices__inner');
                if (divContainer) {
                    // Aplicar estilos con !important
                    divContainer.style.cssText += `
                        background-color: ${background || ""} !important;
                        color: ${color || "black"} !important;
                    `;
                }
            }
        } else {
            console.warn(`No hay colores definidos para ${field} = ${actualValue}`);
        }
    }
};
