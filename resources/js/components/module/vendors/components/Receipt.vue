<template>
    <div class="q-pa-md">
        <div class="contour-stippling">
            <div class="container-1">
                <h3 class="mb-3">{{ title }}</h3>
                <div class="d-flex justify-content-between contour">
                    <div>
                        <h5>Meganet SA DE C.V.</h5>
                        <p>
                            Av. Hda La Purisima Mz3 Lt 54 Casa A Fracc. Ex
                            Hacienda
                        </p>
                        <p>Santa Ines Nextlalpan</p>
                        <p>55796, Estado de México</p>
                    </div>
                    <div>
                        <h5>{{ name }}</h5>
                        <p>{{ address }}</p>
                        <p>{{ city }}</p>
                        <p>{{ zip }}, {{ state }}</p>
                    </div>
                </div>

                <table class="table mt-4">
                    <thead>
                        <tr>
                            <!-- <th scope="col">#</th> -->
                            <th
                                scope="col"
                                v-for="column in columns"
                                :key="column.key"
                            >
                                {{ column.name }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, index) in data" :key="index">
                            <!-- <th scope="row">{{ index + 1 }}</th> -->
                            <td v-for="column in columns" :key="column.value">
                                {{ item[column.value] }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-5">
                <div>
                    <h6>Total: $ {{ total }}</h6>
                    <p>Firma: ___________________________</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { defineProps } from "vue";

defineProps({
    title: {
        type: String,
        default: "Titulo del documento",
    },
    name: {
        type: String,
        default: "",
    },
    address: {
        type: String,
        default: "",
    },
    city: {
        type: String,
        default: "",
    },
    state: {
        type: String,
        default: "",
    },
    zip: {
        type: String,
        default: "",
    },
    columns: {
        type: Array,
        default: () => [],
    },
    data: {
        type: Array,
        default: () => [],
    },
    total: {
        type: Number,
        default: 0,
    },
});
</script>

<style scoped>
.container-2 {
    /* justify-content: end; */
    position: fixed;
    background-color: rgba(213, 219, 219, 0.5);
    width: 30%;
    flex: 1;
    z-index: 1050;
}

.contour {
    border: 1px solid rgb(177, 181, 181);
    padding: 10px;
    border-radius: 10px;
}

.contour-stippling {
    border: 2px dotted #5156be;
    padding: 25px;
    border-radius: 10px;
}
</style>
