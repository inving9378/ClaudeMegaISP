


import { ref, watch, reactive, onMounted, computed } from "vue";
import { showLoading, hideLoading } from "./loading";
export const dataToFindExport = ref();

import * as XLSX from "xlsx";
import Swal from "sweetalert2";
function cleanHtml(htmlString) {
    if (typeof htmlString === "string") {
        return htmlString.replace(/<[^>]*>/g, "").trim();
    } else if (typeof htmlString === "number") {
        return htmlString.toString().replace(/[^0-9.-]/g, "").trim();
    } else {
        return "";
    }
}

export const exportTable = async (columns, rows, tableName, visibleColumns) => {

    try {
        showLoading();
        let filteredColumns = visibleColumns.filter(column => column !== 'action');
        const wb = XLSX.utils.book_new();
        let cols = columns.filter(col => filteredColumns.includes(col.name));
        let headers = cols.map(col => col.label);
        let data = [headers];

        const dataToFind = dataToFindExport.value;

        const response = await axios.post(dataToFind.url, {
            data: dataToFind.data,
            order: dataToFind.order,
            limits: 0,
            start: dataToFind.start,
            dir: dataToFind.dir,
            color_active: dataToFind.color_active,
            is_update_color: dataToFind.is_update_color,
            persistentFilter: dataToFind.persistentFilter,
            module: dataToFind.module,
            export: true
        });

        const roWs = Object.values(response.data.data);
        roWs.forEach((row) => {
            const cleanedRow = cols.map((col) => {
                let rawValue = col.field
                    ? col.field(row)
                    : row[col.name];
                return cleanHtml(rawValue);
            });
            data.push(cleanedRow);
        });

        const ws = XLSX.utils.aoa_to_sheet(data);
        XLSX.utils.book_append_sheet(wb, ws, "Hoja1");
        const filename = `${tableName}.xlsx`;
        XLSX.writeFile(wb, filename);
        hideLoading();
    } catch (error) {
        console.error("Error al exportar la tabla:", error);
        hideLoading();
        // #526: el servidor puede pedir filtrar antes de exportar (demasiadas filas).
        const mensaje = error?.response?.data?.message;
        if (mensaje) {
            Swal.fire("No se exportó", mensaje, "warning");
        }
    }
};

// Nombres de campo que nunca salen en una exportación, aunque se pidan como columna.
const CAMPOS_SENSIBLES = /pass|contrase|token|secret|clave_api|api_key/i;

/**
 * Exportación genérica a XLSX para listados que ya tienen sus filas (sin el endpoint de módulos).
 * Solo exporta las columnas visibles, en su orden, sin la de acciones ni campos sensibles.
 *
 * @param {Array}  columns        Columnas de q-table ({ name, label, field }).
 * @param {Array}  rows           Filas a exportar (ya filtradas por el servidor).
 * @param {string} tableName      Nombre del archivo, sin extensión.
 * @param {Array}  visibleColumns Nombres de las columnas visibles.
 */
export const exportarFilas = (columns, rows, tableName, visibleColumns) => {
    const cols = columns.filter(
        (col) =>
            visibleColumns.includes(col.name) &&
            col.name !== "action" &&
            !CAMPOS_SENSIBLES.test(col.name) &&
            !(typeof col.field === "string" && CAMPOS_SENSIBLES.test(col.field))
    );
    const data = [cols.map((col) => col.label)];
    rows.forEach((row) => {
        data.push(
            cols.map((col) =>
                cleanHtml(typeof col.field === "function" ? col.field(row) : row[col.field ?? col.name])
            )
        );
    });

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(data), "Hoja1");
    XLSX.writeFile(wb, `${tableName}.xlsx`);
};
