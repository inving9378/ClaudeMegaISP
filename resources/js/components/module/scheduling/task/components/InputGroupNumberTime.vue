<template>
    <div :class="`col-12 row mb-2`">
        <div class="input-group">
            <label :class="`col-sm-12 col-form-label pe-2`" for="hours">
                {{ property.label }}
            </label>
            <input
                min="0"
                type="number"
                :id="'hours'"
                name="hours"
                placeholder="1"
                :class="'form-control col-sm-12 col-md-9 ms-1'"
                v-model="hours"
                @blur="validateHours"
            />
            <div class="input-group-text">hrs</div>

            <input
                min="0"
                max="59"
                type="number"
                :id="'minutes'"
                name="minutes"
                placeholder="00"
                :class="'form-control col-sm-12 col-md-9 ms-1'"
                v-model="minutes"
                @blur="validateMinutes"
            />
            <div class="input-group-text">min</div>
        </div>

        <!-- Mostrar mensajes de error -->
        <div v-if="errors.hours" class="text-danger">{{ errors.hours }}</div>
        <div v-if="errors.minutes" class="text-danger">
            {{ errors.minutes }}
        </div>

        <span class="col-sm-12 col-md-3"></span>
    </div>
</template>

<script>
import { onMounted, ref, watch } from "vue";

export default {
    name: "InputGroupNumber",
    props: {
        property: Object,
        modelValue: String, // El valor esperado en formato hh:mm
    },
    setup(props, { emit }) {
        const val = ref("0:0");

        // Variables para horas y minutos
        const hours = ref(props.property.hours ?? 1);
        const minutes = ref(props.property.minutes ?? 0);

        // Errores de validación
        const errors = ref({
            hours: null,
            minutes: null,
        });

        // Inicializar horas y minutos desde el valor inicial (modelValue)
        const initializeTime = () => {
            if (val.value) {
                const [h, m] = val.value.split(":");
                hours.value = parseInt(h, 10);
                minutes.value = parseInt(m, 10);
            }
            updateValue();
        };

        // Validar las horas (deben ser enteros no negativos)
        const validateHours = () => {
            if (!Number.isInteger(Number(hours.value)) || hours.value < 0) {
                errors.value.hours =
                    "Las horas deben ser un número entero positivo";
                hours.value = 0;
            } else {
                errors.value.hours = null;
            }
            updateValue();
        };

        // Validar los minutos (deben estar en el rango de 0-59)
        const validateMinutes = () => {
            if (minutes.value < 0) {
                errors.value.minutes = "Los minutos deben estar entre 0 y 59";
                minutes.value = 0;
            } else if (minutes.value > 59) {
                errors.value.minutes = "Los minutos deben estar entre 0 y 59";
                minutes.value = 59;
            } else {
                errors.value.minutes = null;
            }
            updateValue();
        };

        // Formatear los valores de horas y minutos a hh:mm
        const formatTime = () => {
            const formattedHours = String(hours.value).padStart(2, "0");
            const formattedMinutes = String(minutes.value).padStart(2, "0");
            val.value = `${formattedHours}:${formattedMinutes}`;
        };

        // Emitir el valor formateado
        const updateValue = () => {
            formatTime();
            emit("update-field", {
                value: val,
                field: props.property.field,
            });
        };

        // Inicializa horas y minutos al montar el componente
        onMounted(() => {
            if (props.modelValue) {
                val.value = props.modelValue;
            } else {
                val.value = `${hours.value}:${minutes.value}`;
            }
            initializeTime();
        });

        return {
            hours,
            minutes,
            val,
            errors,
            validateHours,
            validateMinutes,
        };
    },
};
</script>

<style scoped></style>
