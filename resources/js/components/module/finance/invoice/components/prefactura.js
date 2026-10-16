import Swal from "sweetalert2";
import { showLoading, hideLoading } from "../../../../../helpers/loading";

/**
 * Ícono único de documento (item #505; antes «Prefactura actualizada» #480 + «Imprimir») de las
 * tablas de facturas (ficha del cliente y Finanzas › Facturas). Primero valida con ?validar=1 y solo
 * entonces abre el PDF en la pestaña, así una plantilla incompleta se reporta con sus variables
 * faltantes en vez de abrir una pestaña en blanco o con JSON.
 *
 * La pestaña se abre ANTES de esperar la respuesta: si se abriera después del await, el
 * navegador la bloquearía como ventana emergente.
 */
const escapar = (texto) =>
    String(texto ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);

// /print/{id} sirve el documento vigente (Invoice::documentoVigente()): prefactura o factura.
export const abrirDocumentoFactura = (id) =>
    abrirPdfValidado(`/finanzas/invoices/print/${id}`, "No se pudo generar el documento");

export const abrirPdfValidado = async (url, tituloError) => {
    const pestana = window.open("about:blank", "_blank");
    showLoading("showTextDef");

    try {
        await axios.get(url, { params: { validar: 1 } });
        if (pestana) {
            pestana.location.href = url;
        } else {
            window.location.href = url;
        }
    } catch (error) {
        if (pestana) {
            pestana.close();
        }
        const data = error.response?.data || {};
        const keys = Array.isArray(data.keys) ? data.keys : [];
        const lista = keys.length
            ? `<ul style="text-align:left;margin-top:8px">${keys
                  .map((k) => `<li><code>${escapar(k)}</code></li>`)
                  .join("")}</ul>`
            : "";
        Swal.fire({
            icon: "error",
            title: tituloError,
            html: `${escapar(data.message || "Ocurrió un error inesperado.")}${lista}`,
        });
    } finally {
        hideLoading();
    }
};
