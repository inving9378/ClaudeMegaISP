<template>
    <q-item-label>
        {{ text }}
        <q-icon
            :name="!isPwd ? 'visibility_off' : 'visibility'"
            class="cursor-pointer text-primary"
            :style="{
                'margin-top': isPwd ? '-7px' : null,
            }"
            @click="isPwd = !isPwd"
        />
    </q-item-label>
</template>

<script setup>
import { computed, ref } from "vue";

defineOptions({
    name: "PasswordLabel",
});

const props = defineProps({
    password: String,
    maskChar: {
        type: String,
        default: "*",
    },
});

const isPwd = ref(true);

const text = computed(() => {
    if (isPwd.value) {
        return props.maskChar.repeat(props.password?.length ?? 0);
    }
    return props.password;
});
</script>
