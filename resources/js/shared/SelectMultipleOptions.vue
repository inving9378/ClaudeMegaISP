<template>
    <div>
        <label for="name">{{ label }}</label>
        <div class="dropdown" style="width: 100%">
            <button
                class="form-select dropdown-toggle"
                type="button"
                id="dropdownMenuButton"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                style="width: 100%"
                :disabled="disabled"
            >
                Seleccionar
            </button>
            <div
                class="dropdown-menu px-3 custom-scrollbar"
                aria-labelledby="dropdownMenuButton"
                style="width: 100%; height: 200px; overflow-y: auto"
            >
                <div
                    v-for="option in options"
                    :key="option[select_value]"
                    class="dropdown-item form-check d-flex justify-content-between"
                    @click.stop="toggleOption(option[select_value])"
                >
                    <span
                        class="form-check-label"
                        :for="`option-${option[select_value]}`"
                    >
                        {{ option[text_value] }}
                    </span>
                    <input
                        class="form-check-input"
                        type="checkbox"
                        :value="option[select_value]"
                        :checked="
                            selectedOptions.includes(option[select_value])
                        "
                        :disabled="disabled"
                        :id="`option-${option[select_value]}`"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { defineEmits } from "vue";

const props = defineProps({
    label: String,
    options: Array,
    selectedOptions: [Number,Array],
    text_value: [String, Number],
    select_value: [String, Number],
    disabled: Boolean,
});

const emit = defineEmits(["update:selectedOptions"]);

const toggleOption = (value) => {
    if (props.disabled) return;

    const checked = !props.selectedOptions.includes(value);
    updateSelectedOptions(value, checked);
};

const updateSelectedOptions = (value, checked) => {
    let newSelectedOptions = [...props.selectedOptions];

    if (checked) {
        newSelectedOptions.push(value);
    } else {
        newSelectedOptions = newSelectedOptions.filter(
            (option) => option !== value
        );
    }
    emit("update:selectedOptions", newSelectedOptions);
};
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 3px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #888;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>
