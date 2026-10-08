<?php

namespace App\Modules\Addons\Talento\Support;

use App\Models\Seller;
use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Core\Clientes\Models\Client;

/**
 * Actor — punto único de verdad de "¿quién es este colaborador?" para el Portal de Colaborador.
 *
 * Inmutable, resuelto UNA vez por request desde auth()->user(); cada faceta se memoiza (un query c/u).
 * Agnóstico del guard (web o Sanctum) → la APK arma el mismo Actor. Cada faceta devuelve null si no
 * aplica; el sidebar usa esos null para ocultar secciones. (Mismo rol que PayWeek para la ventana.)
 *
 * Regla de negocio: todo colaborador NACE vendedor; el esquema vendedor/embajador es EXCLUYENTE.
 * El campo de "esquema activo" aún no existe → hoy seller() aplica por default y embajador() es inerte
 * (null para todos). La conversión de esquema (y ese campo) se construye en la Fase E.
 */
final class Actor
{
    private bool $talentoLoaded = false;
    private ?TalentoColaborador $talento = null;

    private bool $sellerLoaded = false;
    private ?Seller $seller = null;

    private function __construct(private readonly User $user)
    {
    }

    public static function for(User $user): self
    {
        return new self($user);
    }

    public function user(): User
    {
        return $this->user;
    }

    /** Talento: colaborador ACTIVO por user_id. Molde generalizado — los Bloques 1-2 cuelgan de acá. */
    public function talento(): ?TalentoColaborador
    {
        if (! $this->talentoLoaded) {
            $this->talento = TalentoColaborador::with('user')
                ->where('user_id', $this->user->id)
                ->where('status', 'active')
                ->first();
            $this->talentoLoaded = true;
        }

        return $this->talento;
    }

    /** Vendedor: fila sellers por user_id (todo colaborador nace vendedor por default). */
    public function seller(): ?Seller
    {
        if (! $this->sellerLoaded) {
            $this->seller = Seller::where('user_id', $this->user->id)->first();
            $this->sellerLoaded = true;
        }

        return $this->seller;
    }

    /**
     * Embajador: INERTE por ahora → devuelve null para todos.
     *
     * El esquema vendedor/embajador es excluyente y todo colaborador nace VENDEDOR; el campo de
     * "esquema activo" aún no existe. En la Fase E, cuando exista, esta faceta resolverá el Client por
     * client_id (Client.user_id → fallback login_user → client_main_information.user → client_id) y lo
     * devolverá SOLO si el esquema activo del colaborador es embajador.
     */
    public function embajador(): ?Client
    {
        return null;
    }

    /** Custodia: anclada en el COLABORADOR de Talento (user_id → inventario), NO en Vendedores. */
    public function custodia(): ?TalentoColaborador
    {
        return $this->talento();
    }

    /**
     * "Vista principal" del colaborador cuando tiene varios roles a la vez —
     * decisión de Irving (llamada 2026-10-08): un técnico que TAMBIÉN carga
     * el rol Vendedor (caso real hoy en producción, no hipotético) sigue
     * siendo técnico para efectos de qué pantalla/métricas le corresponden,
     * no vendedor. Misma prioridad que ya usa
     * TalentoColaboradorSyncService::PRIORITY para resolver el department —
     * aquí solo se traduce a una etiqueta de "track" en vez de un department.
     *
     * Puramente informativa: no reemplaza ni oculta seller()/talento()/
     * embajador() — un frontend futuro la usa para decidir qué métricas
     * mostrar como protagonistas, sin tener que rederivar roles.
     */
    public function trackPrincipal(): string
    {
        $roles = $this->user->getRoleNames();

        if ($roles->contains('TECNICO_PLANTA')) {
            return 'tecnico_planta_interna';
        }
        if ($roles->contains('TECNICO_INSTALADOR') || $roles->contains('TECNICO')) {
            return 'tecnico_planta_externa';
        }
        if ($roles->contains('Mostrador')) {
            return 'mostrador';
        }
        if ($roles->contains('Vendedor')) {
            return 'vendedor';
        }

        return 'generico';
    }

    /**
     * Es técnico (interna o externa) para efectos del sidebar/dashboard del
     * Portal — deriva de trackPrincipal() en vez de repetir la lista de
     * roles, para que ambas nunca puedan desincronizarse entre sí.
     */
    public function esTecnico(): bool
    {
        return str_starts_with($this->trackPrincipal(), 'tecnico_');
    }

    /**
     * Secciones del sidebar del portal para ESTE colaborador. Cada entrada se incluye solo si
     * (a) está CONSTRUIDA (flag 'built') y (b) APLICA según la faceta. Faceta null → oculta.
     * Crece por fase: al construir una sección se pone built=true y se cablea su tab en app.js.
     * Estructura de retorno: lista ordenada de items {key, group, label, icon, tab}.
     */
    public function sections(): array
    {
        $esColaborador = fn (): bool => (bool) $this->talento();

        // key           grupo          label             icono           tab            aplica                                                built (fase)
        $catalog = [
            ['mi_dia',     'Mi Talento',  'Mi día',         'today',        'inicio',      $esColaborador,                                       true],   // Bloques 1-2
            ['mi_dinero',  'Mi Talento',  'Mi dinero',      'payments',     'dinero',      $esColaborador,                                       true],   // Bloque 2
            ['material',   'Mi trabajo',  'Mi material',    'inventory_2',  'material',    fn () => (bool) $this->custodia(),                     true],   // Fase A (A2 activada)
            ['calidad',    'Mi trabajo',  'Mi calidad',     'verified',     'calidad',     fn () => $this->esTecnico(),                           true],   // Fase 1 del dashboard por rol (2026-10-08)
            ['prospectos', 'Mi trabajo',  'Mis prospectos', 'groups',       'prospectos',  fn () => (bool) $this->seller(),                       true],   // Fase C
            ['panel',      'Mi trabajo',  'Mi panel',       'insights',     'panel',       fn () => (bool) ($this->seller() || $this->embajador()), false], // Fases D/E
            ['documentos', 'Cuenta',      'Mis documentos', 'description',  'documentos',  fn () => $this->user->can('talento.documentos.ver-propios'), true], // Item #9990814
            ['perfil',     'Cuenta',      'Perfil',         'person',       'perfil',      fn (): bool => true,                                  true],
        ];

        $out = [];
        foreach ($catalog as [$key, $group, $label, $icon, $tab, $aplica, $built]) {
            if ($built && $aplica()) {
                $out[] = ['key' => $key, 'group' => $group, 'label' => $label, 'icon' => $icon, 'tab' => $tab];
            }
        }

        return $out;
    }
}
