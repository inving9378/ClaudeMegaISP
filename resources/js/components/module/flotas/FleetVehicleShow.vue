<template>
    <div class="flt-show-wrap">

        <!-- ── Loading / Error ─────────────────────────────────────────── -->
        <div v-if="loading" class="flt-center text-muted py-5">
            <div class="spinner-border text-primary mb-2"></div>
            <div>Cargando vehículo…</div>
        </div>

        <div v-else-if="loadError" class="alert alert-danger d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            No se pudo cargar el vehículo. <a :href="baseUrl" class="ms-1">Volver a Flotas</a>
        </div>

        <template v-else-if="vehicle">

            <!-- ── HEADER ──────────────────────────────────────────────── -->
            <div class="flt-header">
                <div class="flt-header-icon" :class="`flt-status-${vehicle.status}`">
                    <i :class="['bi', vehicleIcon]"></i>
                </div>
                <div class="flt-header-main">
                    <h4 class="flt-header-title">
                        {{ vehicle.brand }} {{ vehicle.model }}
                        <span class="text-muted fw-normal" v-if="vehicle.year">{{ vehicle.year }}</span>
                    </h4>
                    <div class="flt-badges">
                        <span class="badge" :class="`flt-badge-${vehicle.status}`">{{ statusLabel(vehicle.status) }}</span>
                        <span class="badge" :class="vehicle.has_gps ? 'flt-badge-gps' : 'flt-badge-nogps'">
                            <i class="bi bi-broadcast me-1"></i>{{ vehicle.has_gps ? 'Con GPS' : 'Sin GPS' }}
                        </span>
                    </div>
                    <div class="flt-header-quick">
                        <span><i class="bi bi-credit-card-2-front me-1"></i>{{ vehicle.plates || 'Sin placas' }}</span>
                        <span><i class="bi bi-person me-1"></i>{{ currentOperatorName || 'Sin operador' }}</span>
                        <span><i class="bi bi-speedometer2 me-1"></i>{{ fmtKm(vehicle.current_km) }} km</span>
                    </div>
                </div>
                <div class="flt-header-actions">
                    <button class="btn btn-sm btn-primary me-2" @click="startEditInfo">
                        <i class="bi bi-pencil me-1"></i>Editar
                    </button>
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" @click.stop="moreOpen = !moreOpen">
                            <i class="bi bi-three-dots"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" :class="{ show: moreOpen }" @click="moreOpen = false">
                            <li><a class="dropdown-item" href="#" @click.prevent="goTab('fotos')"><i class="bi bi-images me-2"></i>Fotos</a></li>
                            <li><a class="dropdown-item" href="#" @click.prevent="goTab('historial')"><i class="bi bi-clock-history me-2"></i>Historial</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="#" @click.prevent="confirmDelete"><i class="bi bi-trash me-2"></i>Eliminar vehículo</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- ── 4 TARJETAS DE SALUD ─────────────────────────────────── -->
            <div class="row g-3 flt-health">
                <div class="col-6 col-lg-3">
                    <div class="flt-health-card">
                        <div class="flt-health-icon bg-soft-blue"><i class="bi bi-wrench-adjustable"></i></div>
                        <div>
                            <div class="flt-health-label">Próximo servicio</div>
                            <div class="flt-health-value">{{ nextServiceText }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="flt-health-card">
                        <div class="flt-health-icon" :class="docsAlertCount > 0 ? 'bg-soft-amber' : 'bg-soft-green'">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <div>
                            <div class="flt-health-label">Documentos por vencer</div>
                            <div class="flt-health-value" :class="docsAlertColorClass">{{ docsAlertCount }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="flt-health-card">
                        <div class="flt-health-icon bg-soft-blue"><i class="bi bi-tools"></i></div>
                        <div>
                            <div class="flt-health-label">Mantenimientos del año</div>
                            <div class="flt-health-value">{{ maintYearCount }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="flt-health-card">
                        <div class="flt-health-icon bg-soft-green"><i class="bi bi-cash-stack"></i></div>
                        <div>
                            <div class="flt-health-label">Gasto del año</div>
                            <div class="flt-health-value">{{ fmtMoney(spendYear) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── PESTAÑAS ────────────────────────────────────────────── -->
            <ul class="nav nav-tabs flt-tabs mt-4">
                <li class="nav-item" v-for="t in tabs" :key="t.key">
                    <a class="nav-link" :class="{ active: activeTab === t.key }" href="#" @click.prevent="goTab(t.key)">
                        <i :class="['bi', t.icon, 'me-1']"></i>{{ t.label }}
                        <span v-if="t.badge" class="badge flt-tab-badge ms-1">{{ t.badge }}</span>
                    </a>
                </li>
            </ul>

            <div class="flt-tab-body">

                <!-- a) INFORMACIÓN ─────────────────────────────────────── -->
                <section v-show="activeTab === 'informacion'">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="flt-section-title mb-0">Datos generales</h6>
                        <button v-if="!editInfo" class="btn btn-sm btn-outline-primary" @click="startEditInfo">
                            <i class="bi bi-pencil me-1"></i>Editar
                        </button>
                        <div v-else>
                            <button class="btn btn-sm btn-outline-secondary me-2" @click="cancelEditInfo">Cancelar</button>
                            <button class="btn btn-sm btn-primary" :disabled="savingInfo" @click="saveInfo">
                                <i class="bi bi-check2 me-1"></i>{{ savingInfo ? 'Guardando…' : 'Guardar' }}
                            </button>
                        </div>
                    </div>

                    <!-- Vista -->
                    <div v-if="!editInfo" class="row g-3 flt-info-grid">
                        <div class="col-md-4" v-for="f in infoFields" :key="f.key">
                            <div class="flt-info-label">{{ f.label }}</div>
                            <div class="flt-info-value">{{ f.format ? f.format(vehicle[f.key]) : (vehicle[f.key] || '—') }}</div>
                        </div>
                    </div>

                    <!-- Edición inline -->
                    <div v-else class="row g-3">
                        <div class="col-md-3"><label class="form-label fw-semibold">Placas</label><input class="form-control" v-model="editForm.plates" /></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">Marca</label><input class="form-control" v-model="editForm.brand" /></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">Modelo</label><input class="form-control" v-model="editForm.model" /></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">Año</label><input type="number" class="form-control" v-model="editForm.year" /></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">Color</label><input class="form-control" v-model="editForm.color" /></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">Tipo</label>
                            <select class="form-select" v-model="editForm.vehicle_type">
                                <option value="car">Automóvil</option><option value="pickup">Pickup / Camioneta</option>
                                <option value="truck">Camión</option><option value="motorcycle">Motocicleta</option><option value="other">Otro</option>
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label fw-semibold">Combustible</label>
                            <select class="form-select" v-model="editForm.fuel_type">
                                <option value="gasoline">Gasolina</option><option value="diesel">Diésel</option>
                                <option value="electric">Eléctrico</option><option value="hybrid">Híbrido</option>
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label fw-semibold">Estado</label>
                            <select class="form-select" v-model="editForm.status">
                                <option value="active">Activo</option><option value="in_workshop">En taller</option><option value="inactive">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label fw-semibold">Cap. tanque (L)</label><input type="number" step="0.5" class="form-control" v-model="editForm.tank_capacity_liters" /></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">Kilometraje</label><input type="number" class="form-control" v-model="editForm.current_km" /></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">VIN</label><input class="form-control" v-model="editForm.vin" /></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">No. motor</label><input class="form-control" v-model="editForm.motor_number" /></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Ubicación habitual</label><input class="form-control" v-model="editForm.habitual_location" /></div>
                        <div class="col-12"><label class="form-label fw-semibold">Notas</label><textarea class="form-control" rows="2" v-model="editForm.notes"></textarea></div>
                    </div>

                    <!-- Sección GPS (solo si has_gps) -->
                    <div v-if="vehicle.has_gps" class="flt-gps-box mt-4">
                        <h6 class="flt-section-title"><i class="bi bi-broadcast text-info me-1"></i>Dispositivo GPS</h6>
                        <div class="row g-3 flt-info-grid">
                            <div class="col-md-3"><div class="flt-info-label">Marca</div><div class="flt-info-value">{{ vehicle.gps_brand || '—' }}</div></div>
                            <div class="col-md-3"><div class="flt-info-label">Modelo</div><div class="flt-info-value">{{ vehicle.gps_model || '—' }}</div></div>
                            <div class="col-md-3"><div class="flt-info-label">IMEI</div><div class="flt-info-value">{{ vehicle.gps_imei || '—' }}</div></div>
                            <div class="col-md-3"><div class="flt-info-label">SIM</div><div class="flt-info-value">{{ vehicle.gps_sim || '—' }}</div></div>
                            <div class="col-md-3"><div class="flt-info-label">Operadora</div><div class="flt-info-value">{{ vehicle.gps_carrier || '—' }}</div></div>
                            <div class="col-md-3"><div class="flt-info-label">Última señal</div><div class="flt-info-value text-muted"><i class="bi bi-hourglass-split me-1"></i>Sin tracking aún</div></div>
                        </div>
                        <p class="text-muted small mb-0 mt-2"><i class="bi bi-info-circle me-1"></i>El tracking en vivo estará disponible en Fase 2.</p>
                    </div>
                </section>

                <!-- b) ASIGNACIÓN ──────────────────────────────────────── -->
                <section v-show="activeTab === 'asignacion'">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="flt-section-title mb-0">Operador actual</h6>
                        <button class="btn btn-sm btn-outline-primary" @click="showAssignForm = !showAssignForm">
                            <i class="bi bi-arrow-left-right me-1"></i>Cambiar asignación
                        </button>
                    </div>

                    <div class="flt-current-assign mb-3" v-if="currentAssignment">
                        <div class="flt-avatar"><i class="bi bi-person-fill"></i></div>
                        <div>
                            <div class="fw-semibold">{{ currentOperatorName }}</div>
                            <div class="text-muted small">
                                {{ currentAssignment.department || 'Sin departamento' }} ·
                                desde {{ fmtDate(currentAssignment.since) }}
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-muted mb-3"><i class="bi bi-person-dash me-1"></i>Sin operador asignado.</div>

                    <!-- Formulario cambiar asignación -->
                    <div v-if="showAssignForm" class="flt-inline-form mb-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Operador <span class="text-danger">*</span></label>
                                <select class="form-select" v-model="assignForm.user_id">
                                    <option :value="null">Selecciona…</option>
                                    <option v-for="u in operators" :key="u.id" :value="u.id">{{ u.name }}</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Departamento</label>
                                <input class="form-control" v-model="assignForm.department" />
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-semibold">Desde <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" v-model="assignForm.since" />
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-semibold">Hasta</label>
                                <input type="date" class="form-control" v-model="assignForm.until" />
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Notas</label>
                                <input class="form-control" v-model="assignForm.notes" />
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button class="btn btn-sm btn-outline-secondary me-2" @click="showAssignForm = false">Cancelar</button>
                            <button class="btn btn-sm btn-primary" :disabled="savingAssign" @click="saveAssignment">
                                <i class="bi bi-check2 me-1"></i>{{ savingAssign ? 'Guardando…' : 'Asignar' }}
                            </button>
                        </div>
                    </div>

                    <h6 class="flt-section-title">Historial de asignaciones</h6>
                    <div v-if="!assignments.length" class="text-muted small">Sin historial.</div>
                    <div class="flt-timeline">
                        <div class="flt-tl-item" v-for="a in assignments" :key="a.id">
                            <div class="flt-tl-dot" :class="a.is_active ? 'bg-success' : 'bg-secondary'"></div>
                            <div class="flt-tl-content">
                                <div class="fw-semibold">{{ a.operator_name || ('Usuario #' + a.user_id) }}
                                    <span v-if="a.is_active" class="badge flt-badge-active ms-1">Actual</span>
                                </div>
                                <div class="text-muted small">
                                    {{ a.department || 'Sin departamento' }} ·
                                    {{ fmtDate(a.since) }} → {{ a.until ? fmtDate(a.until) : 'presente' }}
                                </div>
                                <div v-if="a.notes" class="small mt-1">{{ a.notes }}</div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- c) MANTENIMIENTOS ──────────────────────────────────── -->
                <section v-show="activeTab === 'mantenimientos'">
                    <div v-if="nextServiceBanner" class="flt-banner flt-banner-blue mb-3">
                        <i class="bi bi-wrench-adjustable-circle me-2"></i>
                        <span>{{ nextServiceBanner }}</span>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6 col-lg-3"><div class="flt-mini-card"><div class="flt-mini-label">Total</div><div class="flt-mini-value">{{ maintenances.length }}</div></div></div>
                        <div class="col-6 col-lg-3"><div class="flt-mini-card"><div class="flt-mini-label">Este año</div><div class="flt-mini-value">{{ maintYearCount }}</div></div></div>
                        <div class="col-6 col-lg-3"><div class="flt-mini-card"><div class="flt-mini-label">Gasto del año</div><div class="flt-mini-value">{{ fmtMoney(maintSpendYear) }}</div></div></div>
                        <div class="col-6 col-lg-3"><div class="flt-mini-card"><div class="flt-mini-label">Promedio</div><div class="flt-mini-value">{{ fmtMoney(maintAvg) }}</div></div></div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                        <div class="flex-grow-1" style="min-width:180px">
                            <input class="form-control form-control-sm" placeholder="Buscar…" v-model="maintSearch" />
                        </div>
                        <select class="form-select form-select-sm w-auto" v-model="maintTypeFilter">
                            <option value="">Todos los tipos</option>
                            <option value="preventive">Preventivo</option><option value="corrective">Correctivo</option>
                            <option value="emergency">Emergencia</option><option value="verification">Verificación</option>
                        </select>
                        <select class="form-select form-select-sm w-auto" v-model="maintPeriodFilter">
                            <option value="">Todo el tiempo</option><option value="year">Este año</option><option value="month">Este mes</option>
                        </select>
                        <button class="btn btn-sm btn-primary ms-auto" @click="showMaintForm = !showMaintForm">
                            <i class="bi bi-plus-lg me-1"></i>Nuevo mantenimiento
                        </button>
                    </div>

                    <!-- Formulario INLINE nuevo mantenimiento -->
                    <div v-if="showMaintForm" class="flt-inline-form mb-4">
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label fw-semibold">Tipo <span class="text-danger">*</span></label>
                                <select class="form-select" v-model="maintForm.type">
                                    <option value="preventive">Preventivo</option><option value="corrective">Correctivo</option>
                                    <option value="emergency">Emergencia</option><option value="verification">Verificación</option>
                                </select>
                            </div>
                            <div class="col-md-3"><label class="form-label fw-semibold">Fecha <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" v-model="maintForm.service_date" />
                            </div>
                            <div class="col-md-3"><label class="form-label fw-semibold">Km al servicio</label>
                                <input type="number" class="form-control" v-model="maintForm.service_km" />
                            </div>
                            <div class="col-md-3"><label class="form-label fw-semibold">Mecánico</label>
                                <input class="form-control" v-model="maintForm.mechanic_name" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Trabajos realizados</label>
                                <select class="form-select" multiple size="6" v-model="maintForm.works">
                                    <option v-for="w in worksCatalog" :key="w" :value="w">{{ w }}</option>
                                </select>
                                <div class="form-text">Ctrl/⌘ + clic para seleccionar varios.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Proveedor / Taller</label>
                                <div class="input-group">
                                    <select class="form-select" v-model="maintForm.provider_id">
                                        <option :value="null">Sin proveedor</option>
                                        <option v-for="p in providers" :key="p.id" :value="p.id">{{ p.name }}</option>
                                    </select>
                                    <button class="btn btn-outline-secondary" type="button" @click="showProviderForm = !showProviderForm">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                </div>
                                <div v-if="showProviderForm" class="d-flex gap-2 mt-2">
                                    <input class="form-control form-control-sm" placeholder="Nombre del nuevo proveedor" v-model="newProviderName" />
                                    <button class="btn btn-sm btn-success" :disabled="creatingProvider" @click="createProvider">Crear</button>
                                </div>
                                <div class="mt-3">
                                    <label class="form-label fw-semibold">Descripción</label>
                                    <textarea class="form-control" rows="2" v-model="maintForm.description"></textarea>
                                </div>
                            </div>
                            <div class="col-md-3"><label class="form-label fw-semibold">Mano de obra</label>
                                <input type="number" step="0.01" class="form-control" v-model.number="maintForm.labor_cost" />
                            </div>
                            <div class="col-md-3"><label class="form-label fw-semibold">Refacciones</label>
                                <input type="number" step="0.01" class="form-control" v-model.number="maintForm.parts_cost" />
                            </div>
                            <div class="col-md-3"><label class="form-label fw-semibold">Otros</label>
                                <input type="number" step="0.01" class="form-control" v-model.number="maintForm.other_cost" />
                            </div>
                            <div class="col-md-3"><label class="form-label fw-semibold">Total</label>
                                <input class="form-control bg-light" :value="fmtMoney(maintTotalLive)" readonly />
                            </div>

                            <div class="col-md-3"><label class="form-label fw-semibold">Próximo servicio</label>
                                <select class="form-select" v-model="maintForm.next_mode">
                                    <option value="none">Sin definir</option><option value="km">Por km</option>
                                    <option value="date">Por fecha</option><option value="both">Ambos</option>
                                </select>
                            </div>
                            <div class="col-md-3" v-if="['km','both'].includes(maintForm.next_mode)">
                                <label class="form-label fw-semibold">Próximo km</label>
                                <input type="number" class="form-control" v-model="maintForm.next_service_km" />
                            </div>
                            <div class="col-md-3" v-if="['date','both'].includes(maintForm.next_mode)">
                                <label class="form-label fw-semibold">Próxima fecha</label>
                                <input type="date" class="form-control" v-model="maintForm.next_service_date" />
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Evidencia</label>
                                <div class="flt-drop" :class="{ 'flt-drop-over': dragMaint }"
                                     @dragover.prevent="dragMaint = true" @dragleave.prevent="dragMaint = false"
                                     @drop.prevent="onDropMaint" @click="$refs.maintFile.click()">
                                    <i class="bi bi-cloud-arrow-up fs-3 d-block mb-1"></i>
                                    Arrastra archivos aquí o haz clic para seleccionar
                                    <input ref="maintFile" type="file" multiple class="d-none" @change="onPickMaint" />
                                </div>
                                <ul class="flt-file-list mt-2" v-if="maintFiles.length">
                                    <li v-for="(f, i) in maintFiles" :key="i">
                                        <i class="bi bi-paperclip me-1"></i>{{ f.name }}
                                        <i class="bi bi-x-lg flt-file-rm" @click="maintFiles.splice(i, 1)"></i>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button class="btn btn-sm btn-outline-secondary me-2" @click="saveMaint(true)" :disabled="savingMaint">
                                <i class="bi bi-file-earmark me-1"></i>Borrador
                            </button>
                            <button class="btn btn-sm btn-outline-primary me-2" @click="saveMaint(false, true)" :disabled="savingMaint">
                                Guardar y crear otro
                            </button>
                            <button class="btn btn-sm btn-primary" @click="saveMaint(false)" :disabled="savingMaint">
                                <i class="bi bi-check2 me-1"></i>{{ savingMaint ? 'Guardando…' : 'Guardar' }}
                            </button>
                        </div>
                    </div>

                    <!-- Lista cronológica -->
                    <div v-if="!filteredMaintenances.length" class="text-muted small py-3">Sin mantenimientos registrados.</div>
                    <div class="flt-list">
                        <div class="flt-list-item" v-for="m in filteredMaintenances" :key="m.id">
                            <div class="flt-list-icon"><i :class="['bi', maintIcon(m.type).icon, maintIcon(m.type).color]"></i></div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-semibold">{{ maintTypeLabel(m.type) }}
                                        <span v-if="m.is_draft" class="badge bg-secondary ms-1">Borrador</span>
                                    </span>
                                    <span class="fw-semibold">{{ fmtMoney(m.total_cost) }}</span>
                                </div>
                                <div class="text-muted small">
                                    {{ fmtDate(m.service_date) }}
                                    <span v-if="m.service_km"> · {{ fmtKm(m.service_km) }} km</span>
                                    <span v-if="m.provider"> · {{ m.provider.name }}</span>
                                </div>
                                <div v-if="m.works && m.works.length" class="small text-muted mt-1">
                                    <i class="bi bi-list-check me-1"></i>{{ m.works.join(', ') }}
                                </div>
                                <div v-if="m.description" class="small mt-1">{{ m.description }}</div>
                                <div v-if="m.files && m.files.length" class="small text-muted mt-1">
                                    <i class="bi bi-paperclip me-1"></i>{{ m.files.length }} archivo(s)
                                </div>
                            </div>
                            <button class="btn btn-sm btn-link text-danger" @click="deleteMaint(m)"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </section>

                <!-- d) DOCUMENTOS ──────────────────────────────────────── -->
                <section v-show="activeTab === 'documentos'">
                    <div v-if="docsCriticalCount" class="flt-banner flt-banner-red mb-3">
                        <i class="bi bi-exclamation-octagon-fill me-2"></i>
                        Hay {{ docsCriticalCount }} documento(s) vencido(s) o por vencer que requieren atención.
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6 col-lg-3"><div class="flt-mini-card flt-mini-red"><div class="flt-mini-label">Vencidos</div><div class="flt-mini-value">{{ docCounts.vencido }}</div></div></div>
                        <div class="col-6 col-lg-3"><div class="flt-mini-card flt-mini-amber"><div class="flt-mini-label">Por vencer 30d</div><div class="flt-mini-value">{{ docCounts.por_vencer }}</div></div></div>
                        <div class="col-6 col-lg-3"><div class="flt-mini-card flt-mini-green"><div class="flt-mini-label">Vigentes</div><div class="flt-mini-value">{{ docCounts.vigente }}</div></div></div>
                        <div class="col-6 col-lg-3"><div class="flt-mini-card"><div class="flt-mini-label">Costo del año</div><div class="flt-mini-value">{{ fmtMoney(docCostYear) }}</div></div></div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                        <select class="form-select form-select-sm w-auto" v-model="docTypeFilter">
                            <option value="">Todos los tipos</option>
                            <option v-for="(label, key) in docTypeLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <select class="form-select form-select-sm w-auto" v-model="docStatusFilter">
                            <option value="">Todos los estados</option>
                            <option value="vencido">Vencidos</option><option value="por_vencer">Por vencer</option><option value="vigente">Vigentes</option>
                        </select>
                        <button class="btn btn-sm btn-primary ms-auto" @click="showDocForm = !showDocForm">
                            <i class="bi bi-plus-lg me-1"></i>Nuevo documento
                        </button>
                    </div>

                    <!-- Formulario nuevo documento -->
                    <div v-if="showDocForm" class="flt-inline-form mb-4">
                        <div class="alert alert-light border d-flex align-items-center small mb-3">
                            <i class="bi bi-robot me-2 text-primary"></i>
                            <span><strong>Detección automática con IA (Fase 7)</strong> — pronto podrás subir el documento y se llenarán los campos solos.</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label fw-semibold">Tipo <span class="text-danger">*</span></label>
                                <select class="form-select" v-model="docForm.document_type">
                                    <option v-for="(label, key) in docTypeLabels" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </div>
                            <div class="col-md-4"><label class="form-label fw-semibold">Folio</label><input class="form-control" v-model="docForm.folio_number" /></div>
                            <div class="col-md-4"><label class="form-label fw-semibold">Emisor</label><input class="form-control" v-model="docForm.issued_by" /></div>
                            <div class="col-md-3"><label class="form-label fw-semibold">Fecha emisión</label><input type="date" class="form-control" v-model="docForm.issue_date" /></div>
                            <div class="col-md-3"><label class="form-label fw-semibold">Fecha vencimiento</label><input type="date" class="form-control" v-model="docForm.expiration_date" /></div>
                            <div class="col-md-3"><label class="form-label fw-semibold">Costo</label><input type="number" step="0.01" class="form-control" v-model.number="docForm.cost" /></div>
                            <div class="col-md-3 d-flex align-items-end">
                                <div class="flt-drop w-100" :class="{ 'flt-drop-over': dragDoc }"
                                     @dragover.prevent="dragDoc = true" @dragleave.prevent="dragDoc = false"
                                     @drop.prevent="onDropDoc" @click="$refs.docFile.click()">
                                    <i class="bi bi-file-earmark-arrow-up me-1"></i>{{ docFile ? docFile.name : 'PDF / imagen' }}
                                    <input ref="docFile" type="file" accept=".pdf,image/*" class="d-none" @change="onPickDoc" />
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Alertas de vencimiento</label>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="form-check"><input class="form-check-input" type="checkbox" id="a30" v-model="docForm.alert_30_days"><label class="form-check-label" for="a30">30 días</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" id="a7" v-model="docForm.alert_7_days"><label class="form-check-label" for="a7">7 días</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" id="a1" v-model="docForm.alert_1_day"><label class="form-check-label" for="a1">1 día</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" id="a0" v-model="docForm.alert_same_day"><label class="form-check-label" for="a0">El mismo día</label></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Canales</label>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="form-check" v-for="ch in ['email','whatsapp','push','sms']" :key="ch">
                                        <input class="form-check-input" type="checkbox" :id="'ch'+ch" :value="ch" v-model="docForm.alert_channels">
                                        <label class="form-check-label text-capitalize" :for="'ch'+ch">{{ ch }}</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button class="btn btn-sm btn-outline-secondary me-2" @click="showDocForm = false">Cancelar</button>
                            <button class="btn btn-sm btn-primary" :disabled="savingDoc" @click="saveDoc">
                                <i class="bi bi-check2 me-1"></i>{{ savingDoc ? 'Guardando…' : 'Guardar' }}
                            </button>
                        </div>
                    </div>

                    <div v-if="!filteredDocuments.length" class="text-muted small py-3">Sin documentos registrados.</div>
                    <div class="flt-doc-list">
                        <div class="flt-doc-item" :class="`flt-doc-${d._status}`" v-for="d in filteredDocuments" :key="d.id">
                            <div class="flt-list-icon"><i :class="['bi', docIcon(d.document_type)]"></i></div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <span class="fw-semibold">{{ docTypeLabels[d.document_type] || d.document_type }}</span>
                                    <span class="badge" :class="docBadgeClass(d._status)">{{ docBadgeText(d) }}</span>
                                </div>
                                <div class="text-muted small">
                                    <span v-if="d.issued_by">{{ d.issued_by }}</span>
                                    <span v-if="d.folio_number"> · Folio {{ d.folio_number }}</span>
                                    <span v-if="d.expiration_date"> · Vence {{ fmtDate(d.expiration_date) }}</span>
                                </div>
                            </div>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-outline-secondary" disabled title="Disponible al adjuntar archivo (Fase 2)"><i class="bi bi-eye"></i></button>
                                <button class="btn btn-sm" :class="d._status === 'vigente' ? 'btn-outline-secondary' : 'btn-outline-primary'" @click="renewDoc(d)">
                                    {{ d._status === 'vigente' ? 'Agendar' : 'Renovar' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- e) COMBUSTIBLE ─────────────────────────────────────── -->
                <section v-show="activeTab === 'combustible'">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="flt-section-title mb-0">Cargas de combustible</h6>
                        <button class="btn btn-sm btn-primary" @click="showFuelForm = !showFuelForm">
                            <i class="bi bi-plus-lg me-1"></i>Nueva carga
                        </button>
                    </div>

                    <div v-if="showFuelForm" class="flt-inline-form mb-4">
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label fw-semibold">Fecha <span class="text-danger">*</span></label><input type="date" class="form-control" v-model="fuelForm.refuel_date" /></div>
                            <div class="col-md-2"><label class="form-label fw-semibold">Litros <span class="text-danger">*</span></label><input type="number" step="0.01" class="form-control" v-model.number="fuelForm.liters" /></div>
                            <div class="col-md-2"><label class="form-label fw-semibold">Costo <span class="text-danger">*</span></label><input type="number" step="0.01" class="form-control" v-model.number="fuelForm.cost" /></div>
                            <div class="col-md-2"><label class="form-label fw-semibold">Km</label><input type="number" class="form-control" v-model.number="fuelForm.km_at_refuel" /></div>
                            <div class="col-md-3"><label class="form-label fw-semibold">Octanaje</label><input class="form-control" v-model="fuelForm.octane" placeholder="Magna / Premium" /></div>
                            <div class="col-md-6"><label class="form-label fw-semibold">Gasolinera</label><input class="form-control" v-model="fuelForm.station_name" /></div>
                        </div>
                        <div class="text-end mt-3">
                            <button class="btn btn-sm btn-outline-secondary me-2" @click="showFuelForm = false">Cancelar</button>
                            <button class="btn btn-sm btn-primary" :disabled="savingFuel" @click="saveFuel">
                                <i class="bi bi-check2 me-1"></i>{{ savingFuel ? 'Guardando…' : 'Guardar' }}
                            </button>
                        </div>
                    </div>

                    <div v-if="!fuelComputed.length" class="text-muted small py-3">Sin cargas registradas.</div>
                    <div class="flt-list">
                        <div class="flt-list-item" v-for="f in fuelComputed" :key="f.id">
                            <div class="flt-list-icon"><i class="bi bi-fuel-pump-fill text-warning"></i></div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-semibold">{{ Number(f.liters).toFixed(1) }} L
                                        <span class="text-muted fw-normal">· {{ fmtMoney(f.cost) }}</span></span>
                                    <span class="text-muted small" v-if="f.km_l">{{ f.km_l }} km/L</span>
                                </div>
                                <div class="text-muted small">
                                    {{ fmtDate(f.refuel_date) }}
                                    <span v-if="f.km_at_refuel"> · {{ fmtKm(f.km_at_refuel) }} km</span>
                                    <span v-if="f.octane"> · {{ f.octane }}</span>
                                    <span v-if="f.station_name"> · {{ f.station_name }}</span>
                                    <span> · {{ fmtMoney(f.cost_per_liter) }}/L</span>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-link text-danger" @click="deleteFuel(f)"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </section>

                <!-- f) TRACKING GPS ────────────────────────────────────── -->
                <section v-show="activeTab === 'gps'">
                    <!-- CON GPS: mapa + panel -->
                    <div v-if="vehicle.has_gps" class="flt-gps-track">
                        <div class="row g-3">
                            <!-- Mapa (60%) -->
                            <div class="col-lg-7">
                                <div class="flt-gps-mapbox">
                                    <div ref="gpsMapEl" class="flt-gps-map"></div>
                                    <div class="flt-gps-overlay">
                                        <span v-if="gpsRefreshing" class="badge bg-primary"><i class="bi bi-arrow-repeat spin me-1"></i>actualizando…</span>
                                        <button class="btn btn-sm btn-light" :title="gpsPaused ? 'Reanudar' : 'Pausar actualización'" @click="toggleGpsPause">
                                            <i class="bi" :class="gpsPaused ? 'bi-play-circle' : 'bi-pause-circle'"></i>
                                        </button>
                                    </div>
                                    <div v-if="gpsError" class="flt-gps-errbar">{{ gpsError }}</div>
                                </div>
                                <!-- Selector de rango -->
                                <div class="flt-gps-ranges mt-2">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button v-for="r in gpsRanges" :key="r.key" class="btn"
                                            :class="gpsRange === r.key ? 'btn-primary' : 'btn-outline-secondary'"
                                            @click="setGpsRange(r.key)">{{ r.label }}</button>
                                    </div>
                                    <button class="btn btn-sm btn-link ms-2" @click="refreshGps"><i class="bi bi-arrow-clockwise me-1"></i>Refrescar</button>
                                    <div v-if="gpsRange === 'custom'" class="d-flex align-items-center gap-2 mt-2 flex-wrap">
                                        <input type="datetime-local" class="form-control form-control-sm" style="max-width:210px" v-model="gpsCustom.from" />
                                        <span>→</span>
                                        <input type="datetime-local" class="form-control form-control-sm" style="max-width:210px" v-model="gpsCustom.to" />
                                        <button class="btn btn-sm btn-primary" @click="loadGpsHistory">Aplicar</button>
                                    </div>
                                </div>
                            </div>
                            <!-- Panel (40%) -->
                            <div class="col-lg-5">
                                <div class="flt-gps-card">
                                    <h6><i class="bi bi-speedometer2 me-1 text-success"></i>Estado actual</h6>
                                    <div v-if="gpsLast">
                                        <div class="flt-gps-row"><span>Último ping</span><strong>{{ gpsLastSeen }}</strong></div>
                                        <div class="flt-gps-row"><span>Velocidad</span><strong>{{ Math.round(gpsLast.speed) }} km/h</strong></div>
                                        <div class="flt-gps-row"><span>Rumbo</span><strong>{{ Math.round(gpsLast.heading) }}° {{ headingCardinal(gpsLast.heading) }}</strong></div>
                                        <div class="flt-gps-row"><span>Satélites</span><strong>{{ gpsLast.satellites ?? '—' }}</strong></div>
                                        <div class="flt-gps-row"><span>Estado</span><span class="badge" :style="{ background: gpsStatusColor }">{{ gpsStatusLabel }}</span></div>
                                    </div>
                                    <div v-else class="text-muted small">Sin posiciones registradas aún. Genera datos con <code>flotas:simulate-gps</code>.</div>
                                </div>
                                <div class="flt-gps-card">
                                    <h6><i class="bi bi-cpu me-1 text-info"></i>Dispositivo</h6>
                                    <div class="flt-gps-row"><span>Marca</span><strong>{{ (gpsDevice && gpsDevice.brand) || vehicle.gps_brand || '—' }}</strong></div>
                                    <div class="flt-gps-row"><span>Modelo</span><strong>{{ (gpsDevice && gpsDevice.model) || vehicle.gps_model || '—' }}</strong></div>
                                    <div class="flt-gps-row"><span>IMEI</span><strong>{{ (gpsDevice && gpsDevice.imei) || vehicle.gps_imei || '—' }}</strong></div>
                                    <div class="flt-gps-row"><span>SIM</span><strong>{{ (gpsDevice && gpsDevice.sim_number) || vehicle.gps_sim || '—' }}</strong></div>
                                </div>
                                <div class="flt-gps-card">
                                    <h6><i class="bi bi-graph-up me-1 text-primary"></i>Estadísticas hoy</h6>
                                    <div class="flt-gps-row"><span>Km recorridos</span><strong>{{ gpsStats ? gpsStats.km : 0 }} km</strong></div>
                                    <div class="flt-gps-row"><span>Tiempo en movimiento</span><strong>{{ gpsStats ? gpsStats.moving_minutes : 0 }} min</strong></div>
                                    <div class="flt-gps-row"><span>Paradas</span><strong>{{ gpsStats ? gpsStats.stops : 0 }}</strong></div>
                                </div>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-secondary btn-sm" @click="centerGps"><i class="bi bi-crosshair me-1"></i>Centrar mapa</button>
                                    <button class="btn btn-outline-secondary btn-sm" :disabled="!gpsHistory.length" @click="downloadGpx"><i class="bi bi-download me-1"></i>Descargar GPX</button>
                                    <button class="btn btn-outline-secondary btn-sm" @click="goTab('informacion')"><i class="bi bi-gear me-1"></i>Configurar dispositivo</button>
                                </div>
                            </div>
                        </div>

                        <!-- Eventos de geocercas recientes (Sub-fase 3.2) -->
                        <div class="flt-gps-card flt-geo-events mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <h6 class="mb-0"><i class="bi bi-bounding-box-circles me-1 text-primary"></i>Eventos de geocercas recientes</h6>
                                <div class="d-flex align-items-center gap-2">
                                    <a v-if="activeRulesCount > 0" :href="`${baseUrl}/reglas`" class="badge bg-primary text-decoration-none">
                                        <i class="bi bi-funnel me-1"></i>{{ activeRulesCount }} regla(s) activa(s)
                                    </a>
                                    <a v-else :href="`${baseUrl}/reglas`" class="text-muted small text-decoration-none">
                                        <i class="bi bi-funnel me-1"></i>Sin reglas — recibes todas las alertas
                                    </a>
                                    <button class="btn btn-sm btn-outline-primary" @click="openAlertPrefs">
                                        <i class="bi bi-bell me-1"></i>Configurar mis alertas
                                    </button>
                                </div>
                            </div>
                            <div v-if="!geofenceEvents.length" class="text-muted small">
                                <i class="bi bi-info-circle me-1"></i>Sin eventos de geocercas registrados aún.
                                Los eventos aparecerán automáticamente cuando el vehículo entre o salga de una geocerca asignada.
                            </div>
                            <div v-else class="flt-geo-ev-list">
                                <button type="button" class="flt-geo-ev" v-for="e in geofenceEvents" :key="e.id"
                                        :class="{ disabled: e.lat == null }" @click="focusGeofenceEvent(e)">
                                    <i class="bi" :class="e.type === 'enter' ? 'bi-arrow-right-circle-fill text-success' : 'bi-arrow-left-circle-fill text-warning'"></i>
                                    <span class="flex-grow-1 text-truncate">
                                        {{ e.type === 'enter' ? 'Entró a' : 'Salió de' }}
                                        <strong>{{ e.geofence_name }}</strong>
                                    </span>
                                    <span class="text-muted small flt-geo-ev-time">{{ relativeTime(e.occurred_at) }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- SIN GPS: wizard de activación -->
                    <div v-else class="flt-empty">
                        <i class="bi bi-broadcast-pin"></i>
                        <h6>Este vehículo no tiene GPS</h6>
                        <p class="text-muted">Activa el tracking registrando el dispositivo instalado.</p>
                        <button v-if="!showGpsForm" class="btn btn-primary" @click="showGpsForm = true">
                            <i class="bi bi-plus-lg me-1"></i>Activar tracking GPS
                        </button>
                        <div v-if="showGpsForm" class="flt-inline-form mt-3 text-start mx-auto" style="max-width:640px">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Marca</label>
                                    <select class="form-select" v-model="gpsForm.brand">
                                        <option value="ruptela">Ruptela</option>
                                        <option value="concox">Concox</option>
                                        <option value="gt06_generic">GT06 genérico</option>
                                        <option value="mock">Mock (para pruebas)</option>
                                    </select>
                                </div>
                                <div class="col-md-6"><label class="form-label fw-semibold">Modelo</label><input class="form-control" v-model="gpsForm.model" /></div>
                                <div class="col-md-6"><label class="form-label fw-semibold">IMEI</label><input class="form-control" v-model="gpsForm.imei" placeholder="Obligatorio y único" /></div>
                                <div class="col-md-3"><label class="form-label fw-semibold">SIM</label><input class="form-control" v-model="gpsForm.sim_number" /></div>
                                <div class="col-md-3"><label class="form-label fw-semibold">Operadora</label><input class="form-control" v-model="gpsForm.sim_carrier" /></div>
                            </div>
                            <div class="text-end mt-3">
                                <button class="btn btn-sm btn-outline-secondary me-2" @click="showGpsForm = false">Cancelar</button>
                                <button class="btn btn-sm btn-primary" :disabled="savingGps" @click="saveGps">
                                    <i class="bi bi-check2 me-1"></i>{{ savingGps ? 'Guardando…' : 'Activar' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- g) HISTORIAL ───────────────────────────────────────── -->
                <section v-show="activeTab === 'historial'">
                    <h6 class="flt-section-title">Historial completo</h6>
                    <div v-if="!historyEvents.length" class="text-muted small">Sin eventos.</div>
                    <div class="flt-timeline">
                        <div class="flt-tl-item" v-for="(e, i) in historyEvents" :key="i">
                            <div class="flt-tl-dot" :class="e.dot"></div>
                            <div class="flt-tl-content">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-semibold"><i :class="['bi', e.icon, 'me-1']"></i>{{ e.title }}</span>
                                    <a href="#" class="small" @click.prevent="goTab(e.tab)">Ver →</a>
                                </div>
                                <div class="text-muted small">{{ fmtDate(e.date) }} · {{ e.desc }}</div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- h) FOTOS ───────────────────────────────────────────── -->
                <section v-show="activeTab === 'fotos'">
                    <h6 class="flt-section-title">Galería</h6>
                    <div class="row g-3">
                        <div class="col-6 col-md-3" v-for="slot in photoSlots" :key="slot.key">
                            <div class="flt-photo-slot">
                                <div class="flt-photo-slot-head">{{ slot.label }}</div>
                                <div v-if="photosByType[slot.key]" class="flt-photo-thumb" @click="zoomPhoto(photosByType[slot.key])">
                                    <img :src="photoUrl(photosByType[slot.key])" @error="onImgError" />
                                    <button class="flt-photo-del" @click.stop="deletePhoto(photosByType[slot.key])"><i class="bi bi-trash"></i></button>
                                </div>
                                <div v-else class="flt-photo-empty" @click="pickPhoto(slot.key)">
                                    <i class="bi bi-camera"></i>
                                    <span>Subir</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h6 class="flt-section-title mt-4">Fotos adicionales</h6>
                    <div class="row g-3">
                        <div class="col-6 col-md-3" v-for="p in extraPhotos" :key="p.id">
                            <div class="flt-photo-thumb" @click="zoomPhoto(p)">
                                <img :src="photoUrl(p)" @error="onImgError" />
                                <button class="flt-photo-del" @click.stop="deletePhoto(p)"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="flt-photo-empty" style="height:100%;min-height:120px" @click="pickPhoto('general')">
                                <i class="bi bi-plus-lg"></i><span>Agregar</span>
                            </div>
                        </div>
                    </div>
                    <input ref="photoFile" type="file" accept="image/*" class="d-none" @change="onPickPhoto" />
                </section>

            </div>

            <!-- ── BOTONES FIJOS ABAJO ─────────────────────────────────── -->
            <div class="flt-fixed-bar">
                <button class="btn btn-outline-danger" @click="confirmDelete"><i class="bi bi-trash me-1"></i>Eliminar</button>
                <div>
                    <a :href="baseUrl" class="btn btn-outline-secondary me-2">Cancelar</a>
                    <button class="btn btn-primary" :disabled="savingInfo" @click="saveInfo">
                        <i class="bi bi-check2 me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </template>

        <!-- Zoom modal -->
        <div v-if="zoom" class="flt-zoom" @click="zoom = null">
            <img :src="photoUrl(zoom)" @error="onImgError" />
        </div>

        <!-- ── Modal de confirmación: eliminar vehículo ─────────────────── -->
        <div v-if="showDeleteModal" class="modal fade show flt-delete-modal" tabindex="-1" style="display:block"
             @click.self="!deleting && (showDeleteModal = false)">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>¿Eliminar este vehículo?
                        </h5>
                        <button type="button" class="btn-close" :disabled="deleting" @click="showDeleteModal = false"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3" v-if="vehicle">
                            Estás a punto de eliminar
                            <strong>{{ vehicle.brand }} {{ vehicle.model }}<span v-if="vehicle.year"> {{ vehicle.year }}</span></strong>
                            con placas <strong>{{ vehicle.plates || 'sin placas' }}</strong>.
                        </p>
                        <p class="mb-2">Esta acción también marcará como eliminados:</p>
                        <ul class="flt-delete-list">
                            <li><i class="bi bi-tools me-2 text-muted"></i>{{ deleteCounts.maintenances }} mantenimiento(s) registrado(s)</li>
                            <li><i class="bi bi-file-earmark-text me-2 text-muted"></i>{{ deleteCounts.documents }} documento(s)</li>
                            <li><i class="bi bi-person me-2 text-muted"></i>{{ deleteCounts.assignments }} asignación(es)</li>
                            <li><i class="bi bi-fuel-pump me-2 text-muted"></i>{{ deleteCounts.fuel }} carga(s) de combustible</li>
                            <li><i class="bi bi-images me-2 text-muted"></i>{{ deleteCounts.photos }} foto(s)</li>
                        </ul>
                        <div class="alert alert-light border small mb-0 mt-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Los datos quedarán en base de datos por seguridad (soft delete).
                            Si te equivocas, contacta al administrador para restaurarlos.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" :disabled="deleting" @click="showDeleteModal = false">Cancelar</button>
                        <button class="btn btn-danger" :disabled="deleting" @click="executeDelete">
                            <i class="bi bi-trash me-1"></i>{{ deleting ? 'Eliminando…' : 'Sí, eliminar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="showDeleteModal" class="modal-backdrop fade show"></div>

        <!-- ── Modal: configurar mis alertas (Sub-fase 3.3) ─────────────── -->
        <div v-if="showAlertModal" class="modal fade show flt-delete-modal" tabindex="-1" style="display:block"
             @click.self="showAlertModal = false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-bell me-2 text-primary"></i>Mis alertas de este vehículo</h5>
                        <button type="button" class="btn-close" @click="showAlertModal = false"></button>
                    </div>
                    <div class="modal-body">
                        <div v-if="alertPrefs.loading" class="text-center text-muted py-3">
                            <div class="spinner-border spinner-border-sm me-2"></div>Cargando…
                        </div>
                        <template v-else>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="alertActive" v-model="alertPrefs.active">
                                <label class="form-check-label fw-semibold" for="alertActive">Recibir alertas de este vehículo</label>
                            </div>

                            <fieldset :disabled="!alertPrefs.active" :class="{ 'opacity-50': !alertPrefs.active }">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Geocercas</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="alertAllGeo" v-model="alertPrefs.all_geofences">
                                        <label class="form-check-label" for="alertAllGeo">Todas las geocercas del vehículo</label>
                                    </div>
                                    <select v-if="!alertPrefs.all_geofences" class="form-select form-select-sm mt-2" multiple size="4" v-model="alertPrefs.geofence_ids">
                                        <option v-for="g in alertPrefs.geofences" :key="g.id" :value="g.id">{{ g.name }}</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Tipos de evento</label>
                                    <div class="d-flex gap-3">
                                        <div class="form-check"><input class="form-check-input" type="checkbox" id="evEnter" value="enter" v-model="alertPrefs.event_types"><label class="form-check-label" for="evEnter">Entrada</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" id="evExit" value="exit" v-model="alertPrefs.event_types"><label class="form-check-label" for="evExit">Salida</label></div>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label fw-semibold">Canales</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="chEmail" value="email" v-model="alertPrefs.channels">
                                        <label class="form-check-label" for="chEmail">Email <span class="text-muted small">→ {{ alertPrefs.user_email || 'sin email' }}</span></label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="chWa" value="whatsapp" v-model="alertPrefs.channels">
                                        <label class="form-check-label" for="chWa">WhatsApp <span class="text-muted small">→ {{ alertPrefs.user_phone || 'sin teléfono' }}</span></label>
                                    </div>
                                </div>
                            </fieldset>
                        </template>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" @click="showAlertModal = false">Cancelar</button>
                        <button class="btn btn-primary" :disabled="alertPrefs.saving" @click="saveAlertPrefs">
                            <i class="bi bi-check2 me-1"></i>{{ alertPrefs.saving ? 'Guardando…' : 'Guardar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="showAlertModal" class="modal-backdrop fade show"></div>

        <!-- ── Modal: instrucciones para apuntar el GPS físico (Sub-fase 2.3a) ── -->
        <div v-if="gpsInstructions" class="modal fade show flt-delete-modal" tabindex="-1" style="display:block"
             @click.self="gpsInstructions = null">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-broadcast me-2 text-primary"></i>Configura tu GPS</h5>
                        <button type="button" class="btn-close" @click="gpsInstructions = null"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">Dispositivo registrado. Ahora configura el GPS apuntando al servidor:</p>
                        <table class="table table-sm">
                            <tbody>
                                <tr><td class="text-muted">Servidor (IP)</td><td><strong>{{ gpsInstructions.ip }}</strong></td></tr>
                                <tr><td class="text-muted">Puerto</td><td><strong>{{ gpsInstructions.port }}</strong></td></tr>
                                <tr><td class="text-muted">Protocolo</td><td><strong>{{ gpsInstructions.protocol }}</strong></td></tr>
                                <tr><td class="text-muted">IMEI</td><td><strong>{{ gpsInstructions.imei }}</strong></td></tr>
                            </tbody>
                        </table>
                        <div class="alert alert-light border small mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Usa el <strong>Ruptela Configurator</strong> (Server IP + Puerto + TCP). Cuando el
                            dispositivo se conecte por primera vez, su estado pasará a <em>activo</em> y el
                            recorrido aparecerá en esta pestaña automáticamente.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-primary" @click="gpsInstructions = null">Entendido</button>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="gpsInstructions" class="modal-backdrop fade show"></div>

        <!-- Toast -->
        <transition name="flt-toast-fade">
            <div v-if="toast.visible" class="flt-toast" :class="`flt-toast-${toast.type}`">
                <i :class="toast.icon" class="me-2"></i>{{ toast.message }}
            </div>
        </transition>
    </div>
</template>

<script>
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import axios from 'axios';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

export default {
    name: 'FleetVehicleShow',
    props: {
        baseUrl: { type: String, default: '/flotas' },
    },
    setup(props) {
        // ── IDs y navegación ──────────────────────────────────────────────
        const path = window.location.pathname.split('/').filter(Boolean);
        const vehicleId = path[path.length - 1];
        const queryTab = new URLSearchParams(window.location.search).get('tab');

        const tabKeys = ['informacion', 'asignacion', 'mantenimientos', 'documentos', 'combustible', 'gps', 'historial', 'fotos'];
        const activeTab = ref(tabKeys.includes(queryTab) ? queryTab : 'informacion');
        const moreOpen = ref(false);

        const loading   = ref(true);
        const loadError = ref(false);
        const vehicle   = ref(null);
        const operators = ref([]);
        const providers = ref([]);
        const assignments = ref([]);

        const toast = ref({ visible: false, message: '', type: 'success', icon: '', timer: null });
        function showToast(message, type = 'success', icon = 'bi bi-check-circle-fill') {
            if (toast.value.timer) clearTimeout(toast.value.timer);
            toast.value = { visible: true, message, type, icon, timer: setTimeout(() => { toast.value.visible = false; }, 4000) };
        }
        function ok(m) { showToast(m, 'success', 'bi bi-check-circle-fill'); }
        function err(e, fallback) {
            const msg = e?.response?.data?.message || e?.response?.data?.error || fallback || 'Ocurrió un error.';
            showToast(msg, 'error', 'bi bi-exclamation-circle-fill');
        }

        // ── Formatters ────────────────────────────────────────────────────
        const moneyFmt = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
        const fmtMoney = (v) => moneyFmt.format(Number(v || 0));
        const fmtKm = (v) => new Intl.NumberFormat('es-MX').format(Number(v || 0));
        const fmtDate = (v) => {
            if (!v) return '—';
            const d = new Date(v);
            if (isNaN(d)) return v;
            return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
        };

        // ── Catálogos / labels ────────────────────────────────────────────
        const statusLabels = { active: 'Activo', in_workshop: 'En taller', inactive: 'Inactivo' };
        const statusLabel = (s) => statusLabels[s] || s;
        const fuelLabels = { gasoline: 'Gasolina', diesel: 'Diésel', electric: 'Eléctrico', hybrid: 'Híbrido' };
        const typeLabels = { car: 'Automóvil', pickup: 'Pickup / Camioneta', truck: 'Camión', motorcycle: 'Motocicleta', other: 'Otro' };
        const maintLabels = { preventive: 'Preventivo', corrective: 'Correctivo', emergency: 'Emergencia', verification: 'Verificación' };
        const maintTypeLabel = (t) => maintLabels[t] || t;
        const maintIcon = (t) => ({
            preventive:   { icon: 'bi-droplet-fill',           color: 'text-success' },
            corrective:   { icon: 'bi-exclamation-triangle-fill', color: 'text-danger' },
            emergency:    { icon: 'bi-exclamation-octagon-fill',  color: 'text-danger' },
            verification: { icon: 'bi-patch-check-fill',          color: 'text-info' },
        }[t] || { icon: 'bi-tools', color: 'text-secondary' });

        const docTypeLabels = {
            circulation_card: 'Tarjeta de circulación', insurance_policy: 'Póliza de seguro',
            tenencia: 'Tenencia', verification: 'Verificación', operator_license: 'Licencia del operador',
            special_permit: 'Permiso especial', other: 'Otro',
        };
        const docIcon = (t) => ({
            circulation_card: 'bi-card-heading', insurance_policy: 'bi-shield-check', tenencia: 'bi-receipt',
            verification: 'bi-clipboard-check', operator_license: 'bi-person-vcard', special_permit: 'bi-file-earmark-ruled', other: 'bi-file-earmark',
        }[t] || 'bi-file-earmark');

        const worksCatalog = [
            'Cambio de aceite', 'Filtro de aceite', 'Filtro de aire', 'Filtro de gasolina',
            'Frenos', 'Llantas', 'Suspensión', 'Afinación', 'Batería', 'Sistema eléctrico',
            'Transmisión', 'Clutch', 'Alineación y balanceo', 'Anticongelante', 'Bandas', 'Otro',
        ];

        const vehicleIcon = computed(() => (vehicle.value?.vehicle_type === 'motorcycle' ? 'bi-scooter' : 'bi-truck-front'));

        // ── Documentos: status calculado lado cliente ─────────────────────
        function docStatus(d) {
            if (!d?.expiration_date) return 'vigente';
            const exp = new Date(d.expiration_date);
            const today = new Date(); today.setHours(0, 0, 0, 0);
            const in30 = new Date(today); in30.setDate(in30.getDate() + 30);
            if (exp < today) return 'vencido';
            if (exp <= in30) return 'por_vencer';
            return 'vigente';
        }
        function docDays(d) {
            if (!d?.expiration_date) return null;
            const exp = new Date(d.expiration_date);
            const today = new Date(); today.setHours(0, 0, 0, 0);
            return Math.round((exp - today) / 86400000);
        }
        const decoratedDocuments = computed(() =>
            (vehicle.value?.documents ?? []).map((d) => ({ ...d, _status: docStatus(d), _days: docDays(d) }))
        );

        // ── Maintenances ──────────────────────────────────────────────────
        const maintenances = computed(() => vehicle.value?.maintenances ?? []);
        const thisYear = new Date().getFullYear();
        const isThisYear = (dateStr) => dateStr && new Date(dateStr).getFullYear() === thisYear;
        const isThisMonth = (dateStr) => {
            if (!dateStr) return false;
            const d = new Date(); const t = new Date(dateStr);
            return t.getFullYear() === d.getFullYear() && t.getMonth() === d.getMonth();
        };
        const maintYearCount = computed(() => maintenances.value.filter((m) => isThisYear(m.service_date)).length);
        const maintSpendYear = computed(() => maintenances.value.filter((m) => isThisYear(m.service_date)).reduce((s, m) => s + Number(m.total_cost || 0), 0));
        const maintAvg = computed(() => (maintenances.value.length ? maintenances.value.reduce((s, m) => s + Number(m.total_cost || 0), 0) / maintenances.value.length : 0));
        const spendYear = computed(() => maintSpendYear.value); // gasto del año = mantenimientos del año (Fase 1)

        // Próximo servicio
        const nextServiceRecord = computed(() => {
            const withNext = maintenances.value.filter((m) => m.next_service_date || m.next_service_km);
            if (!withNext.length) return null;
            return withNext.slice().sort((a, b) => new Date(b.service_date) - new Date(a.service_date))[0];
        });
        const nextServiceText = computed(() => {
            const r = nextServiceRecord.value;
            if (!r) return '—';
            if (r.next_service_km) {
                const remaining = r.next_service_km - Number(vehicle.value?.current_km || 0);
                return remaining > 0 ? `${fmtKm(remaining)} km` : 'Vencido';
            }
            if (r.next_service_date) return fmtDate(r.next_service_date);
            return '—';
        });
        const nextServiceBanner = computed(() => {
            const r = nextServiceRecord.value;
            if (!r) return null;
            const parts = [];
            if (r.next_service_date) parts.push(`fecha estimada ${fmtDate(r.next_service_date)}`);
            if (r.next_service_km) parts.push(`a los ${fmtKm(r.next_service_km)} km`);
            return `Próximo servicio: ${parts.join(' · ')}`;
        });

        // Filtros mantenimientos
        const maintSearch = ref('');
        const maintTypeFilter = ref('');
        const maintPeriodFilter = ref('');
        const filteredMaintenances = computed(() => maintenances.value.filter((m) => {
            if (maintTypeFilter.value && m.type !== maintTypeFilter.value) return false;
            if (maintPeriodFilter.value === 'year' && !isThisYear(m.service_date)) return false;
            if (maintPeriodFilter.value === 'month' && !isThisMonth(m.service_date)) return false;
            if (maintSearch.value) {
                const q = maintSearch.value.toLowerCase();
                const hay = `${maintTypeLabel(m.type)} ${m.description || ''} ${m.mechanic_name || ''} ${(m.works || []).join(' ')} ${m.provider?.name || ''}`.toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        }));

        // ── Documentos: counts/filtros ────────────────────────────────────
        const docCounts = computed(() => {
            const c = { vencido: 0, por_vencer: 0, vigente: 0 };
            decoratedDocuments.value.forEach((d) => { c[d._status] = (c[d._status] || 0) + 1; });
            return c;
        });
        const docsCriticalCount = computed(() => docCounts.value.vencido + docCounts.value.por_vencer);
        const docsAlertCount = computed(() => docsCriticalCount.value);
        const docsAlertColorClass = computed(() => (docCounts.value.vencido > 0 ? 'text-danger' : (docCounts.value.por_vencer > 0 ? 'text-warning' : '')));
        const docCostYear = computed(() => decoratedDocuments.value.filter((d) => isThisYear(d.issue_date)).reduce((s, d) => s + Number(d.cost || 0), 0));
        const docTypeFilter = ref('');
        const docStatusFilter = ref('');
        const filteredDocuments = computed(() => decoratedDocuments.value
            .filter((d) => (!docTypeFilter.value || d.document_type === docTypeFilter.value))
            .filter((d) => (!docStatusFilter.value || d._status === docStatusFilter.value))
            .slice()
            .sort((a, b) => ({ vencido: 0, por_vencer: 1, vigente: 2 }[a._status] - { vencido: 0, por_vencer: 1, vigente: 2 }[b._status])));
        const docBadgeClass = (s) => ({ vencido: 'bg-danger', por_vencer: 'bg-warning text-dark', vigente: 'bg-success' }[s] || 'bg-secondary');
        const docBadgeText = (d) => {
            if (d._status === 'vencido') return 'VENCIDA';
            if (d._status === 'por_vencer') return `VENCE EN ${d._days} DÍA${d._days === 1 ? '' : 'S'}`;
            return 'Vigente';
        };

        // ── Combustible: rendimiento km/L ─────────────────────────────────
        const fuelComputed = computed(() => {
            const logs = (vehicle.value?.fuelLog ?? []).slice()
                .sort((a, b) => new Date(a.refuel_date) - new Date(b.refuel_date));
            let prevKm = null;
            const out = logs.map((f) => {
                let km_l = null;
                if (prevKm != null && f.km_at_refuel && f.liters > 0 && f.km_at_refuel > prevKm) {
                    km_l = ((f.km_at_refuel - prevKm) / Number(f.liters)).toFixed(1);
                }
                if (f.km_at_refuel) prevKm = f.km_at_refuel;
                return { ...f, km_l, cost_per_liter: Number(f.liters) > 0 ? (Number(f.cost) / Number(f.liters)) : 0 };
            });
            return out.reverse();
        });

        // ── Fotos ─────────────────────────────────────────────────────────
        const photoSlots = [
            { key: 'front', label: 'Frente' }, { key: 'side', label: 'Lateral' },
            { key: 'rear', label: 'Trasera' }, { key: 'dashboard', label: 'Tablero' },
        ];
        const photosByType = computed(() => {
            const map = {};
            (vehicle.value?.photos ?? []).forEach((p) => { if (p.photo_type !== 'general' && !map[p.photo_type]) map[p.photo_type] = p; });
            return map;
        });
        const extraPhotos = computed(() => (vehicle.value?.photos ?? []).filter((p) => p.photo_type === 'general'));
        const photoUrl = (p) => (p?.file_path ? `/storage/${p.file_path}` : '');
        const onImgError = (e) => { e.target.style.display = 'none'; e.target.parentElement.classList.add('flt-photo-broken'); };
        const zoom = ref(null);
        const zoomPhoto = (p) => { zoom.value = p; };

        // ── Header / info derivados ───────────────────────────────────────
        const currentAssignment = computed(() => vehicle.value?.current_assignment || vehicle.value?.currentAssignment || null);
        const currentOperatorName = computed(() => currentAssignment.value?.operator?.name || null);

        const infoFields = [
            { key: 'plates', label: 'Placas' },
            { key: 'brand', label: 'Marca' },
            { key: 'model', label: 'Modelo' },
            { key: 'year', label: 'Año' },
            { key: 'color', label: 'Color' },
            { key: 'vehicle_type', label: 'Tipo', format: (v) => typeLabels[v] || v },
            { key: 'fuel_type', label: 'Combustible', format: (v) => fuelLabels[v] || v },
            { key: 'tank_capacity_liters', label: 'Cap. tanque', format: (v) => (v ? `${v} L` : '—') },
            { key: 'current_km', label: 'Kilometraje', format: (v) => `${fmtKm(v)} km` },
            { key: 'status', label: 'Estado', format: (v) => statusLabel(v) },
            { key: 'vin', label: 'VIN' },
            { key: 'motor_number', label: 'No. motor' },
            { key: 'habitual_location', label: 'Ubicación habitual' },
            { key: 'notes', label: 'Notas' },
        ];

        // ── Tabs meta ─────────────────────────────────────────────────────
        const tabs = computed(() => [
            { key: 'informacion', label: 'Información', icon: 'bi-info-circle' },
            { key: 'asignacion', label: 'Asignación', icon: 'bi-person-badge' },
            { key: 'mantenimientos', label: 'Mantenimientos', icon: 'bi-tools', badge: maintenances.value.length || null },
            { key: 'documentos', label: 'Documentos', icon: 'bi-file-earmark-text', badge: docsCriticalCount.value || null },
            { key: 'combustible', label: 'Combustible', icon: 'bi-fuel-pump' },
            { key: 'gps', label: 'Tracking GPS', icon: 'bi-broadcast' },
            { key: 'historial', label: 'Historial', icon: 'bi-clock-history' },
            { key: 'fotos', label: 'Fotos', icon: 'bi-images' },
        ]);
        function goTab(k) {
            activeTab.value = k;
            const url = new URL(window.location);
            url.searchParams.set('tab', k);
            window.history.replaceState({}, '', url);
        }

        // ── Historial agregado ────────────────────────────────────────────
        const historyEvents = computed(() => {
            const ev = [];
            maintenances.value.forEach((m) => ev.push({ date: m.service_date, icon: maintIcon(m.type).icon, dot: 'bg-primary', title: `Mantenimiento ${maintTypeLabel(m.type).toLowerCase()}`, desc: fmtMoney(m.total_cost), tab: 'mantenimientos' }));
            decoratedDocuments.value.forEach((d) => ev.push({ date: d.issue_date || d.expiration_date, icon: docIcon(d.document_type), dot: 'bg-info', title: docTypeLabels[d.document_type] || 'Documento', desc: d.expiration_date ? `Vence ${fmtDate(d.expiration_date)}` : 'Registrado', tab: 'documentos' }));
            assignments.value.forEach((a) => ev.push({ date: a.since, icon: 'bi-person-badge', dot: 'bg-success', title: 'Asignación', desc: `${a.operator_name || 'Operador'} · ${a.department || 's/depto'}`, tab: 'asignacion' }));
            (vehicle.value?.fuelLog ?? []).forEach((f) => ev.push({ date: f.refuel_date, icon: 'bi-fuel-pump-fill', dot: 'bg-warning', title: 'Carga de combustible', desc: `${Number(f.liters).toFixed(1)} L · ${fmtMoney(f.cost)}`, tab: 'combustible' }));
            (vehicle.value?.photos ?? []).forEach((p) => ev.push({ date: p.taken_at || p.created_at, icon: 'bi-image', dot: 'bg-secondary', title: 'Foto agregada', desc: p.photo_type, tab: 'fotos' }));
            return ev.filter((e) => e.date).sort((a, b) => new Date(b.date) - new Date(a.date));
        });

        // ══════════════════════════════════════════════════════════════════
        //  ACCIONES
        // ══════════════════════════════════════════════════════════════════

        // Editar info
        const editInfo = ref(false);
        const savingInfo = ref(false);
        const editForm = reactive({});
        function startEditInfo() {
            Object.assign(editForm, vehicle.value);
            editInfo.value = true;
            goTab('informacion');
        }
        function cancelEditInfo() { editInfo.value = false; }
        async function saveInfo() {
            savingInfo.value = true;
            try {
                const payload = editInfo.value ? editForm : vehicle.value;
                const { data } = await axios.patch(`${props.baseUrl}/api/vehiculos/${vehicleId}`, payload);
                Object.assign(vehicle.value, data.vehicle ?? {});
                editInfo.value = false;
                ok('Vehículo actualizado.');
            } catch (e) { err(e, 'No se pudo actualizar el vehículo.'); }
            finally { savingInfo.value = false; }
        }

        // Eliminar vehículo — modal de confirmación
        const showDeleteModal = ref(false);
        const deleting = ref(false);
        // Contadores reales tomados de los datos ya cargados en la ficha
        const deleteCounts = computed(() => ({
            maintenances: (vehicle.value?.maintenances ?? []).length,
            documents:    (vehicle.value?.documents ?? []).length,
            assignments:  assignments.value.length,
            fuel:         (vehicle.value?.fuelLog ?? []).length,
            photos:       (vehicle.value?.photos ?? []).length,
        }));
        function confirmDelete() {
            moreOpen.value = false;       // cierra el dropdown si vino de ahí
            showDeleteModal.value = true; // NO elimina: solo abre el modal
        }
        async function executeDelete() {
            deleting.value = true;
            try {
                await axios.delete(`${props.baseUrl}/api/vehiculos/${vehicleId}`);
                ok('Vehículo eliminado correctamente.');
                setTimeout(() => { window.location.href = props.baseUrl; }, 800);
            } catch (e) {
                err(e, 'No se pudo eliminar el vehículo.');
                deleting.value = false; // el modal sigue abierto para reintentar
            }
        }

        // Asignación
        const showAssignForm = ref(false);
        const savingAssign = ref(false);
        const assignForm = reactive({ user_id: null, department: '', since: new Date().toISOString().split('T')[0], until: '', notes: '' });
        async function saveAssignment() {
            if (!assignForm.user_id) { err(null, 'Selecciona un operador.'); return; }
            savingAssign.value = true;
            try {
                const payload = { ...assignForm };
                if (!payload.until) delete payload.until;
                await axios.post(`${props.baseUrl}/api/vehiculos/${vehicleId}/asignaciones`, payload);
                ok('Asignación registrada.');
                showAssignForm.value = false;
                await Promise.all([loadAssignments(), loadVehicle()]);
            } catch (e) { err(e, 'No se pudo registrar la asignación.'); }
            finally { savingAssign.value = false; }
        }

        // Mantenimiento
        const showMaintForm = ref(false);
        const savingMaint = ref(false);
        const dragMaint = ref(false);
        const maintFiles = ref([]);
        const defaultMaint = () => ({
            type: 'preventive', service_date: new Date().toISOString().split('T')[0], service_km: vehicle.value?.current_km ?? null,
            mechanic_name: '', works: [], provider_id: null, description: '',
            labor_cost: 0, parts_cost: 0, other_cost: 0,
            next_mode: 'none', next_service_km: null, next_service_date: null,
        });
        const maintForm = reactive(defaultMaint());
        const maintTotalLive = computed(() => Number(maintForm.labor_cost || 0) + Number(maintForm.parts_cost || 0) + Number(maintForm.other_cost || 0));
        const onDropMaint = (e) => { dragMaint.value = false; maintFiles.value.push(...Array.from(e.dataTransfer.files)); };
        const onPickMaint = (e) => { maintFiles.value.push(...Array.from(e.target.files)); e.target.value = ''; };

        const showProviderForm = ref(false);
        const newProviderName = ref('');
        const creatingProvider = ref(false);
        async function createProvider() {
            if (!newProviderName.value.trim()) return;
            creatingProvider.value = true;
            try {
                const { data } = await axios.post(`${props.baseUrl}/api/proveedores`, { name: newProviderName.value.trim(), type: 'workshop' });
                providers.value.push(data.provider);
                maintForm.provider_id = data.provider.id;
                newProviderName.value = '';
                showProviderForm.value = false;
                ok('Proveedor creado.');
            } catch (e) { err(e, 'No se pudo crear el proveedor.'); }
            finally { creatingProvider.value = false; }
        }

        async function saveMaint(isDraft, keepOpen = false) {
            savingMaint.value = true;
            try {
                const payload = {
                    vehicle_id: Number(vehicleId), type: maintForm.type, service_date: maintForm.service_date,
                    service_km: maintForm.service_km || null, works: maintForm.works, description: maintForm.description || null,
                    provider_id: maintForm.provider_id, mechanic_name: maintForm.mechanic_name || null,
                    labor_cost: maintForm.labor_cost || 0, parts_cost: maintForm.parts_cost || 0, other_cost: maintForm.other_cost || 0,
                    is_draft: !!isDraft,
                    next_service_km: ['km', 'both'].includes(maintForm.next_mode) ? maintForm.next_service_km : null,
                    next_service_date: ['date', 'both'].includes(maintForm.next_mode) ? maintForm.next_service_date : null,
                };
                const { data } = await axios.post(`${props.baseUrl}/api/mantenimientos`, payload);
                const newId = data.maintenance?.id;
                // Subir evidencia
                for (const file of maintFiles.value) {
                    const fd = new FormData(); fd.append('file', file);
                    await axios.post(`${props.baseUrl}/api/mantenimientos/${newId}/files`, fd).catch(() => {});
                }
                ok(isDraft ? 'Borrador guardado.' : 'Mantenimiento registrado.');
                await loadVehicle();
                maintFiles.value = [];
                Object.assign(maintForm, defaultMaint());
                if (!keepOpen) showMaintForm.value = false;
            } catch (e) { err(e, 'No se pudo guardar el mantenimiento.'); }
            finally { savingMaint.value = false; }
        }
        async function deleteMaint(m) {
            if (!window.confirm('¿Eliminar este mantenimiento?')) return;
            try { await axios.delete(`${props.baseUrl}/api/mantenimientos/${m.id}`); ok('Mantenimiento eliminado.'); await loadVehicle(); }
            catch (e) { err(e, 'No se pudo eliminar.'); }
        }

        // Documento
        const showDocForm = ref(false);
        const savingDoc = ref(false);
        const dragDoc = ref(false);
        const docFile = ref(null);
        const defaultDoc = () => ({
            document_type: 'circulation_card', folio_number: '', issued_by: '', issue_date: '', expiration_date: '', cost: null,
            alert_30_days: true, alert_7_days: true, alert_1_day: true, alert_same_day: false, alert_channels: ['email'],
        });
        const docForm = reactive(defaultDoc());
        const onDropDoc = (e) => { dragDoc.value = false; if (e.dataTransfer.files[0]) docFile.value = e.dataTransfer.files[0]; };
        const onPickDoc = (e) => { if (e.target.files[0]) docFile.value = e.target.files[0]; };
        async function saveDoc() {
            savingDoc.value = true;
            try {
                const payload = { ...docForm, vehicle_id: Number(vehicleId) };
                Object.keys(payload).forEach((k) => { if (payload[k] === '') payload[k] = null; });
                await axios.post(`${props.baseUrl}/api/documentos`, payload);
                ok('Documento registrado.');
                showDocForm.value = false;
                Object.assign(docForm, defaultDoc());
                await loadVehicle();
            } catch (e) { err(e, 'No se pudo guardar el documento.'); }
            finally { savingDoc.value = false; }
        }
        function renewDoc(d) {
            Object.assign(docForm, defaultDoc(), { document_type: d.document_type, issued_by: d.issued_by || '' });
            showDocForm.value = true;
            ok('Captura los datos del documento renovado.');
        }

        // Combustible
        const showFuelForm = ref(false);
        const savingFuel = ref(false);
        const defaultFuel = () => ({ refuel_date: new Date().toISOString().split('T')[0], liters: null, cost: null, km_at_refuel: vehicle.value?.current_km ?? null, octane: '', station_name: '' });
        const fuelForm = reactive(defaultFuel());
        async function saveFuel() {
            if (!fuelForm.liters || !fuelForm.cost) { err(null, 'Litros y costo son requeridos.'); return; }
            savingFuel.value = true;
            try {
                await axios.post(`${props.baseUrl}/api/combustible`, { ...fuelForm, vehicle_id: Number(vehicleId) });
                ok('Carga registrada.');
                showFuelForm.value = false;
                Object.assign(fuelForm, defaultFuel());
                await loadVehicle();
            } catch (e) { err(e, 'No se pudo registrar la carga.'); }
            finally { savingFuel.value = false; }
        }
        async function deleteFuel(f) {
            if (!window.confirm('¿Eliminar esta carga?')) return;
            try { await axios.delete(`${props.baseUrl}/api/combustible/${f.id}`); ok('Carga eliminada.'); await loadVehicle(); }
            catch (e) { err(e, 'No se pudo eliminar.'); }
        }

        // ── GPS / Tracking ────────────────────────────────────────────────
        const GPS_COLORS = { moving: '#22c55e', stopped: '#f59e0b', idle: '#9ca3af', offline: '#ef4444' };
        const GPS_LABELS = { moving: 'En movimiento', stopped: 'Detenido', idle: 'Inactivo', offline: 'Sin señal' };

        const showGpsForm = ref(false);
        const savingGps = ref(false);
        const gpsForm = reactive({ brand: 'mock', model: '', imei: '', sim_number: '', sim_carrier: '' });

        const gpsMapEl = ref(null);
        const gpsLast = ref(null);
        const gpsDevice = ref(null);
        const gpsStats = ref(null);
        const gpsLiveStatus = ref('offline');
        const gpsHistory = ref([]);
        const gpsRange = ref('24h');
        const gpsCustom = reactive({ from: '', to: '' });
        const gpsRefreshing = ref(false);
        const gpsPaused = ref(false);
        const gpsError = ref('');
        const gpsRanges = [
            { key: '6h', label: 'Últimas 6h' },
            { key: '24h', label: '24h' },
            { key: '7d', label: '7 días' },
            { key: 'custom', label: 'Personalizado' },
        ];

        let gpsMap = null;
        let gpsMarker = null;
        let gpsPath = null;
        let gpsTimer = null;

        const gpsStatusColor = computed(() => GPS_COLORS[gpsLiveStatus.value] || GPS_COLORS.offline);
        const gpsStatusLabel = computed(() => GPS_LABELS[gpsLiveStatus.value] || GPS_LABELS.offline);
        const gpsLastSeen = computed(() => {
            const iso = gpsLast.value?.recorded_at;
            if (!iso) return '—';
            const diff = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
            if (diff < 1) return 'ahora';
            if (diff < 60) return `hace ${diff} min`;
            return `hace ${Math.round(diff / 60)} h`;
        });

        function headingCardinal(deg) {
            const dirs = ['N', 'NE', 'E', 'SE', 'S', 'SO', 'O', 'NO'];
            return dirs[Math.round((deg % 360) / 45) % 8];
        }

        function gpsRangeBounds() {
            const to = new Date();
            let from = new Date(to.getTime() - 24 * 3600 * 1000);
            if (gpsRange.value === '6h') from = new Date(to.getTime() - 6 * 3600 * 1000);
            else if (gpsRange.value === '7d') from = new Date(to.getTime() - 7 * 24 * 3600 * 1000);
            else if (gpsRange.value === 'custom') {
                return {
                    from: gpsCustom.from ? new Date(gpsCustom.from).toISOString() : from.toISOString(),
                    to: gpsCustom.to ? new Date(gpsCustom.to).toISOString() : to.toISOString(),
                };
            }
            return { from: from.toISOString(), to: to.toISOString() };
        }

        function ensureGpsMap() {
            if (!vehicle.value?.has_gps) return;
            nextTick(() => {
                if (!gpsMapEl.value) return;
                if (!gpsMap) {
                    gpsMap = L.map(gpsMapEl.value).setView([19.4326, -99.1332], 12);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                    }).addTo(gpsMap);
                    refreshGps();
                    startGpsPolling();
                } else {
                    gpsMap.invalidateSize();
                }
            });
        }

        function updateGpsMarker() {
            if (!gpsMap || !gpsLast.value || gpsLast.value.lat == null) return;
            const ll = [gpsLast.value.lat, gpsLast.value.lng];
            const html = `<div class="flt-popup"><strong>${vehicle.value?.display_name ?? ''}</strong><br>
                Vel: ${Math.round(gpsLast.value.speed)} km/h<br>
                Rumbo: ${Math.round(gpsLast.value.heading)}°<br>
                <span class="text-muted small">${gpsLastSeen.value}</span></div>`;
            if (!gpsMarker) {
                gpsMarker = L.circleMarker(ll, { radius: 9, color: '#fff', weight: 2, fillColor: gpsStatusColor.value, fillOpacity: 1 }).addTo(gpsMap);
                gpsMarker.bindPopup(html);
            } else {
                gpsMarker.setLatLng(ll);
                gpsMarker.setStyle({ fillColor: gpsStatusColor.value });
                gpsMarker.setPopupContent(html);
            }
        }

        function drawGpsPath() {
            if (!gpsMap) return;
            const latlngs = gpsHistory.value.filter((p) => p.lat != null).map((p) => [p.lat, p.lng]);
            if (gpsPath) { gpsMap.removeLayer(gpsPath); gpsPath = null; }
            if (latlngs.length) {
                gpsPath = L.polyline(latlngs, { color: '#2563eb', weight: 4, opacity: 0.75 }).addTo(gpsMap);
                gpsMap.fitBounds(gpsPath.getBounds(), { padding: [30, 30], maxZoom: 16 });
            } else if (gpsLast.value?.lat != null) {
                gpsMap.setView([gpsLast.value.lat, gpsLast.value.lng], 14);
            }
        }

        async function loadGpsStatus() {
            try {
                const { data } = await axios.get(`${props.baseUrl}/api/vehiculos/${vehicleId}/gps`);
                gpsLast.value = data?.last ?? null;
                gpsDevice.value = data?.device ?? null;
                gpsStats.value = data?.stats_today ?? null;
                gpsLiveStatus.value = data?.live_status ?? 'offline';
                gpsError.value = '';
                updateGpsMarker();
            } catch (e) {
                gpsError.value = 'No se pudo cargar el estado GPS.';
            }
        }

        async function loadGpsHistory() {
            const { from, to } = gpsRangeBounds();
            try {
                const { data } = await axios.get(`${props.baseUrl}/api/vehiculos/${vehicleId}/gps/history`, { params: { from, to } });
                gpsHistory.value = data?.positions ?? [];
                drawGpsPath();
            } catch (e) {
                gpsError.value = 'No se pudo cargar el historial GPS.';
            }
        }

        async function refreshGps() {
            gpsRefreshing.value = true;
            try { await Promise.all([loadGpsStatus(), loadGpsHistory(), loadGeofenceEvents(), loadRulesCount()]); }
            finally { gpsRefreshing.value = false; }
        }

        // ── Reglas activas del usuario (Sub-fase 3.4) ─────────────────────
        const activeRulesCount = ref(0);
        async function loadRulesCount() {
            try {
                const { data } = await axios.get(`${props.baseUrl}/api/reglas`);
                activeRulesCount.value = (data?.rules ?? []).filter((r) => r.active).length;
            } catch (e) {
                activeRulesCount.value = 0;
            }
        }

        // ── Eventos de geocercas (Sub-fase 3.2) ───────────────────────────
        const geofenceEvents = ref([]);
        async function loadGeofenceEvents() {
            try {
                const { data } = await axios.get(`${props.baseUrl}/api/vehiculos/${vehicleId}/geofence-events`, { params: { limit: 20 } });
                geofenceEvents.value = data?.events ?? [];
            } catch (e) {
                geofenceEvents.value = [];
            }
        }
        function relativeTime(iso) {
            if (!iso) return '—';
            const diff = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
            if (diff < 1) return 'ahora';
            if (diff < 60) return `hace ${diff} min`;
            const h = Math.round(diff / 60);
            if (h < 24) return `hace ${h} h`;
            return `hace ${Math.round(h / 24)} d`;
        }
        function focusGeofenceEvent(e) {
            if (e?.lat == null || !gpsMap) return;
            gpsMap.setView([e.lat, e.lng], 15, { animate: true });
        }

        // Preferencias de alerta del usuario (Sub-fase 3.3)
        const showAlertModal = ref(false);
        const alertPrefs = reactive({
            loading: false, saving: false,
            active: false, all_geofences: true, geofence_ids: [],
            event_types: ['enter', 'exit'], channels: ['email'],
            geofences: [], user_email: '', user_phone: '',
        });
        async function openAlertPrefs() {
            showAlertModal.value = true;
            alertPrefs.loading = true;
            try {
                const { data } = await axios.get(`${props.baseUrl}/api/vehiculos/${vehicleId}/notification-preference`);
                alertPrefs.active = data?.active ?? false;
                alertPrefs.all_geofences = data?.all_geofences ?? true;
                alertPrefs.geofence_ids = data?.geofence_ids ?? [];
                alertPrefs.event_types = data?.event_types ?? ['enter', 'exit'];
                alertPrefs.channels = data?.channels ?? ['email'];
                alertPrefs.geofences = data?.geofences ?? [];
                alertPrefs.user_email = data?.user_email ?? '';
                alertPrefs.user_phone = data?.user_phone ?? '';
            } catch (e) {
                err(e, 'No se pudieron cargar tus preferencias.');
            } finally {
                alertPrefs.loading = false;
            }
        }
        async function saveAlertPrefs() {
            if (alertPrefs.active && !alertPrefs.event_types.length) { err(null, 'Selecciona al menos un tipo de evento.'); return; }
            if (alertPrefs.active && !alertPrefs.channels.length) { err(null, 'Selecciona al menos un canal.'); return; }
            alertPrefs.saving = true;
            try {
                await axios.post(`${props.baseUrl}/api/vehiculos/${vehicleId}/notification-preference`, {
                    active: alertPrefs.active,
                    all_geofences: alertPrefs.all_geofences,
                    geofence_ids: alertPrefs.geofence_ids,
                    event_types: alertPrefs.event_types,
                    channels: alertPrefs.channels,
                });
                ok('Preferencias guardadas.');
                showAlertModal.value = false;
            } catch (e) {
                err(e, 'No se pudieron guardar tus preferencias.');
            } finally {
                alertPrefs.saving = false;
            }
        }

        function setGpsRange(key) {
            gpsRange.value = key;
            if (key !== 'custom') loadGpsHistory();
        }

        function centerGps() {
            if (gpsPath && gpsMap) gpsMap.fitBounds(gpsPath.getBounds(), { padding: [30, 30], maxZoom: 16 });
            else if (gpsLast.value?.lat != null && gpsMap) gpsMap.setView([gpsLast.value.lat, gpsLast.value.lng], 15);
        }

        function downloadGpx() {
            if (!gpsHistory.value.length) return;
            const pts = gpsHistory.value
                .filter((p) => p.lat != null)
                .map((p) => `<trkpt lat="${p.lat}" lon="${p.lng}"><time>${p.recorded_at}</time><extensions><speed>${p.speed}</speed></extensions></trkpt>`)
                .join('\n');
            const gpx = `<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="MegaISP Flotas" xmlns="http://www.topografix.com/GPX/1/1">
<trk><name>${(vehicle.value?.display_name ?? 'vehiculo').replace(/"/g, '')}</name><trkseg>
${pts}
</trkseg></trk></gpx>`;
            const blob = new Blob([gpx], { type: 'application/gpx+xml' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `flota-${vehicleId}-${gpsRange.value}.gpx`;
            a.click();
            URL.revokeObjectURL(url);
        }

        function startGpsPolling() {
            stopGpsPolling();
            gpsTimer = setInterval(() => { if (!gpsPaused.value && activeTab.value === 'gps') loadGpsStatus(); }, 30000);
        }
        function stopGpsPolling() { if (gpsTimer) { clearInterval(gpsTimer); gpsTimer = null; } }
        function toggleGpsPause() { gpsPaused.value = !gpsPaused.value; if (!gpsPaused.value) loadGpsStatus(); }

        // Activación de dispositivo (crea fleet_device + has_gps=true)
        async function saveGps() {
            if (!gpsForm.imei?.trim()) { err(null, 'El IMEI es obligatorio.'); return; }
            savingGps.value = true;
            try {
                const { data } = await axios.post(`${props.baseUrl}/api/vehiculos/${vehicleId}/gps/device`, { ...gpsForm });
                showGpsForm.value = false;
                ok('Tracking GPS activado.');
                // Dispositivo físico → mostrar instrucciones para apuntarlo al listener TCP (Sub-fase 2.3a).
                if (data?.listener) {
                    gpsInstructions.value = { ...data.listener, imei: gpsForm.imei };
                }
                await loadVehicle();
                ensureGpsMap();
            } catch (e) { err(e, 'No se pudo activar el GPS.'); }
            finally { savingGps.value = false; }
        }
        const gpsInstructions = ref(null);

        // Inicializa el mapa cuando se entra a la pestaña GPS.
        watch(activeTab, (tab) => { if (tab === 'gps') ensureGpsMap(); });

        // Fotos
        const photoFile = ref(null);
        const pendingPhotoType = ref('general');
        function pickPhoto(type) { pendingPhotoType.value = type; photoFile.value.click(); }
        async function onPickPhoto(e) {
            const file = e.target.files[0]; e.target.value = '';
            if (!file) return;
            try {
                const fd = new FormData();
                fd.append('photo', file); fd.append('vehicle_id', vehicleId); fd.append('photo_type', pendingPhotoType.value);
                await axios.post(`${props.baseUrl}/api/fotos`, fd);
                ok('Foto subida.');
                await loadVehicle();
            } catch (er) { err(er, 'No se pudo subir la foto.'); }
        }
        async function deletePhoto(p) {
            if (!window.confirm('¿Eliminar esta foto?')) return;
            try { await axios.delete(`${props.baseUrl}/api/fotos/${p.id}`); ok('Foto eliminada.'); await loadVehicle(); }
            catch (e) { err(e, 'No se pudo eliminar la foto.'); }
        }

        // ── Carga de datos ────────────────────────────────────────────────
        async function loadVehicle() {
            const { data } = await axios.get(`${props.baseUrl}/api/vehiculos/${vehicleId}`);
            vehicle.value = data.vehicle;
        }
        async function loadAssignments() {
            const { data } = await axios.get(`${props.baseUrl}/api/vehiculos/${vehicleId}/asignaciones`).catch(() => ({ data: { assignments: [] } }));
            assignments.value = data?.assignments ?? [];
        }
        async function loadOperators() {
            const { data } = await axios.get(`${props.baseUrl}/api/vehiculos/data/operadores`).catch(() => ({ data: { users: [] } }));
            operators.value = data?.users ?? [];
        }
        async function loadProviders() {
            const { data } = await axios.get(`${props.baseUrl}/api/proveedores`).catch(() => ({ data: { providers: [] } }));
            providers.value = data?.providers ?? [];
        }

        onMounted(async () => {
            try {
                await loadVehicle();
                await Promise.all([loadAssignments(), loadOperators(), loadProviders()]);
                if (activeTab.value === 'gps') ensureGpsMap();
            } catch (e) {
                loadError.value = true;
            } finally {
                loading.value = false;
            }
        });

        onBeforeUnmount(() => { stopGpsPolling(); if (gpsMap) { gpsMap.remove(); gpsMap = null; } });

        return {
            // estado base
            vehicleId, baseUrl: props.baseUrl, loading, loadError, vehicle, operators, providers, assignments,
            activeTab, tabs, goTab, moreOpen, toast,
            // helpers
            fmtMoney, fmtKm, fmtDate, statusLabel, maintTypeLabel, maintIcon, docTypeLabels, docIcon, worksCatalog, vehicleIcon,
            // header / info
            currentAssignment, currentOperatorName, infoFields,
            editInfo, savingInfo, editForm, startEditInfo, cancelEditInfo, saveInfo,
            confirmDelete, executeDelete, showDeleteModal, deleting, deleteCounts,
            // salud
            nextServiceText, nextServiceBanner, docsAlertCount, docsAlertColorClass, maintYearCount, spendYear,
            // mantenimientos
            maintenances, maintSpendYear, maintAvg, maintSearch, maintTypeFilter, maintPeriodFilter, filteredMaintenances,
            showMaintForm, savingMaint, dragMaint, maintFiles, maintForm, maintTotalLive,
            onDropMaint, onPickMaint, saveMaint, deleteMaint,
            showProviderForm, newProviderName, creatingProvider, createProvider,
            // documentos
            docCounts, docsCriticalCount, docCostYear, docTypeFilter, docStatusFilter, filteredDocuments,
            docBadgeClass, docBadgeText, showDocForm, savingDoc, dragDoc, docFile, docForm, onDropDoc, onPickDoc, saveDoc, renewDoc,
            // combustible
            fuelComputed, showFuelForm, savingFuel, fuelForm, saveFuel, deleteFuel,
            // gps
            showGpsForm, savingGps, gpsForm, saveGps,
            gpsMapEl, gpsLast, gpsDevice, gpsStats, gpsHistory, gpsRange, gpsRanges, gpsCustom,
            gpsRefreshing, gpsPaused, gpsError, gpsLastSeen, gpsStatusColor, gpsStatusLabel,
            headingCardinal, setGpsRange, refreshGps, loadGpsHistory, centerGps, downloadGpx, toggleGpsPause,
            // geocercas (Sub-fase 3.2)
            geofenceEvents, relativeTime, focusGeofenceEvent,
            // alertas (Sub-fase 3.3)
            showAlertModal, alertPrefs, openAlertPrefs, saveAlertPrefs,
            // instrucciones GPS físico (Sub-fase 2.3a)
            gpsInstructions,
            // reglas activas (Sub-fase 3.4)
            activeRulesCount,
            // asignacion
            showAssignForm, savingAssign, assignForm, saveAssignment,
            // historial
            historyEvents,
            // fotos
            photoSlots, photosByType, extraPhotos, photoUrl, onImgError, zoom, zoomPhoto, pickPhoto, onPickPhoto, deletePhoto, photoFile,
        };
    },
};
</script>

<style scoped>
.flt-show-wrap { max-width: 1200px; margin: 0 auto; padding: 16px 0 90px; }

/* GPS Tracking */
.flt-gps-mapbox { position: relative; height: 420px; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
.flt-gps-map { width: 100%; height: 100%; }
.flt-gps-overlay { position: absolute; top: 10px; right: 10px; z-index: 500; display: flex; align-items: center; gap: 8px; }
.flt-gps-errbar { position: absolute; top: 10px; left: 50%; transform: translateX(-50%); z-index: 500; background: #fef2f2; color: #b91c1c; padding: 5px 12px; border-radius: 8px; font-size: .82rem; }
.flt-gps-ranges { display: flex; flex-wrap: wrap; align-items: center; }
.flt-gps-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px; margin-bottom: 12px; }
.flt-gps-card h6 { font-weight: 700; font-size: .9rem; margin-bottom: 10px; }
.flt-gps-row { display: flex; justify-content: space-between; align-items: center; padding: 4px 0; font-size: .88rem; }
.flt-gps-row span { color: #6b7280; }
.flt-geo-events .flt-geo-ev-list { display: flex; flex-direction: column; gap: 4px; max-height: 280px; overflow-y: auto; }
.flt-geo-ev { display: flex; align-items: center; gap: 10px; width: 100%; text-align: left; background: #fff; border: 1px solid #eef0f3; border-radius: 8px; padding: 8px 10px; font-size: .88rem; cursor: pointer; }
.flt-geo-ev:hover { background: #f8fafc; border-color: #cbd5e1; }
.flt-geo-ev.disabled { cursor: default; opacity: .7; }
.flt-geo-ev i { font-size: 1.05rem; flex-shrink: 0; }
.flt-geo-ev-time { flex-shrink: 0; white-space: nowrap; }
.spin { display: inline-block; animation: flt-spin 1s linear infinite; }
@keyframes flt-spin { to { transform: rotate(360deg); } }
.flt-center { text-align: center; }
.flt-section-title { font-size: 14px; font-weight: 700; color: #374151; }

/* Header */
.flt-header { display: flex; align-items: center; gap: 16px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 18px 20px; }
.flt-header-icon { width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 26px; color: #fff; background: #6b7280; flex-shrink: 0; }
.flt-header-icon.flt-status-active { background: #2563eb; }
.flt-header-icon.flt-status-in_workshop { background: #d97706; }
.flt-header-icon.flt-status-inactive { background: #6b7280; }
.flt-header-main { flex-grow: 1; min-width: 0; }
.flt-header-title { font-size: 19px; font-weight: 700; margin: 0 0 4px; }
.flt-badges { display: flex; gap: 6px; margin-bottom: 6px; flex-wrap: wrap; }
.flt-header-quick { display: flex; gap: 16px; flex-wrap: wrap; font-size: 13px; color: #6b7280; }
.flt-header-actions { flex-shrink: 0; }

.badge.flt-badge-active, .badge.flt-badge-gps { background: #16a34a; }
.badge.flt-badge-in_workshop { background: #d97706; }
.badge.flt-badge-inactive, .badge.flt-badge-nogps { background: #9ca3af; }
.badge.flt-badge-gps, .badge.flt-badge-nogps, .badge.flt-badge-active, .badge.flt-badge-in_workshop, .badge.flt-badge-inactive { color: #fff; font-weight: 600; }

/* Health cards */
.flt-health { margin-top: 16px; }
.flt-health-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px 16px; display: flex; align-items: center; gap: 12px; height: 100%; }
.flt-health-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.bg-soft-blue { background: #dbeafe; color: #2563eb; }
.bg-soft-green { background: #dcfce7; color: #16a34a; }
.bg-soft-amber { background: #fef3c7; color: #d97706; }
.flt-health-label { font-size: 12px; color: #6b7280; }
.flt-health-value { font-size: 18px; font-weight: 700; }

/* Tabs */
.flt-tabs .nav-link { color: #6b7280; font-weight: 600; font-size: 13px; cursor: pointer; }
.flt-tabs .nav-link.active { color: #2563eb; }
.flt-tab-badge { background: #ef4444; color: #fff; font-size: 10px; }
.flt-tab-body { background: #fff; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 12px 12px; padding: 22px; }

/* Info grid */
.flt-info-label { font-size: 12px; color: #6b7280; }
.flt-info-value { font-size: 14px; font-weight: 600; color: #1f2937; }
.flt-gps-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; }

/* Inline forms */
.flt-inline-form { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 18px; }

/* Banners */
.flt-banner { border-radius: 10px; padding: 12px 16px; font-size: 13px; font-weight: 600; display: flex; align-items: center; }
.flt-banner-blue { background: #dbeafe; color: #1e40af; }
.flt-banner-red { background: #fee2e2; color: #b91c1c; }

/* Mini cards */
.flt-mini-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px 14px; height: 100%; }
.flt-mini-label { font-size: 11px; color: #6b7280; }
.flt-mini-value { font-size: 17px; font-weight: 700; }
.flt-mini-red { background: #fef2f2; border-color: #fecaca; }
.flt-mini-amber { background: #fffbeb; border-color: #fde68a; }
.flt-mini-green { background: #f0fdf4; border-color: #bbf7d0; }

/* Lists */
.flt-list-item { display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f3f4f6; }
.flt-list-icon { width: 34px; height: 34px; border-radius: 8px; background: #f3f4f6; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }

/* Doc list */
.flt-doc-item { display: flex; align-items: flex-start; gap: 12px; padding: 14px; border-radius: 10px; margin-bottom: 10px; border: 1px solid #e5e7eb; background: #fff; }
.flt-doc-vencido { background: #fef2f2; border-color: #ef4444; }
.flt-doc-por_vencer { background: #fffbeb; border-color: #f59e0b; }
.flt-doc-vigente { background: #fff; }

/* Timeline */
.flt-timeline { position: relative; padding-left: 20px; }
.flt-timeline::before { content: ''; position: absolute; left: 5px; top: 4px; bottom: 4px; width: 2px; background: #e5e7eb; }
.flt-tl-item { position: relative; padding: 0 0 18px 16px; }
.flt-tl-dot { position: absolute; left: -19px; top: 4px; width: 12px; height: 12px; border-radius: 50%; border: 2px solid #fff; }
.flt-current-assign { display: flex; align-items: center; gap: 12px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px 16px; }
.flt-avatar { width: 42px; height: 42px; border-radius: 50%; background: #dbeafe; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 20px; }
.badge.flt-badge-active { background: #16a34a; color: #fff; }

/* Drop zone */
.flt-drop { border: 2px dashed #cbd5e1; border-radius: 10px; padding: 18px; text-align: center; color: #6b7280; font-size: 13px; cursor: pointer; transition: all .15s; }
.flt-drop:hover, .flt-drop-over { border-color: #2563eb; background: #eff6ff; color: #2563eb; }
.flt-file-list { list-style: none; padding: 0; margin: 0; font-size: 13px; }
.flt-file-list li { display: flex; align-items: center; padding: 3px 0; }
.flt-file-rm { margin-left: auto; cursor: pointer; color: #ef4444; }

/* Empty state */
.flt-empty { text-align: center; padding: 40px 20px; }
.flt-empty i { font-size: 48px; color: #cbd5e1; display: block; margin-bottom: 12px; }
.flt-empty h6 { font-weight: 700; }

/* Photos */
.flt-photo-slot { border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
.flt-photo-slot-head { background: #f9fafb; padding: 6px 10px; font-size: 12px; font-weight: 600; color: #6b7280; }
.flt-photo-thumb { position: relative; height: 120px; cursor: pointer; background: #f3f4f6; display: flex; align-items: center; justify-content: center; }
.flt-photo-thumb img { width: 100%; height: 100%; object-fit: cover; }
.flt-photo-thumb.flt-photo-broken::after { content: '\F8C9'; font-family: 'bootstrap-icons'; font-size: 28px; color: #cbd5e1; }
.flt-photo-del { position: absolute; top: 6px; right: 6px; border: none; background: rgba(0,0,0,.55); color: #fff; border-radius: 6px; width: 28px; height: 28px; }
.flt-photo-empty { height: 120px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; color: #9ca3af; cursor: pointer; background: #f9fafb; }
.flt-photo-empty i { font-size: 24px; }
.flt-zoom { position: fixed; inset: 0; background: rgba(0,0,0,.8); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 30px; }
.flt-zoom img { max-width: 90%; max-height: 90%; border-radius: 8px; }

/* Modal eliminar — sobre la barra fija (1000), debajo del toast (10001) */
.flt-delete-modal { z-index: 9999; }
.flt-delete-modal .modal-content { border: none; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,.2); }
.flt-delete-modal .modal-title { font-size: 1.05rem; font-weight: 700; }
.modal-backdrop.show { z-index: 9998; opacity: .5; }
.flt-delete-list { list-style: none; padding: 0; margin: 0; }
.flt-delete-list li { padding: 4px 0; font-size: .9rem; }

/* Fixed bar */
.flt-fixed-bar { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; border-top: 1px solid #e5e7eb; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; z-index: 1000; box-shadow: 0 -2px 10px rgba(0,0,0,.05); }

/* Toast */
.flt-toast { position: fixed; bottom: 80px; right: 28px; z-index: 10001; padding: 12px 20px; border-radius: 10px; font-size: 13px; font-weight: 600; box-shadow: 0 4px 16px rgba(0,0,0,.15); display: flex; align-items: center; color: #fff; }
.flt-toast-success { background: #16a34a; }
.flt-toast-error { background: #dc2626; }
.flt-toast-fade-enter-active, .flt-toast-fade-leave-active { transition: all .25s; }
.flt-toast-fade-enter-from, .flt-toast-fade-leave-to { opacity: 0; transform: translateY(12px); }

.dropdown-menu.show { display: block; }
</style>
