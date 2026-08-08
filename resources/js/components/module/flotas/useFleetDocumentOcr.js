import { ref } from 'vue';
import axios from 'axios';

/**
 * Lectura por IA de documentos de vehículo (item #580, Fase 7).
 *
 * Lo comparten la pestaña Documentos de la ficha y el modal de renovación del dashboard, para no
 * tener dos implementaciones del mismo flujo.
 *
 * REGLA DE ORO: el OCR NUNCA bloquea la subida. Si falla el servicio, la red o la IA, se devuelve
 * un resultado marcado para revisión manual y el usuario sigue capturando a mano como siempre.
 * Lo que la IA propone es SIEMPRE un borrador: la persona confirma al presionar Guardar.
 */
export function useFleetDocumentOcr(baseUrl) {
    const ocrRunning = ref(false);
    const ocrResult  = ref(null);   // { ok, needs_review, fields, unreadable, labels, type_labels, error }
    const ocrRunId   = ref(null);
    const ocrApplied = ref([]);     // claves que se volcaron al formulario

    function resetOcr() {
        ocrRunning.value = false;
        ocrResult.value  = null;
        ocrRunId.value   = null;
        ocrApplied.value = [];
    }

    async function runOcr(file, vehicleId) {
        resetOcr();
        if (!file) return null;

        ocrRunning.value = true;
        try {
            const fd = new FormData();
            fd.append('file', file);
            if (vehicleId) fd.append('vehicle_id', vehicleId);

            const { data } = await axios.post(`${baseUrl}/api/documentos/ocr`, fd);
            ocrResult.value = data;
            ocrRunId.value  = data.ocr_run_id ?? null;
            return data;
        } catch (e) {
            ocrResult.value = {
                ok: false,
                needs_review: true,
                fields: {},
                unreadable: [],
                error: e.response?.data?.message
                    || 'No se pudo contactar el servicio de lectura por IA. Captura los datos a mano.',
            };
            return ocrResult.value;
        } finally {
            ocrRunning.value = false;
        }
    }

    /**
     * Vuelca lo leído al formulario. Por defecto SOLO llena campos vacíos: nunca pisa algo que la
     * persona ya escribió. `overwriteKeys` es para campos que traen un valor por defecto que en
     * realidad es un placeholder (p.ej. el tipo de documento en el alta).
     */
    function applyToForm(form, { overwriteKeys = [] } = {}) {
        const fields  = ocrResult.value?.fields || {};
        const applied = [];

        Object.entries(fields).forEach(([key, entry]) => {
            if (!entry || entry.value === null || entry.value === undefined) return;
            if (!(key in form)) return;

            const actual = form[key];
            const vacio  = actual === '' || actual === null || actual === undefined;
            if (!vacio && !overwriteKeys.includes(key)) return;

            form[key] = entry.value;
            applied.push(key);
        });

        ocrApplied.value = applied;
        return applied;
    }

    /** Campos leídos con valor, en el orden que definió el backend. */
    function readFields() {
        const fields = ocrResult.value?.fields || {};
        const labels = ocrResult.value?.labels || {};
        const types  = ocrResult.value?.type_labels || {};

        return Object.entries(fields).map(([key, entry]) => ({
            key,
            label: labels[key] || key,
            value: key === 'document_type' && entry.value ? (types[entry.value] || entry.value) : entry.value,
            confidence: entry.confidence,
            applied: ocrApplied.value.includes(key),
        }));
    }

    const confBadge = (c) => ({ alta: 'bg-success', media: 'bg-warning text-dark', baja: 'bg-secondary' }[c] || 'bg-secondary');
    const confLabel = (c) => ({ alta: 'Confianza alta', media: 'Confianza media', baja: 'No se pudo leer' }[c] || c);

    return {
        ocrRunning, ocrResult, ocrRunId, ocrApplied,
        runOcr, resetOcr, applyToForm, readFields, confBadge, confLabel,
    };
}
