<template>
    <!-- Botón flotante de ayuda (estilo Splynx) -->
    <div class="help-float-btn" @click="toggle" :title="isOpen ? 'Cerrar ayuda' : 'Ayuda de esta pantalla'">
        <i :class="isOpen ? 'fas fa-times' : 'far fa-question-circle'"></i>
    </div>

    <transition name="help-slide">
        <div v-if="isOpen" class="help-panel">
            <div class="help-header">
                <div class="help-header-left">
                    <i class="far fa-life-ring help-header-icon"></i>
                    <div>
                        <div class="help-title">Ayuda</div>
                        <div class="help-subtitle">{{ help.label || 'Esta pantalla' }}</div>
                    </div>
                </div>
                <button class="help-btn-icon" @click="isOpen = false"><i class="fas fa-times"></i></button>
            </div>

            <div class="help-body">
                <div v-if="loading" class="help-loading">
                    <i class="fas fa-spinner fa-spin"></i> Cargando ayuda…
                </div>

                <template v-else-if="help.found">
                    <!-- Pasos -->
                    <section v-if="help.steps && help.steps.length" class="help-section">
                        <h6 class="help-section-title"><i class="fas fa-list-ol"></i> Cómo usar esta pantalla</h6>
                        <ol class="help-steps">
                            <li v-for="(step, i) in help.steps" :key="'s' + i">{{ step }}</li>
                        </ol>
                    </section>

                    <!-- Acciones -->
                    <section v-if="help.actions && help.actions.length" class="help-section">
                        <h6 class="help-section-title"><i class="fas fa-bolt"></i> Acciones disponibles</h6>
                        <div class="help-chips">
                            <span class="help-chip" v-for="(action, i) in help.actions" :key="'a' + i">{{ action }}</span>
                        </div>
                    </section>

                    <!-- Glosario -->
                    <section v-if="help.terms && help.terms.length" class="help-section">
                        <h6 class="help-section-title"><i class="fas fa-book"></i> Glosario</h6>
                        <div class="help-term" v-for="(t, i) in help.terms" :key="'t' + i">
                            <div class="help-term-name">{{ t.term }}</div>
                            <div class="help-term-def">{{ t.definition }}</div>
                        </div>
                    </section>
                </template>

                <div v-else class="help-empty">
                    <i class="far fa-compass help-empty-icon"></i>
                    <p>Aún no hay ayuda específica para esta pantalla.</p>
                    <p class="help-empty-sub">Consulta el manual general para más información.</p>
                </div>
            </div>

            <div class="help-footer">
                <a :href="manualUrl" class="help-manual-link">
                    <i class="fas fa-book-open"></i> Abrir manual completo
                </a>
            </div>
        </div>
    </transition>
</template>

<script>
import { ref } from "vue";

export default {
    name: "HelpFloat",
    props: { url: { type: String, default: "" } },
    setup(props) {
        const isOpen = ref(false);
        const loading = ref(false);
        const loaded = ref(false);
        const help = ref({ found: false });
        const manualUrl = ref((props.url || "") + "/manual");

        const fetchHelp = async () => {
            loading.value = true;
            try {
                const res = await axios.get(`${props.url}/api/manual/help`, {
                    params: { url: window.location.pathname },
                });
                help.value = res.data || { found: false };
                if (res.data && res.data.manual_url) {
                    manualUrl.value = (props.url || "") + res.data.manual_url;
                }
            } catch (e) {
                help.value = { found: false };
            } finally {
                loading.value = false;
                loaded.value = true;
            }
        };

        const toggle = () => {
            isOpen.value = !isOpen.value;
            if (isOpen.value && !loaded.value) {
                fetchHelp();
            }
        };

        return { isOpen, loading, help, manualUrl, toggle };
    },
};
</script>

<style scoped>
.help-float-btn {
    position: fixed;
    bottom: 24px;
    right: 90px;
    z-index: 9997;
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0ea5e9, #2563eb);
    box-shadow: 0 4px 18px rgba(37, 99, 235, 0.45);
    color: #fff;
    font-size: 22px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s;
}
.help-float-btn:hover { transform: scale(1.1); }

.help-panel {
    position: fixed;
    bottom: 90px;
    right: 24px;
    z-index: 9996;
    width: 380px;
    max-width: calc(100vw - 32px);
    height: 600px;
    max-height: calc(100vh - 120px);
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.18);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid rgba(37, 99, 235, 0.15);
}

.help-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    background: linear-gradient(135deg, #0ea5e9, #2563eb);
    color: #fff;
}
.help-header-left { display: flex; align-items: center; gap: 10px; }
.help-header-icon { font-size: 22px; }
.help-title { font-weight: 700; font-size: 15px; line-height: 1.1; }
.help-subtitle { font-size: 12px; opacity: 0.85; }
.help-btn-icon {
    background: rgba(255, 255, 255, 0.15);
    border: none;
    color: #fff;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    cursor: pointer;
}
.help-btn-icon:hover { background: rgba(255, 255, 255, 0.3); }

.help-body { flex: 1; overflow-y: auto; padding: 16px; }
.help-loading { text-align: center; color: #64748b; padding: 30px 0; }

.help-section { margin-bottom: 20px; }
.help-section-title {
    font-size: 13px;
    font-weight: 700;
    color: #2563eb;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 10px;
}
.help-section-title i { margin-right: 6px; }

.help-steps { margin: 0; padding-left: 20px; }
.help-steps li { margin-bottom: 8px; font-size: 13.5px; color: #334155; line-height: 1.45; }

.help-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.help-chip {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    border-radius: 14px;
    padding: 4px 11px;
    font-size: 12.5px;
}

.help-term { margin-bottom: 12px; }
.help-term-name { font-weight: 600; font-size: 13.5px; color: #0f172a; }
.help-term-def { font-size: 13px; color: #475569; line-height: 1.45; }

.help-empty { text-align: center; color: #64748b; padding: 36px 14px; }
.help-empty-icon { font-size: 36px; color: #cbd5e1; margin-bottom: 12px; display: block; }
.help-empty-sub { font-size: 12.5px; color: #94a3b8; }

.help-footer { border-top: 1px solid #e2e8f0; padding: 12px 16px; }
.help-manual-link {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: #f1f5f9;
    color: #1d4ed8;
    font-weight: 600;
    font-size: 13.5px;
    border-radius: 10px;
    padding: 9px;
    text-decoration: none;
}
.help-manual-link:hover { background: #e2e8f0; }

.help-slide-enter-active, .help-slide-leave-active { transition: all 0.22s ease; }
.help-slide-enter-from, .help-slide-leave-to { opacity: 0; transform: translateY(16px); }
</style>
