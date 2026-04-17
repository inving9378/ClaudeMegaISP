<template>
    <div class="container q-pa-md">
        <div class="receipt">
            <div class="header">
                MEGANET S.A. DE C.V.<br />Tel. 6563336307<br />
                Av. Hda La Purisima Mz3 Lt 54 Casa A Fracc. Ex Hacienda, Santa
                Ines Nextlalpan 55796, Estado de México
            </div>

            <div class="bold">
                <div>Datos del vendedor:</div>
            </div>
            <div class="paragraph">
                <p>Nombre: {{ name }}</p>
                <p>Dirección: {{ address }}</p>
                <p>Municipio: {{ city }}</p>
                <p>CP: {{ zip }}</p>
                <p>Estado: {{ state }}</p>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
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
                            <td v-for="column in columns" :key="column.value">
                                {{ item[column.value] }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <hr />
                <p>TOTAL: ${{ total }}</p>
                <hr />

                <p class="text-center firm">________________________</p>
                <p class="text-center bold">Firma del vendedor</p>
            </div>

            <div class="feedback">
                <div class="break">**********************************</div>
                <p class="center">
                    Este recibo es utilizado como un comprobante favor de
                    conservarlo.
                </p>
                <div class="break">**********************************</div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { defineProps } from "vue";

defineProps({
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
* {
    margin: 0;
    box-sizing: border-box;
    font-family: "VT323", monospace;
    color: #1f1f1f;
}

.container {
    background: #fff;
    padding: 20px 10px;
}

.text-center {
    text-align: center;
}

.text-end {
    text-align: end;
}

.bold {
    font-weight: bold;
}

.center {
    text-align: center;
}

.receipt {
    width: 380px;
    background: #fff;
    margin: 0 auto;
    box-shadow: 5px 5px 19px #ccc;
    padding: 10px;
}

.header {
    text-align: center;
    margin-top: 50px;
    margin-bottom: 20px;
}

.paragraph {
    margin: 10px auto;
    width: 250px;
    font-size: 14px;
    text-transform: uppercase;
}

.table-container {
    margin-top: 20px;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
    margin-bottom: 10px;
}

hr {
    margin-top: 10px;
    margin-bottom: 10px;
}

th,
td {
    padding: 8px;
    text-align: left;
}

th,
hr {
    border-bottom: 1px solid #1f1f1f;
    border-top: 1px solid #1f1f1f;
}

.foo-total {
    display: flex;
    justify-content: space-between;
}

.feedback {
    margin: 20px auto;
}

.feedback h4.web {
    font-size: 20px;
    font-weight: bold;
    text-align: center;
    margin: 10px 0;
}

.feedback .break {
    text-align: center;
    font-size: 18px;
    font-weight: bold;
    margin: 10px 0;
}

.firm {
    margin-top: 45px;
    margin-bottom: 5px;
}
</style>
