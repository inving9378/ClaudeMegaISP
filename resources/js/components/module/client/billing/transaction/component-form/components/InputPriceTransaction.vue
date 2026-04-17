<template>
    <div :class="`row mb-2 ${errors.has('price') && 'has-danger'}`">
        <label
            for="price"
            :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
        >
            {{ object.price.label }}
        </label>
        <div class="col-sm-12 col-md-9">
            <input
                type="text"
                name="price"
                :placeholder="object.price.placeholder"
                :class="{'form-control': true}"
                v-model="price"
                :disabled="object.price.disabled"
            />
            <div v-if="errors.has('price')" class="pristine-error text-help">
                {{ errors.get('price') }}
            </div>
        </div>
    </div>

    <div :class="`row mb-2 ${errors.has('iva') && 'has-danger'}`">
        <label
            for="iva"
            :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
        >
            {{ object.iva.label }}
        </label>
        <div class="col-sm-12 col-md-9">
            <input
                type="text"
                name="iva"
                :placeholder="object.iva.placeholder"
                :class="{'form-control': true}"
                v-model="iva"
                :disabled="object.iva.disabled"
            />
            <div v-if="errors.has('iva')" class="pristine-error text-help">
                {{ errors.get('iva') }}
            </div>
        </div>
    </div>

    <div :class="`row mb-2 ${errors.has('withiva') && 'has-danger'}`">
        <label
            for="withiva"
            :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
        >
            {{ object.withiva.label }}
        </label>
        <div class="col-sm-12 col-md-9">
            <input
                type="text"
                :placeholder="object.withiva.placeholder"
                :class="{'form-control': true}"
                v-model="withiva"
                :disabled="object.withiva.disabled"
            />
            <div v-if="errors.has('withiva')" class="pristine-error text-help">
                {{ errors.get('withiva') }}
            </div>
        </div>
    </div>

    <div :class="`row mb-2 ${errors.has('total') && 'has-danger'}`">
        <label
            for="total"
            :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
        >
            {{ object.total.label }}
        </label>
        <div class="col-sm-12 col-md-9">
            <input
                type="text"
                name="total"
                :placeholder="object.total.placeholder"
                :class="{'form-control': true}"
                v-model="total"
                :disabled="true"
            />
            <div v-if="errors.has('total')" class="pristine-error text-help">
                {{ errors.get('total') }}
            </div>
        </div>
    </div>
</template>

<script>
import {onMounted, ref, watch} from "vue";

export default {
    name: "InputPriceTransaction",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        modelValue: Object,
    },
    emits: ["update-field", "click"],
    setup(props, {emit}) {

        const object = ref(props.modelValue);
        const price = ref(props.modelValue.price.value);
        const iva = ref(props.modelValue.iva.value);
        const withiva = ref(props.modelValue.withiva.value);
        const total = ref(props.modelValue.total.value);

        watch([price, iva, withiva], ([priceActual, ivaActual, withivaActual], [priceOld, ivaOld, withivaOld]) => {
            let tempPrice = parseFloat(priceActual) || 0;
            let tempIva = parseFloat(ivaActual) || 0;
            let tempWithiva = parseFloat(withivaActual) || 0;

            if (priceActual !== priceOld){
                total.value = tempIva ? ((tempPrice * tempIva)/100 + tempPrice) : tempPrice
                withiva.value = total.value
            }

            if (ivaActual !== ivaOld){
                total.value = tempIva ? ((tempPrice * tempIva)/100 + tempPrice) : tempPrice
                withiva.value = total.value
            }

            if (withivaActual !== withivaOld){
                price.value = tempWithiva ? (tempIva ? (tempWithiva/(tempIva/100 + 1)) : tempWithiva) : tempWithiva
            }

            emit("update-field", {value: price, field: 'price'});
            emit("update-field", {value: iva, field: 'iva'});
            emit("update-field", {value: total, field: 'total'});
        })

        return {object, price, iva, withiva, total};
    },
};
</script>

<style scoped></style>
