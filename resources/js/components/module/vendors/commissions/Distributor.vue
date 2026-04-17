<template>
    <div class="row">
        <h3 class="text-center my-3">Agregar comisión a vendedor {{ type }}</h3>
        <div class="col-md-12 mt-3">
            <div class="card h-100 mx-auto w-50">
                <div class="card-body">
                    <form>
                        <div class="form-group">
                            <div>
                                <label for="name">Selecciona al vendedor</label>
                                <select class="form-control" id="name">
                                    <option value="">
                                        Selecciona un vendedor
                                    </option>
                                    <option value="1">Samuel</option>
                                    <option value="2">Esteban</option>
                                </select>
                            </div>

                            <div class="mt-3">
                                <label for="name">Costo del paquete</label>
                                <div class="input-group">
                                    <input
                                        type="text"
                                        class="form-control"
                                        aria-label="Dollar amount (with dot and two decimal places)"
                                    />
                                    <span class="input-group-text">$</span>
                                </div>
                            </div>

                            <div class="mt-3">
                                <div>
                                    <label for="name"
                                        >Total o comisión final que se le asigna
                                        al vendedor</label
                                    >
                                    <div class="input-group">
                                        <input
                                            type="text"
                                            class="form-control"
                                            aria-label="Dollar amount (with dot and two decimal places)"
                                        />
                                        <span class="input-group-text">$</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <button class="btn btn-primary">
                                    Agregar comision
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from "vue";

const type = ref("distribuidor");
</script>
