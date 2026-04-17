


import { ref, watch, reactive, onMounted, computed } from "vue";
import { showLoading, hideLoading } from "./loading";
export const dataToFindExport = ref();

import * as XLSX from "xlsx";
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
    }
};
