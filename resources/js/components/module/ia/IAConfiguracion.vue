<template>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-0">
                    <i class="bi bi-gear me-1"></i> Configuración de Inteligencia Artificial
                </h4>
                <small class="text-muted">Proveedores, modelos, prompts del sistema, tokens y webhooks.</small>
            </div>
        </div>

        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <button class="nav-link" :class="{ active: tab === 'proveedores' }" @click="tab = 'proveedores'">
                    <i class="bi bi-plug"></i> Proveedores y API Keys
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" :class="{ active: tab === 'modelos' }" @click="tab = 'modelos'">
                    <i class="bi bi-cpu"></i> Modelos disponibles
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" :class="{ active: tab === 'prompts' }" @click="tab = 'prompts'">
                    <i class="bi bi-chat-quote"></i> Prompts del sistema
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" :class="{ active: tab === 'memoria' }" @click="tab = 'memoria'">
                    <i class="bi bi-brain"></i> Memoria del proyecto
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" :class="{ active: tab === 'tokens' }" @click="tab = 'tokens'">
                    <i class="bi bi-coin"></i> Uso de tokens
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" :class="{ active: tab === 'webhooks' }" @click="tab = 'webhooks'">
                    <i class="bi bi-broadcast"></i> Webhooks
                </button>
            </li>
        </ul>

        <div v-if="tab === 'proveedores'">
            <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
                <small class="text-muted">
                    Configura los proveedores. Activa al menos uno con su API key para empezar a usar el asistente.
                </small>
                <div class="d-flex gap-2 flex-shrink-0">
                    <button class="btn btn-outline-info btn-sm" @click="mostrarManual = true">
                        <i class="bi bi-book"></i> Manual de implementación
                    </button>
                    <button class="btn btn-primary btn-sm" @click="abrirManager">
                        <i class="bi bi-pencil-square"></i> Administrar
                    </button>
                </div>
            </div>
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Estado</th>
                        <th>Nombre</th>
                        <th>Driver</th>
                        <th>Modelo</th>
                        <th>Imágenes</th>
                        <th>Activo</th>
                        <th>API key</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in proveedoresLocal" :key="p.id">
                        <td>
                            <span class="badge" :class="badgeEstado(p.estado)">{{ p.estado }}</span>
                        </td>
                        <td>{{ p.nombre }}</td>
                        <td><code>{{ p.driver }}</code></td>
                        <td><small>{{ p.modelo_default }}</small></td>
                        <td>{{ p.soporta_imagenes ? "Sí" : "No" }}</td>
                        <td>
                            <span :class="p.activo ? 'text-success' : 'text-muted'">
                                <i class="bi" :class="p.activo ? 'bi-check-circle-fill' : 'bi-circle'"></i>
                                {{ p.activo ? "Activo" : "Inactivo" }}
                            </span>
                        </td>
                        <td>
                            <span :class="p.tiene_api_key ? 'text-success' : 'text-danger'">
                                <i class="bi" :class="p.tiene_api_key ? 'bi-key-fill' : 'bi-x-circle'"></i>
                                {{ p.tiene_api_key ? "Configurada" : "Falta" }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else-if="tab === 'modelos'" class="text-muted">
            <p>El catálogo de modelos por proveedor se mostrará aquí. Próximamente.</p>
        </div>

        <div v-else-if="tab === 'prompts'" class="text-muted">
            <p>Edición de los prompts del sistema que se inyectan en cada conversación. Próximamente.</p>
        </div>

        <div v-else-if="tab === 'memoria'">
            <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
                <small class="text-muted">
                    Hechos, decisiones y pendientes extraídos automáticamente de las conversaciones. Se inyectan como contexto en cada nuevo chat.
                </small>
                <div class="d-flex gap-2 flex-shrink-0">
                    <button class="btn btn-outline-secondary btn-sm" @click="memoriaForm.abierto = true">
                        <i class="bi bi-plus-lg"></i> Agregar manualmente
                    </button>
                    <button class="btn btn-outline-warning btn-sm" @click="ejecutarLimpiarObsoletos" :disabled="memoriaLoading">
                        <i class="bi bi-magic"></i> Detectar contradicciones (IA)
                    </button>
                    <button class="btn btn-outline-danger btn-sm" @click="ejecutarLimpiarAntiguos" :disabled="memoriaLoading">
                        <i class="bi bi-trash"></i> Limpiar antiguos (>90 días)
                    </button>
                </div>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-md-4">
                    <input type="text" v-model="memoriaBusqueda" class="form-control form-control-sm" placeholder="Buscar en contenido o módulo..." />
                </div>
                <div class="col-md-3">
                    <select v-model="memoriaFiltroTipo" class="form-select form-select-sm">
                        <option value="">Todos los tipos</option>
                        <option v-for="t in memoriaTipos" :key="t.id" :value="t.id">{{ t.label }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select v-model="memoriaFiltroEstado" class="form-select form-select-sm">
                        <option value="vigentes">Sólo vigentes</option>
                        <option value="todos">Todos (incluye obsoletos)</option>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-sm btn-outline-primary" @click="cargarMemoria" :disabled="memoriaLoading">
                        <i class="bi bi-arrow-clockwise"></i> Recargar
                    </button>
                </div>
            </div>

            <div v-if="memoriaForm.abierto" class="card mb-3">
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label small">Tipo</label>
                            <select v-model="memoriaForm.tipo" class="form-select form-select-sm">
                                <option v-for="t in memoriaTipos" :key="t.id" :value="t.id">{{ t.label }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Módulo</label>
                            <input type="text" v-model="memoriaForm.modulo_relacionado" class="form-control form-control-sm" placeholder="(opcional) IA, Clientes, ..." />
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Relevancia (1-10)</label>
                            <input type="number" min="1" max="10" v-model.number="memoriaForm.relevancia" class="form-control form-control-sm" />
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small">Contenido</label>
                            <textarea v-model="memoriaForm.contenido" rows="2" maxlength="500" class="form-control form-control-sm" placeholder="Hecho en una línea (max 500 chars)"></textarea>
                        </div>
                        <div class="col-md-12 d-flex justify-content-end gap-2">
                            <button class="btn btn-sm btn-secondary" @click="cerrarFormMemoria">Cancelar</button>
                            <button class="btn btn-sm btn-primary" @click="guardarMemoria" :disabled="!memoriaForm.contenido">
                                {{ memoriaForm.id ? "Actualizar" : "Crear" }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="memoriaLoading" class="text-center py-3 text-muted">
                <div class="spinner-border spinner-border-sm me-1"></div> Cargando memoria...
            </div>
            <div v-else-if="memoriaFiltrada.length === 0" class="text-center py-4 text-muted">
                <i class="bi bi-brain"></i> Sin hechos guardados. Conversa con el asistente y la memoria se irá poblando automáticamente.
            </div>
            <table v-else class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th style="width: 110px;">Tipo</th>
                        <th style="width: 120px;">Módulo</th>
                        <th>Contenido</th>
                        <th style="width: 70px;" class="text-center">Rel.</th>
                        <th style="width: 100px;">Fecha</th>
                        <th style="width: 80px;" class="text-center">Estado</th>
                        <th style="width: 110px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="m in memoriaFiltrada" :key="m.id" :class="{ 'text-muted': m.obsoleto }">
                        <td>
                            <span class="badge" :class="badgeTipoMemoria(m.tipo)">{{ etiquetaTipo(m.tipo) }}</span>
                        </td>
                        <td>
                            <small v-if="m.modulo_relacionado"><code>{{ m.modulo_relacionado }}</code></small>
                            <small v-else class="text-muted">—</small>
                        </td>
                        <td><small :class="{ 'text-decoration-line-through': m.obsoleto }">{{ m.contenido }}</small></td>
                        <td class="text-center"><small>{{ m.relevancia }}</small></td>
                        <td><small>{{ formatearFecha(m.updated_at) }}</small></td>
                        <td class="text-center">
                            <span v-if="m.obsoleto" class="badge bg-secondary">Obsoleto</span>
                            <span v-else class="badge bg-success">Vigente</span>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-link btn-sm p-0 me-1" @click="editarMemoria(m)" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-link btn-sm p-0 me-1" @click="toggleObsoleto(m)" :title="m.obsoleto ? 'Marcar vigente' : 'Marcar obsoleto'">
                                <i class="bi" :class="m.obsoleto ? 'bi-arrow-counterclockwise' : 'bi-archive'"></i>
                            </button>
                            <button class="btn btn-link btn-sm p-0 text-danger" @click="borrarMemoria(m)" title="Eliminar">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else-if="tab === 'tokens'">
            <div class="row g-2 mb-3">
                <div class="col-md-3">
                    <label class="form-label small">Desde</label>
                    <input type="date" v-model="tokensRango.desde" class="form-control form-control-sm" />
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Hasta</label>
                    <input type="date" v-model="tokensRango.hasta" class="form-control form-control-sm" />
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-primary btn-sm" @click="cargarUsoTokens" :disabled="tokensLoading">
                        <i class="bi bi-arrow-clockwise"></i> Actualizar
                    </button>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <div class="card text-bg-light">
                        <div class="card-body">
                            <small class="text-muted">Tokens totales</small>
                            <h4 class="mb-0">{{ formatNumber(tokensResumen.tokens_total) }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-bg-light">
                        <div class="card-body">
                            <small class="text-muted">Costo estimado USD</small>
                            <h4 class="mb-0">${{ (tokensResumen.costo_estimado || 0).toFixed(4) }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-bg-light">
                        <div class="card-body">
                            <small class="text-muted">Input / Output</small>
                            <h4 class="mb-0">
                                <small>{{ formatNumber(tokensResumen.tokens_input) }} / {{ formatNumber(tokensResumen.tokens_output) }}</small>
                            </h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-bg-light">
                        <div class="card-body">
                            <small class="text-muted">Llamadas</small>
                            <h4 class="mb-0">{{ formatNumber(tokensResumen.llamadas) }}</h4>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="tokensLoading" class="text-center py-4 text-muted">
                <div class="spinner-border spinner-border-sm me-1"></div> Cargando uso...
            </div>
            <div v-else-if="tokensSerie.length === 0" class="text-center py-4 text-muted">
                <i class="bi bi-bar-chart"></i> Sin consumo registrado en el rango.
            </div>
            <table v-else class="table table-sm">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Proveedor</th>
                        <th class="text-end">Tokens</th>
                        <th class="text-end">Costo USD</th>
                        <th class="text-end">Llamadas</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(r, i) in tokensSerie" :key="i">
                        <td>{{ r.fecha }}</td>
                        <td><code>{{ r.proveedor }}</code></td>
                        <td class="text-end">{{ formatNumber(r.tokens) }}</td>
                        <td class="text-end">${{ r.costo.toFixed(4) }}</td>
                        <td class="text-end">{{ r.llamadas }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else-if="tab === 'webhooks'" class="text-muted">
            <p>Integraciones por webhook hacia otros sistemas. Próximamente.</p>
        </div>

        <IAProveedoresManager
            v-if="managerAbierto"
            :proveedores="proveedoresLocal"
            @close="cerrarManager"
            @refresh="recargar"
        />

        <div v-if="mostrarManual" class="ia-manual-backdrop" @click.self="mostrarManual = false">
            <div class="ia-manual-dialog">
                <header class="ia-manual-head">
                    <div>
                        <h5 class="mb-0"><i class="bi bi-book"></i> Manual de implementación de proveedores IA</h5>
                        <small class="text-muted">10 proveedores soportados — actualizado mayo 2026</small>
                    </div>
                    <button class="btn btn-sm btn-light" @click="mostrarManual = false" aria-label="Cerrar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </header>

                <div class="ia-manual-filters">
                    <div class="row g-2">
                        <div class="col-md-7">
                            <input type="text" v-model="manualBusqueda" class="form-control form-control-sm"
                                   placeholder="Buscar proveedor o modelo..." />
                        </div>
                        <div class="col-md-5">
                            <ul class="nav nav-pills nav-fill">
                                <li class="nav-item" v-for="t in manualTabs" :key="t.id">
                                    <button class="nav-link py-1 px-2" :class="{ active: manualTab === t.id }"
                                            @click="manualTab = t.id" style="font-size:0.82em;">
                                        {{ t.label }}
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="ia-manual-body">
                    <div v-if="proveedoresManualFiltrados.length === 0" class="text-center text-muted py-4">
                        <i class="bi bi-search"></i> Sin coincidencias.
                    </div>
                    <div v-else class="ia-manual-grid">
                        <article v-for="p in proveedoresManualFiltrados" :key="p.id" class="ia-manual-card"
                                 :style="{ borderTopColor: p.color }">
                            <header class="ia-manual-card-head">
                                <h6 class="mb-0">
                                    <i class="bi" :class="p.icono" :style="{ color: p.color }"></i>
                                    {{ p.nombre }}
                                </h6>
                                <span class="badge" :class="badgeManualClass(p.badge)">{{ p.badge }}</span>
                            </header>
                            <small class="text-muted d-block mb-2">{{ p.empresa }} — {{ p.categoria }}</small>

                            <strong class="small d-block mt-2">Pasos para obtener API Key</strong>
                            <ol class="ia-manual-pasos">
                                <li v-for="(paso, i) in p.pasos" :key="i">{{ paso }}</li>
                            </ol>

                            <strong class="small d-block">Modelos disponibles</strong>
                            <div class="ia-manual-models">
                                <code v-for="m in p.modelos" :key="m">{{ m }}</code>
                            </div>

                            <strong class="small d-block">Endpoint</strong>
                            <div class="ia-manual-endpoint">
                                <code>{{ p.endpoint }}</code>
                                <button class="btn btn-sm btn-link p-0" @click="copiarEndpoint(p.endpoint, p.id)"
                                        :title="endpointCopiado === p.id ? 'Copiado' : 'Copiar'">
                                    <i class="bi" :class="endpointCopiado === p.id ? 'bi-check2' : 'bi-clipboard'"></i>
                                </button>
                            </div>

                            <strong class="small d-block">Formato de API Key</strong>
                            <code class="d-block ia-manual-keyformat">{{ p.keyFormato }}</code>

                            <strong class="small d-block">Nivel gratuito</strong>
                            <small class="d-block text-muted">{{ p.gratis }}</small>

                            <div v-if="p.notas" class="alert alert-info py-1 px-2 mb-0 mt-2">
                                <small><i class="bi bi-info-circle"></i> {{ p.notas }}</small>
                            </div>

                            <footer class="ia-manual-card-foot">
                                <a :href="p.url" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-box-arrow-up-right"></i> Ir al sitio
                                </a>
                            </footer>
                        </article>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, watch } from "vue";
import axios from "axios";
import Swal from "sweetalert2";
import IAProveedoresManager from "./IAProveedoresManager.vue";

const proveedoresManual = [
    {
        id: "claude", nombre: "Claude", empresa: "Anthropic",
        color: "#cc785c", icono: "bi-chat-square-text",
        badge: "Freemium", categoria: "Comercial",
        url: "https://console.anthropic.com",
        pasos: [
            "Crear cuenta en console.anthropic.com",
            "Ir a la sección 'API Keys'",
            "Click en 'Create Key'",
            "Copiar la key generada (sólo visible una vez)",
        ],
        modelos: ["claude-sonnet-4-5", "claude-opus-4", "claude-haiku-4-5"],
        endpoint: "https://api.anthropic.com/v1/messages",
        keyFormato: "sk-ant-api03-...",
        gratis: "$5 USD de crédito inicial",
        notas: "",
    },
    {
        id: "openai", nombre: "OpenAI", empresa: "OpenAI",
        color: "#10a37f", icono: "bi-stars",
        badge: "Freemium", categoria: "Comercial",
        url: "https://platform.openai.com",
        pasos: [
            "Crear cuenta en platform.openai.com",
            "Ir a 'API Keys'",
            "Click en 'Create new secret key'",
            "Copiar la key (sólo visible una vez)",
        ],
        modelos: ["gpt-4o", "gpt-4o-mini", "gpt-4.1", "o3-mini"],
        endpoint: "https://api.openai.com/v1/chat/completions",
        keyFormato: "sk-proj-...",
        gratis: "$5 USD de crédito inicial (expira)",
        notas: "",
    },
    {
        id: "gemini", nombre: "Gemini", empresa: "Google",
        color: "#4285f4", icono: "bi-google",
        badge: "Gratuito", categoria: "Comercial",
        url: "https://aistudio.google.com",
        pasos: [
            "Ir a aistudio.google.com",
            "Iniciar sesión con cuenta Google",
            "Click en 'Get API Key' → 'Create API Key'",
            "Copiar la key generada",
        ],
        modelos: ["gemini-2.5-pro", "gemini-2.0-flash", "gemini-2.5-flash"],
        endpoint: "https://generativelanguage.googleapis.com/v1beta/openai/",
        keyFormato: "AIza...",
        gratis: "Free tier con límites generosos (60 req/min)",
        notas: "",
    },
    {
        id: "groq", nombre: "Groq", empresa: "Groq",
        color: "#f55036", icono: "bi-lightning-charge",
        badge: "Gratuito", categoria: "Comercial",
        url: "https://console.groq.com",
        pasos: [
            "Crear cuenta en console.groq.com",
            "Ir a 'API Keys'",
            "Click en 'Create API Key'",
            "Copiar la key generada",
        ],
        modelos: ["llama-3.3-70b-versatile", "mixtral-8x7b", "gemma2-9b-it"],
        endpoint: "https://api.groq.com/openai/v1",
        keyFormato: "gsk_...",
        gratis: "Sí, con límites de tokens/min",
        notas: "Compatible con formato OpenAI",
    },
    {
        id: "mistral", nombre: "Mistral", empresa: "Mistral AI",
        color: "#ff7000", icono: "bi-wind",
        badge: "Freemium", categoria: "Comercial",
        url: "https://console.mistral.ai",
        pasos: [
            "Crear cuenta en console.mistral.ai",
            "Verificar método de pago (requerido)",
            "Ir a 'API Keys' → 'Create new key'",
            "Copiar la key",
        ],
        modelos: ["mistral-large-latest", "mistral-small-latest", "codestral-latest"],
        endpoint: "https://api.mistral.ai/v1",
        keyFormato: "32 chars alfanumérico",
        gratis: "Prueba gratuita limitada",
        notas: "",
    },
    {
        id: "deepseek", nombre: "DeepSeek", empresa: "DeepSeek",
        color: "#1a73e8", icono: "bi-water",
        badge: "Freemium", categoria: "Comercial",
        url: "https://platform.deepseek.com",
        pasos: [
            "Crear cuenta en platform.deepseek.com",
            "Ir a 'API Keys'",
            "Click en 'Create Key'",
            "Copiar la key generada",
        ],
        modelos: ["deepseek-chat", "deepseek-reasoner"],
        endpoint: "https://api.deepseek.com/v1",
        keyFormato: "sk-...",
        gratis: "Crédito inicial, muy económico",
        notas: "Compatible con formato OpenAI",
    },
    {
        id: "xai", nombre: "Grok", empresa: "xAI",
        color: "#000000", icono: "bi-twitter-x",
        badge: "Freemium", categoria: "Comercial",
        url: "https://console.x.ai",
        pasos: [
            "Crear cuenta en console.x.ai",
            "Ir a 'API Keys'",
            "Click en 'Create API Key'",
            "Copiar la key generada",
        ],
        modelos: ["grok-3", "grok-3-mini", "grok-2-vision"],
        endpoint: "https://api.x.ai/v1",
        keyFormato: "xai-...",
        gratis: "Crédito mensual gratuito",
        notas: "",
    },
    {
        id: "ollama", nombre: "Ollama", empresa: "Ollama (Local)",
        color: "#6b7280", icono: "bi-house-gear",
        badge: "Gratuito", categoria: "Local",
        url: "https://ollama.com",
        pasos: [
            "Descargar Ollama desde ollama.com",
            "Instalar en el servidor o máquina local",
            "Correr: ollama pull llama3.2",
            "Apuntar el endpoint a http://localhost:11434/v1",
        ],
        modelos: ["llama3.2", "qwen2.5", "phi4", "mistral"],
        endpoint: "http://localhost:11434/v1",
        keyFormato: "(no requiere — usar cualquier texto)",
        gratis: "100% gratuito, corre localmente",
        notas: "Compatible con formato OpenAI",
    },
    {
        id: "together", nombre: "Together", empresa: "Together AI",
        color: "#8b5cf6", icono: "bi-people",
        badge: "Freemium", categoria: "Comercial",
        url: "https://api.together.xyz",
        pasos: [
            "Crear cuenta en api.together.xyz",
            "Ir a 'Settings' → 'API Keys'",
            "Click en 'Create new API Key'",
            "Copiar la key",
        ],
        modelos: ["meta-llama/Llama-3.3-70B", "Qwen/Qwen2.5-72B"],
        endpoint: "https://api.together.xyz/v1",
        keyFormato: "64 chars",
        gratis: "$1 USD crédito inicial",
        notas: "",
    },
    {
        id: "cohere", nombre: "Cohere", empresa: "Cohere",
        color: "#9333ea", icono: "bi-diagram-3",
        badge: "Freemium", categoria: "Comercial",
        url: "https://dashboard.cohere.com",
        pasos: [
            "Crear cuenta en dashboard.cohere.com",
            "Ir a 'API Keys'",
            "Click en 'Create Trial Key'",
            "Copiar la key generada",
        ],
        modelos: ["command-r-plus-08-2024", "command-r-08-2024"],
        endpoint: "https://api.cohere.ai/v1",
        keyFormato: "40 chars alfanumérico",
        gratis: "Trial key gratuita con límites",
        notas: "",
    },
];

export default {
    name: "IAConfiguracion",
    components: { IAProveedoresManager },
    props: {
        proveedoresIniciales: { type: Array, default: () => [] },
    },
    setup(props) {
        const tab = ref("proveedores");
        const proveedoresLocal = ref(props.proveedoresIniciales);
        const managerAbierto = ref(false);

        const hoyISO = new Date().toISOString().slice(0, 10);
        const haceTreintaISO = new Date(Date.now() - 30 * 24 * 3600 * 1000).toISOString().slice(0, 10);
        const tokensRango = ref({ desde: haceTreintaISO, hasta: hoyISO });
        const tokensResumen = ref({ tokens_input: 0, tokens_output: 0, tokens_total: 0, costo_estimado: 0, llamadas: 0 });
        const tokensSerie = ref([]);
        const tokensLoading = ref(false);

        const abrirManager = () => (managerAbierto.value = true);
        const cerrarManager = () => (managerAbierto.value = false);

        const recargar = async () => {
            const { data } = await axios.get("/ia/proveedores");
            if (data.data) proveedoresLocal.value = data.data;
        };

        const cargarUsoTokens = async () => {
            tokensLoading.value = true;
            try {
                const { data } = await axios.get("/ia/configuracion/uso-tokens", {
                    params: { desde: tokensRango.value.desde, hasta: tokensRango.value.hasta },
                });
                if (data.success) {
                    tokensResumen.value = data.resumen;
                    tokensSerie.value = data.serie;
                }
            } catch (e) {
                console.error("Error cargando uso tokens", e);
            } finally {
                tokensLoading.value = false;
            }
        };

        watch(tab, (nuevo) => {
            if (nuevo === "tokens") cargarUsoTokens();
        });

        const badgeEstado = (estado) => {
            return {
                conectado: "bg-success",
                error: "bg-danger",
                sin_configurar: "bg-secondary",
            }[estado] || "bg-light text-dark";
        };

        const formatNumber = (n) => (n || 0).toLocaleString("es-MX");

        // ── Manual de implementación de proveedores ─────────────────────────
        const mostrarManual = ref(false);
        const manualBusqueda = ref("");
        const manualTab = ref("todos");
        const manualTabs = [
            { id: "todos", label: "Todos" },
            { id: "Comercial", label: "Comercial" },
            { id: "Gratuito", label: "Gratuito" },
            { id: "Local", label: "Local" },
        ];
        const endpointCopiado = ref(null);

        const proveedoresManualFiltrados = computed(() => {
            const q = manualBusqueda.value.toLowerCase().trim();
            return proveedoresManual.filter((p) => {
                if (manualTab.value !== "todos" && p.categoria !== manualTab.value) return false;
                if (!q) return true;
                return p.nombre.toLowerCase().includes(q)
                    || p.empresa.toLowerCase().includes(q)
                    || p.modelos.some((m) => m.toLowerCase().includes(q));
            });
        });

        const badgeManualClass = (badge) => ({
            "Gratuito": "bg-success",
            "Freemium": "bg-warning text-dark",
            "De pago": "bg-secondary",
        })[badge] || "bg-light text-dark";

        const copiarEndpoint = async (url, id) => {
            try {
                await navigator.clipboard.writeText(url);
                endpointCopiado.value = id;
                setTimeout(() => {
                    if (endpointCopiado.value === id) endpointCopiado.value = null;
                }, 1500);
            } catch (e) {
                // navigator.clipboard puede no estar disponible en http://; ignoramos
            }
        };

        // ── Memoria del proyecto ───────────────────────────────────────────
        const memoria = ref([]);
        const memoriaLoading = ref(false);
        const memoriaBusqueda = ref("");
        const memoriaFiltroTipo = ref("");
        const memoriaFiltroEstado = ref("vigentes");
        const memoriaTipos = [
            { id: "hecho", label: "Hecho", color: "bg-info text-dark" },
            { id: "avance", label: "Avance", color: "bg-success" },
            { id: "decision", label: "Decisión", color: "bg-primary" },
            { id: "pendiente", label: "Pendiente", color: "bg-warning text-dark" },
            { id: "error_resuelto", label: "Error resuelto", color: "bg-secondary" },
        ];
        const memoriaForm = ref({
            abierto: false,
            id: null,
            tipo: "hecho",
            contenido: "",
            modulo_relacionado: "",
            relevancia: 5,
        });

        const cargarMemoria = async () => {
            memoriaLoading.value = true;
            try {
                const { data } = await axios.get("/ia/memoria", {
                    params: { solo_vigentes: memoriaFiltroEstado.value === "vigentes" ? 1 : 0 },
                });
                memoria.value = data.data || [];
            } catch (e) {
                Swal.fire("Error", "No se pudo cargar la memoria", "error");
            } finally {
                memoriaLoading.value = false;
            }
        };

        const memoriaFiltrada = computed(() => {
            const q = memoriaBusqueda.value.toLowerCase().trim();
            return memoria.value.filter((m) => {
                if (memoriaFiltroTipo.value && m.tipo !== memoriaFiltroTipo.value) return false;
                if (!q) return true;
                return (m.contenido || "").toLowerCase().includes(q)
                    || (m.modulo_relacionado || "").toLowerCase().includes(q);
            });
        });

        const etiquetaTipo = (tipo) => (memoriaTipos.find((t) => t.id === tipo)?.label) || tipo;
        const badgeTipoMemoria = (tipo) => (memoriaTipos.find((t) => t.id === tipo)?.color) || "bg-light text-dark";

        const formatearFecha = (iso) => {
            if (!iso) return "";
            const d = new Date(iso);
            return d.toLocaleDateString("es-MX", { year: "numeric", month: "2-digit", day: "2-digit" });
        };

        const cerrarFormMemoria = () => {
            memoriaForm.value = { abierto: false, id: null, tipo: "hecho", contenido: "", modulo_relacionado: "", relevancia: 5 };
        };

        const editarMemoria = (m) => {
            memoriaForm.value = {
                abierto: true,
                id: m.id,
                tipo: m.tipo,
                contenido: m.contenido,
                modulo_relacionado: m.modulo_relacionado || "",
                relevancia: m.relevancia,
            };
        };

        const guardarMemoria = async () => {
            const payload = {
                tipo: memoriaForm.value.tipo,
                contenido: memoriaForm.value.contenido,
                modulo_relacionado: memoriaForm.value.modulo_relacionado || null,
                relevancia: memoriaForm.value.relevancia,
            };
            try {
                if (memoriaForm.value.id) {
                    await axios.post(`/ia/memoria/update/${memoriaForm.value.id}`, payload);
                } else {
                    await axios.post("/ia/memoria/store", payload);
                }
                cerrarFormMemoria();
                await cargarMemoria();
            } catch (e) {
                Swal.fire("Error", e.response?.data?.message || "No se pudo guardar", "error");
            }
        };

        const toggleObsoleto = async (m) => {
            try {
                await axios.post(`/ia/memoria/update/${m.id}`, { obsoleto: !m.obsoleto });
                await cargarMemoria();
            } catch (e) {
                Swal.fire("Error", "No se pudo actualizar", "error");
            }
        };

        const borrarMemoria = async (m) => {
            const r = await Swal.fire({
                title: "¿Eliminar hecho?",
                text: m.contenido,
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Eliminar",
                cancelButtonText: "Cancelar",
            });
            if (!r.isConfirmed) return;
            try {
                await axios.delete(`/ia/memoria/destroy/${m.id}`);
                await cargarMemoria();
            } catch (e) {
                Swal.fire("Error", "No se pudo eliminar", "error");
            }
        };

        const ejecutarLimpiarAntiguos = async () => {
            const r = await Swal.fire({
                title: "¿Limpiar obsoletos antiguos?",
                text: "Borra registros marcados obsoletos creados hace más de 90 días.",
                icon: "question",
                showCancelButton: true,
                confirmButtonText: "Limpiar",
                cancelButtonText: "Cancelar",
            });
            if (!r.isConfirmed) return;
            memoriaLoading.value = true;
            try {
                const { data } = await axios.post("/ia/memoria/limpiar-antiguos", { dias: 90 });
                Swal.fire("Listo", `Eliminados ${data.eliminados} registros`, "success");
                await cargarMemoria();
            } catch (e) {
                Swal.fire("Error", "No se pudo limpiar", "error");
            } finally {
                memoriaLoading.value = false;
            }
        };

        const ejecutarLimpiarObsoletos = async () => {
            const r = await Swal.fire({
                title: "¿Detectar contradicciones con IA?",
                text: "Se enviará la lista de hechos al proveedor IA activo. Esto consume tokens.",
                icon: "question",
                showCancelButton: true,
                confirmButtonText: "Ejecutar",
                cancelButtonText: "Cancelar",
            });
            if (!r.isConfirmed) return;
            memoriaLoading.value = true;
            try {
                const { data } = await axios.post("/ia/memoria/limpiar-obsoletos");
                Swal.fire("Listo", `${data.marcados_obsoletos} hechos marcados como obsoletos`, "success");
                await cargarMemoria();
            } catch (e) {
                Swal.fire("Error", "No se pudo ejecutar", "error");
            } finally {
                memoriaLoading.value = false;
            }
        };

        watch(tab, (nuevo) => {
            if (nuevo === "memoria" && memoria.value.length === 0) cargarMemoria();
        });
        watch(memoriaFiltroEstado, () => cargarMemoria());

        return {
            tab,
            proveedoresLocal,
            managerAbierto,
            abrirManager,
            cerrarManager,
            recargar,
            badgeEstado,
            tokensRango,
            tokensResumen,
            tokensSerie,
            tokensLoading,
            cargarUsoTokens,
            formatNumber,
            mostrarManual,
            manualBusqueda,
            manualTab,
            manualTabs,
            endpointCopiado,
            proveedoresManualFiltrados,
            badgeManualClass,
            copiarEndpoint,
            memoria,
            memoriaLoading,
            memoriaBusqueda,
            memoriaFiltroTipo,
            memoriaFiltroEstado,
            memoriaTipos,
            memoriaForm,
            memoriaFiltrada,
            cargarMemoria,
            etiquetaTipo,
            badgeTipoMemoria,
            formatearFecha,
            cerrarFormMemoria,
            editarMemoria,
            guardarMemoria,
            toggleObsoleto,
            borrarMemoria,
            ejecutarLimpiarAntiguos,
            ejecutarLimpiarObsoletos,
        };
    },
};
</script>

<style scoped>
.ia-manual-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    z-index: 1050;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 5vh 1rem;
    overflow-y: auto;
}
.ia-manual-dialog {
    width: 100%;
    max-width: 900px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    display: flex;
    flex-direction: column;
    max-height: 90vh;
    overflow: hidden;
}
.ia-manual-head {
    padding: 14px 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}
.ia-manual-filters {
    padding: 12px 20px;
    border-bottom: 1px solid #e5e7eb;
    flex-shrink: 0;
}
.ia-manual-body {
    padding: 16px 20px;
    overflow-y: auto;
    flex: 1;
}
.ia-manual-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 16px;
}
.ia-manual-card {
    border: 1px solid #e5e7eb;
    border-top: 3px solid #888;
    border-radius: 6px;
    padding: 12px 14px;
    background: #fafafa;
    display: flex;
    flex-direction: column;
}
.ia-manual-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2px;
}
.ia-manual-pasos {
    font-size: 0.85em;
    padding-left: 1.2em;
    margin: 4px 0 8px;
}
.ia-manual-pasos li { margin: 2px 0; }
.ia-manual-models {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-bottom: 8px;
}
.ia-manual-models code {
    background: rgba(0, 0, 0, 0.06);
    padding: 1px 6px;
    border-radius: 3px;
    font-size: 0.78em;
    color: inherit;
}
.ia-manual-endpoint {
    display: flex;
    align-items: center;
    gap: 6px;
    background: rgba(0, 0, 0, 0.04);
    padding: 4px 8px;
    border-radius: 4px;
    margin-bottom: 8px;
}
.ia-manual-endpoint code {
    flex: 1;
    background: transparent;
    font-size: 0.8em;
    word-break: break-all;
    color: inherit;
}
.ia-manual-keyformat {
    font-size: 0.8em;
    color: #6b7280;
    margin-bottom: 8px;
    background: rgba(0, 0, 0, 0.04);
    padding: 2px 6px;
    border-radius: 3px;
}
.ia-manual-card-foot {
    margin-top: auto;
    padding-top: 8px;
}

/* Dark mode */
body[data-layout-mode="dark"] .ia-manual-dialog {
    background: #25282d;
    color: #e5e7eb;
}
body[data-layout-mode="dark"] .ia-manual-head,
body[data-layout-mode="dark"] .ia-manual-filters {
    border-bottom-color: #3a3d44;
}
body[data-layout-mode="dark"] .ia-manual-card {
    background: #2d3036;
    border-color: #3a3d44;
}
body[data-layout-mode="dark"] .ia-manual-models code,
body[data-layout-mode="dark"] .ia-manual-endpoint,
body[data-layout-mode="dark"] .ia-manual-keyformat {
    background: rgba(255, 255, 255, 0.06);
    color: #e5e7eb;
}
</style>
