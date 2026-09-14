<template>
    <div class="row vnd-wrap">
        <div class="d-flex align-items-center gap-2 mb-3 mt-3 justify-content-center">
            <i class="bi bi-building fs-4"></i>
            <h1 class="h4 fw-bold mb-0">
                Agregar comisión a vendedor {{ type }}
            </h1>
        </div>
        <div class="col-md-12 mt-3">
            <div class="card h-100 mx-auto w-50 vnd-card">
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

                            <div class="mt-3 d-flex gap-3">
                                <div class="col-md-6">
                                    <label for="name"
                                        >Cantidad de prospectos</label
                                    >
                                    <div class="input-group">
                                        <input
                                            type="text"
                                            class="form-control"
                                            aria-label="Dollar amount (with dot and two decimal places)"
                                        />
                                        <span class="input-group-text"
                                            ><i class="fas fa-users"></i
                                        ></span>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label for="name"
                                        >Número total de ventas</label
                                    >
                                    <div class="input-group">
                                        <input
                                            type="text"
                                            class="form-control"
                                            aria-label="Dollar amount (with dot and two decimal places)"
                                        />
                                        <span class="input-group-text"
                                            ><i
                                                class="fas fa-money-check-alt"
                                            ></i
                                        ></span>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <div>
                                    <label for="name">Sueldo total</label>
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

const type = ref("interno");
</script>

<style scoped>
.vnd-card {
    border-radius: 14px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}
</style>
