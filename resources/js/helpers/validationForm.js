import { nextTick } from "vue";
export const changeFieldDiscountValueOrFixedDiscountValueDisableAndSetValue =
    async (field, value, dataForm) => {
        if (
            field == "discount_value" &&
            value.value != null &&
            value.value != "" &&
            value.value != 0
        ) {
            dataForm.data.discount_value_fixed = null;
            dataForm.data.errors.clear("discount_value_fixed");
        }
        if (
            field == "discount_value_fixed" &&
            value.value != null &&
            value.value != "" &&
            value.value != 0
        ) {
            dataForm.data.discount_value = null;
            dataForm.data.errors.clear("discount_value");
        }
        await nextTick();
    };

export const ifEnablePromotionValidateIfDiscountValueOrDiscountValueFixedNotNull =
    (dataForm) => {
        if (dataForm.data.promotion_enable == true ) {
            if (dataForm.data.discount_value == "") {
                dataForm.data.discount_value = null;
            }

            if (dataForm.data.discount_value_fixed == "") {
                dataForm.data.discount_value_fixed = null;
            }

            if (
                dataForm.data.discount_period == null ||
                dataForm.data.discount_period == "null"
            ) {
                dataForm.data.errors.set(
                    "discount_period",
                    "El Periodo de descuento es obligatorio"
                );
            }
            if (
                dataForm.data.discount_value == null &&
                dataForm.data.discount_value_fixed == null
            ) {
                dataForm.data.errors.set(
                    "discount_value",
                    "El porciento de descuento es obligatorio"
                );
                dataForm.data.errors.set(
                    "discount_value_fixed",
                    "El valor de descuento es obligatorio"
                );
            }
        }
    };


export const validateIfPasswordIsSecure = (dataForm) => {
    if (dataForm.data.password == null || dataForm.data.password === '') {
        dataForm.data.errors.set(
            'password',
            "El campo de contraseña es obligatorio"
        );
        return;
    }

    const password = dataForm.data.password;

    // Expresión regular para permitir letras, números y caracteres especiales
    const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;

    if (!passwordRegex.test(password)) {
        dataForm.data.errors.set(
            'password',
            "La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula y un número"
        );
    }
};
