import { ref } from "vue";

const errors = ref({});

const hasError = (k) => {
    return errors.value[k] ? true : false;
};

const getError = (k) => {
    return errors.value[k] ?? null;
};

const hasErrors = () => {
    return errors.value && Object.keys(errors.value).length > 0 ? true : false;
};

const setErrors = (err) => {
    errors.value = err;
};

const removeError = (k) => {
    delete errors.value[k];
};

const clearErrors = () => {
    errors.value = {};
};

export function useForm() {
    return {
        setErrors,
        hasErrors,
        hasError,
        getError,
        removeError,
        clearErrors,
    };
}
