<template>
    <div class="admin-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Administración</h4>
            <small class="text-muted">{{ cards.length }} secciones instaladas</small>
        </div>

        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
        </div>

        <div v-else-if="cards.length === 0" class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="fa fa-th-large fa-3x mb-3 d-block opacity-25"></i>
                No hay módulos con tarjetas de administración instalados.
            </div>
        </div>

        <div v-else class="row">
            <div
                v-for="card in cards"
                :key="card._module + '_' + card.title"
                class="col-xl-3 col-lg-4 col-md-6 mb-4"
            >
                <a :href="card.url || '#'" class="text-decoration-none">
                    <div class="card h-100 admin-card">
                        <div class="card-body d-flex align-items-start gap-3 p-4">
                            <div class="admin-card-icon">
                                <i :class="'fa fa-fw fa-' + (card.icon || 'cube')"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <h6 class="card-title mb-1 text-truncate">{{ card.title }}</h6>
                                <p class="card-text text-muted small mb-0" style="line-clamp: 2; -webkit-line-clamp: 2; overflow: hidden; display: -webkit-box; -webkit-box-orient: vertical;">
                                    {{ card.description }}
                                </p>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-top-0 pt-0 pb-3 px-4">
                            <small class="text-muted">
                                <i class="fa fa-puzzle-piece me-1"></i>{{ card._module }}
                            </small>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'AdminPanel',
    props: {
        csrfToken: { type: String, default: '' },
    },
    data() {
        return {
            cards: [],
            loading: true,
        };
    },
    mounted() {
        this.loadCards();
    },
    methods: {
        async loadCards() {
            try {
                const { data } = await axios.get('/admin/administracion/cards');
                this.cards = data.cards || [];
            } catch (e) {
                console.error('AdminPanel: error cargando tarjetas', e);
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>

<style scoped>
.admin-card {
    transition: box-shadow 0.15s, transform 0.15s;
    border: 1px solid rgba(0,0,0,.08);
}
.admin-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,.12);
    transform: translateY(-2px);
}
.admin-card-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: var(--bs-primary, #556ee6);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
</style>
