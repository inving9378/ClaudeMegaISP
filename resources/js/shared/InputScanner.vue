<template>
    <div :class="`${property.class_col === 'full' ? 'col-12' : 'col-6 partial-class-field'
        } row mb-2 ${errors.has(property.field) && 'has-danger'} `" @click="!isLocked && focusInput()">
        <label :for="property.field" :class="`${property.class_label}`">
            {{ property.label }}
        </label>
        <div :class="`${property.class_field}`">
            <div class="input-group">
                <input ref="inputRef" type="text" :name="property.field"
                    :placeholder="isLocked ? 'Modo edición (No modificable)' : (property.placeholder || 'Escanee seriales...')"
                    :class="{ 'form-control': true }" v-model="currentScan" :disabled="isLocked || property.disabled"
                    @keydown.enter.prevent="addSerial" autocomplete="off" />
                <span class="input-group-text bg-light">
                    <i class="fa fa-barcode"></i>
                </span>
            </div>

            <!-- Lista de seriales escaneados -->
            <div class="mt-2 d-flex flex-wrap gap-1" v-if="serials && serials.length > 0">
                <span v-for="(serial, index) in serials" :key="index"
                    class="badge bg-primary d-flex align-items-center p-2 mb-1 me-1"
                    style="font-size: 0.85rem; border-radius: 4px;">
                    {{ serial }}
                    <!-- Ocultar el icono de eliminar si está bloqueado -->
                    <i v-if="!isLocked" class="fa fa-times ms-2 cursor-pointer text-white-50"
                        @click.stop="removeSerial(index)" title="Eliminar"
                        style="cursor: pointer; margin-left: 8px;"></i>
                </span>
            </div>

            <div v-if="errors.has(property.field)" class="pristine-error text-help" v-html="errors.get(property.field)">
            </div>

            <small class="text-muted mt-1 d-block" v-if="serials && serials.length > 0">
                <i class="fa fa-info-circle"></i> {{ serials.length }} equipos registrados.
            </small>
        </div>
    </div>
</template>

<script>
import { onMounted, ref, watch, nextTick, computed } from "vue";
import { getAjaxDefaultValue } from "../helpers/Request";

export default {
    name: "InputScanner",
    props: {
        errors: {
            type: Object,
            default: () => ({ has: () => false, get: () => '' }),
        },
        property: Object,
        modelValue: Array | String,
    },
    setup(props, { emit }) {
        const currentScan = ref(""); // Valor temporal del input
        const serials = ref([]);     // Lista acumulada (Array)
        const inputRef = ref(null);

        const isLocked = ref(false);

        onMounted(async () => {
            // Inicializar seriales con modelValue o valor por defecto
            if (props.modelValue && props.modelValue !== "") {
                isLocked.value = true;
            }
            // Inicializar seriales con modelValue o valor por defecto
            const initialValue = props.modelValue ?? (await getValByDefaultValue());
            serials.value = Array.isArray(initialValue) ? initialValue : [initialValue];

            // Mantenemos tu arquitectura de enviar la referencia reactiva 'serials'
            emit("update-field", {
                value: serials,
                field: props.property.field,
            });

            if (!isLocked.value) focusInput();
        });

        const getValByDefaultValue = async () => {
            return typeof props.property.default_value === "object" &&
                props.property.default_value &&
                props.property.default_value.request
                ? await getAjaxDefaultValue(
                    props.property.default_value.request
                )
                : props.property.default_value ?? [];
        };

        const focusInput = () => {
            if (inputRef.value) inputRef.value.focus();
        };

        // Al presionar Enter (Pistola)
        const addSerial = () => {
            if (isLocked.value) return;
            const cleanValue = currentScan.value ? currentScan.value.trim() : "";

            if (cleanValue === "") return;

            // Evitar duplicados en la misma lista
            if (serials.value.includes(cleanValue)) {
                // Podrías usar una notificación más elegante aquí
                alert(`El serial ${cleanValue} ya está en la lista.`);
            } else {
                serials.value.push(cleanValue);
            }

            // Limpiar input para el siguiente escaneo
            currentScan.value = "";

            nextTick(() => {
                focusInput();
            });
        };

        // Eliminar un serial específico
        const removeSerial = (index) => {
            if (isLocked.value) return;
            serials.value.splice(index, 1);
            focusInput();
        };

        // Mantenemos la sincronización constante con el padre
        // Al ser serials un Array, usamos deep: true
        watch(serials, () => {
            emit("update-field", {
                value: serials,
                field: props.property.field
            });
        }, { deep: true });

        return {
            currentScan,
            serials,
            inputRef,
            addSerial,
            removeSerial,
            focusInput,
            isLocked
        };
    },
};
</script>

<style scoped>
.cursor-pointer {
    cursor: pointer;
}

.gap-1 {
    gap: 0.25rem;
}

/* Estilo para que las etiquetas no se vean pegadas */
.badge i:hover {
    color: white !important;
}
</style>