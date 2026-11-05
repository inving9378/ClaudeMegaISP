<template>
    <div
        :class="`${
            property.class_col === 'full'
                ? 'col-12'
                : property.class_col === 'partial'
                ? 'col-6'
                : 'col'
        } row mb-2 ${errors.has(property.field) && 'has-danger'}`"
    >
        <label :for="`${property.field}_rol`" :class="`${property.class_label ?? ''}`">
            {{ property.label }}
        </label>
        <div :class="`${property.class_field ?? ''}`">
            <div class="sru-pasos">
                <select
                    :id="`${property.field}_rol`"
                    class="form-select"
                    v-model="rol"
                    @change="cambiarRol"
                >
                    <option value="">Rol…</option>
                    <option v-for="r in roles" :key="r.rol" :value="r.rol">
                        {{ r.etiqueta }}
                    </option>
                </select>
                <select
                    :id="property.field"
                    :name="property.field"
                    class="form-select"
                    v-model="persona"
                    :disabled="!rol"
                    @change="emitir"
                >
                    <option value="">{{ rol ? "Persona…" : "Elige primero el rol" }}</option>
                    <option v-for="p in personasDelRol" :key="p.id" :value="p.id">
                        {{ p.nombre }}
                    </option>
                </select>
            </div>
            <div v-if="fueraDeLista" class="small text-muted mt-1">
                Está asignado a alguien que ya no está activo. Elige a otra persona para cambiarlo.
            </div>
            <div v-if="errors.has(property.field)" class="pristine-error text-help">
                {{ errors.get(property.field) }}
            </div>
        </div>
    </div>
</template>

<script>
import { computed, onMounted, ref, watch } from "vue";
import axios from "axios";
import { isEdit } from "../hook/comunValues";

/**
 * "Asignado a" en dos pasos: primero el rol y después una de las personas activas de ese rol
 * (2026-10-22). Guarda un solo id. Si el valor actual trae varios (tareas viejas asignadas a
 * varias personas) o es de alguien inactivo, no lo cambia hasta que se elija otra persona.
 */
export default {
    name: "SelectRolUsuario",
    props: {
        errors: {
            type: Object,
            default: () => ({ has: () => false, get: () => "" }),
        },
        property: Object,
        modelValue: String | Array | Number,
        // Fuente de roles/personas; la orden de trabajo de Talento usa la de colaboradores.
        endpoint: {
            type: String,
            default: "/get-options-rol-usuario",
        },
    },
    emits: ["update:modelValue", "update-field"],
    setup(props, { emit }) {
        const roles = ref([]);
        const rol = ref("");
        const persona = ref("");
        const fueraDeLista = ref(false);

        const personasDelRol = computed(
            () => roles.value.find((r) => r.rol === rol.value)?.personas ?? []
        );

        const primerId = (valor) => {
            if (Array.isArray(valor)) valor = valor[0];
            if (typeof valor === "string") valor = valor.split(",")[0];
            const id = parseInt(valor, 10);
            return Number.isNaN(id) ? null : id;
        };

        // Coloca rol y persona según el valor que trae el formulario, sin emitir nada.
        const colocar = (valor) => {
            const id = primerId(valor);
            fueraDeLista.value = false;
            if (id === null) {
                persona.value = "";
                return;
            }
            if (id === persona.value) return;
            const delRolActual = personasDelRol.value.some((p) => p.id === id);
            const r = delRolActual
                ? rol.value
                : roles.value.find((x) => x.personas.some((p) => p.id === id))?.rol;
            if (r) {
                rol.value = r;
                persona.value = id;
            } else {
                rol.value = "";
                persona.value = "";
                fueraDeLista.value = true;
            }
        };

        const emitir = () => {
            const valor = persona.value === "" ? null : persona.value;
            fueraDeLista.value = false;
            emit("update:modelValue", valor);
            emit("update-field", { field: props.property.field, value: valor });
        };

        const cambiarRol = () => {
            persona.value = "";
            emitir();
        };

        watch(
            () => props.modelValue,
            (valor) => colocar(valor)
        );

        const valorPorDefecto = async () => {
            if (isEdit.value || !props.property.module_id) return null;
            try {
                const { data } = await axios.post("/get-default-fields-value", {
                    module_id: props.property.module_id,
                    field: props.property.field,
                });
                return data?.value ?? null;
            } catch (e) {
                return null;
            }
        };

        onMounted(async () => {
            try {
                const { data } = await axios.post(props.endpoint);
                roles.value = Array.isArray(data) ? data : [];
            } catch (e) {
                roles.value = [];
            }
            const inicial = primerId(props.modelValue) ?? primerId(await valorPorDefecto());
            colocar(inicial);
            // Un valor por defecto se pasa al formulario; uno que ya traía, no.
            if (primerId(props.modelValue) === null && persona.value !== "") emitir();
        });

        return { roles, rol, persona, personasDelRol, fueraDeLista, cambiarRol, emitir };
    },
};
</script>

<style scoped>
.sru-pasos {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.sru-pasos .form-select {
    flex: 1 1 9rem;
    min-width: 0;
}
</style>
