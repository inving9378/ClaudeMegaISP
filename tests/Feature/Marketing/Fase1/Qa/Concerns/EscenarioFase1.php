<?php

namespace Tests\Feature\Marketing\Fase1\Qa\Concerns;

use App\Models\Marketing\Attribution;
use App\Models\Marketing\Campaign;
use App\Models\Marketing\Channel;
use App\Models\Marketing\Conversation;
use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\Lead;
use App\Models\Marketing\LeadForm;
use App\Models\Marketing\LeadSource;
use App\Models\Marketing\MultivariantCampaign;
use App\Models\Marketing\PilotCampaign;
use App\Models\Marketing\Publication;
use App\Models\Marketing\PublicationChannel;
use App\Models\User;
use App\Modules\Addons\Marketing\Models\Empresa;
use App\Modules\Addons\Marketing\Support\EmpresaActiva;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Escenario común de QA · Marketing Fase 1 (contrato docs/marketing/fase-1-paquetes.md §5).
 *
 * DIFERENCIA CLAVE con el escenario de la Fase 0 (§2.1, riesgo R6): aquí NO se siembra ninguno de los
 * 13 permisos que crea la migración 2026_11_13_100000. Si la migración no los creó, `givePermissionTo`
 * lanza PermissionDoesNotExist y la prueba falla — que es justo lo que la Fase 0 tapó.
 *
 * Lo único que se siembra es el catálogo de permisos que en dev/prod SÍ existe pero `megaisp_test` no
 * trae (lo crea `database/seeders/Marketing/Phase4PermissionsSeeder` y compañía, no una migración):
 * verificado el 2026-11-13 contra `megaisp` (solo lectura) — ver PERMISOS_DE_SEEDER. Nunca se mezclan
 * con los 13 (lo comprueba PermisosFaltantesTest).
 *
 * - Empresa A = la principal (id 1, Meganet). Empresa B = nueva (id autoincremental, nunca se asume 2).
 * - adminA / adminB: rol de sistema super-administrator (recibe los 13 por la migración) + permisos de seeder.
 * - marketingA / ventasA: SIN roles de sistema; solo permisos Spatie directos (aditivo) + su rol de empresa.
 * - sinEmpresa: sin roles, sin permisos, sin asignación.
 * - inactivo: super-administrator + admin en A, pero `estado = 'inactivo'`.
 */
trait EscenarioFase1
{
    /** Los 13 permisos de la migración de Fase 1 (§2.7). Las pruebas NUNCA los crean. */
    protected static array $permisosDeLaMigracion = [
        'marketing.leads.view', 'marketing.leads.create', 'marketing.leads.update', 'marketing.leads.delete',
        'marketing.leads.assign', 'marketing.leads.score',
        'marketing.forms.view', 'marketing.forms.create', 'marketing.forms.update', 'marketing.forms.delete',
        'marketing.brand_kit.configure', 'marketing.conversations.view', 'marketing.campaigns.view',
    ];

    /** Existen en dev/prod (seeders de Marketing), faltan en megaisp_test. Verificado en `megaisp` el 13-nov. */
    protected static array $permisosDeSeeder = [
        'view-video-templates', 'generate-video-content', 'view-video-content', 'delete-video-content',
        'view-marketing-campaigns', 'create-marketing-campaigns', 'delete-marketing-campaigns',
        'regenerate-marketing-variants', 'manage-marketing-niches', 'test-voices', 'manage-integrations',
    ];

    protected Empresa $empresaA;
    protected Empresa $empresaB;
    protected User $adminA;
    protected User $marketingA;
    protected User $ventasA;
    protected User $adminB;
    protected User $sinEmpresa;
    protected User $inactivo;
    protected LeadSource $fuente;
    protected Channel $canal;

    protected function montarEscenarioFase1(): void
    {
        $this->contexto()->limpiar();

        $existentes = Permission::query()->where('guard_name', 'web')->pluck('name')->all();
        foreach (array_diff(self::$permisosDeSeeder, $existentes) as $p) {
            Permission::query()->create(['name' => $p, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->empresaA = Empresa::query()->findOrFail(Empresa::PRINCIPAL_ID);
        $this->empresaB = $this->crearEmpresa('QA1 Empresa B');

        $this->adminA = $this->crearUsuario('qa1_admin_a', [$this->empresaA->id => 'admin'], ['super-administrator'], self::$permisosDeSeeder);
        $this->adminB = $this->crearUsuario('qa1_admin_b', [$this->empresaB->id => 'admin'], ['super-administrator'], self::$permisosDeSeeder);
        $this->marketingA = $this->crearUsuario('qa1_mkt_a', [$this->empresaA->id => 'marketing'], [], array_merge(
            ['marketing.leads.view', 'marketing.leads.create', 'marketing.leads.update', 'marketing.forms.view',
             'marketing.forms.create', 'marketing.forms.update', 'marketing.conversations.view'],
            self::$permisosDeSeeder
        ));
        $this->ventasA = $this->crearUsuario('qa1_ventas_a', [$this->empresaA->id => 'ventas'], [],
            ['marketing.leads.view', 'marketing.leads.create', 'marketing.leads.update', 'marketing.conversations.view']);
        $this->sinEmpresa = $this->crearUsuario('qa1_sin_empresa', [], [], []);
        $this->inactivo = $this->crearUsuario('qa1_inactivo', [$this->empresaA->id => 'admin'], ['super-administrator'], self::$permisosDeSeeder, ['estado' => 'inactivo']);

        $this->fuente = LeadSource::query()->firstOrCreate(['company_id' => 1, 'code' => 'qa_fase1'], ['name' => 'QA Fase 1']);
        $this->canal = Channel::query()->firstOrCreate(
            ['company_id' => 1, 'code' => 'whatsapp'],
            ['name' => 'WhatsApp Business', 'active' => true, 'config' => []]
        );
    }

    protected function contexto(): EmpresaActiva
    {
        return app(EmpresaActiva::class);
    }

    /** Petición nueva (como en producción): limpia el contexto y la sesión y entra como $u. */
    protected function como(User $u, ?int $empresaId = null): static
    {
        $this->contexto()->limpiar();
        $this->flushSession();
        $this->actingAs($u);
        if ($empresaId !== null) {
            $this->withSession(['marketing.empresa_activa_id' => $empresaId]);
        }

        return $this;
    }

    /** Petición nueva SIN sesión (alguien de fuera escribiendo la URL). */
    protected function anonimo(): static
    {
        $this->contexto()->limpiar();
        $this->flushSession();
        app('auth')->forgetGuards();

        return $this;
    }

    protected function crearEmpresa(string $nombre, bool $activo = true): Empresa
    {
        $id = DB::table('marketing_empresas')->insertGetId([
            'nombre' => $nombre . ' ' . Str::upper(Str::random(5)), 'slug' => 'qa1-' . Str::lower(Str::random(12)),
            'color_primario' => '#1F3A93', 'color_secundario' => null, 'activo' => $activo ? 1 : 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Empresa::query()->findOrFail($id);
    }

    /**
     * @param array<int,string> $asignaciones company_id => rol
     * @param array<int,string> $roles roles de sistema (aditivo)
     * @param array<int,string> $permisos permisos Spatie directos que YA existen (aditivo)
     */
    protected function crearUsuario(string $prefijo, array $asignaciones, array $roles, array $permisos, array $extra = []): User
    {
        $u = User::factory()->create(array_merge([
            'name' => 'QA1 ' . $prefijo, 'father_last_name' => 'PRUEBA',
            'login_user' => $prefijo . '_' . Str::lower(Str::random(8)), 'estado' => 'activo', 'is_system' => 0,
        ], $extra));
        foreach ($roles as $r) {
            $u->assignRole($r);
        }
        if ($permisos) {
            $u->givePermissionTo($permisos);
        }
        foreach ($asignaciones as $companyId => $rol) {
            $this->asignar($u, $companyId, $rol);
        }

        return $u->fresh();
    }

    protected function asignar(User $u, int $companyId, string $rol, bool $activo = true): void
    {
        DB::table('marketing_empresa_usuario')->updateOrInsert(
            ['company_id' => $companyId, 'user_id' => $u->id],
            ['rol' => $rol, 'activo' => $activo ? 1 : 0, 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    /** Crea un modelo saltando $fillable y SIN contexto de empresa (company_id explícito). */
    protected function crear(string $clase, array $attrs, array $opcionales = []): Model
    {
        $this->contexto()->limpiar();
        /** @var Model $m */
        $m = new $clase();
        foreach ($opcionales as $col) {
            if (! Schema::hasColumn($m->getTable(), $col)) {
                unset($attrs[$col]);
            }
        }
        $m->forceFill($attrs);
        $m->save();

        return $m;
    }

    protected function lead(int $companyId, string $status, $capturado, string $nombre = 'Lead QA1'): Lead
    {
        return $this->crear(Lead::class, [
            'company_id' => $companyId, 'source_id' => $this->fuente->id, 'full_name' => $nombre,
            'phone' => '55' . random_int(10000000, 99999999), 'status' => $status, 'captured_at' => $capturado,
        ]);
    }

    protected function campana(int $companyId, array $attrs = []): Campaign
    {
        return $this->crear(Campaign::class, array_merge([
            'company_id' => $companyId, 'name' => 'Campaña QA1 ' . Str::random(6), 'slug' => 'qa1-' . Str::lower(Str::random(12)),
            'type' => 'meta_ads', 'status' => 'active', 'currency' => 'MXN', 'budget' => '0.00', 'spent' => '0.00',
            'start_date' => $this->hoy()->startOfMonth()->toDateString(), 'end_date' => null,
        ], $attrs));
    }

    protected function atribucion(Lead $lead, Campaign $c, string $touch = 'first_touch'): Attribution
    {
        return $this->crear(Attribution::class, [
            'lead_id' => $lead->id, 'campaign_id' => $c->id, 'touchpoint' => $touch, 'weight' => '1.000', 'occurred_at' => now(),
        ]);
    }

    /** "Hoy" en la zona del contrato (America/Mexico_City). */
    protected function hoy(): CarbonImmutable
    {
        return CarbonImmutable::now('America/Mexico_City');
    }

    /** Datos de negocio mínimos de una empresa (para A/B). @return array<string, Model|string> */
    protected function sembrarNegocio(int $companyId, string $etiqueta): array
    {
        $m = 'QA1-' . $etiqueta . '-' . Str::upper(Str::random(6));
        $d = ['marca' => $m];
        $d['lead'] = $this->lead($companyId, 'new', now(), "Lead {$m}");
        $d['conversacion'] = $this->crear(Conversation::class, [
            'company_id' => $companyId, 'lead_id' => $d['lead']->id, 'channel_id' => $this->canal->id,
            'external_thread_id' => Str::random(12) . '@s.whatsapp.net', 'ai_handled' => false,
            'status' => 'open', 'unread_count' => 1, 'last_message_at' => now(),
        ]);
        $d['campana'] = $this->campana($companyId, ['name' => "Campaña {$m}", 'status' => 'draft']);
        $d['contenido'] = $this->crear(GeneratedContent::class, [
            'company_id' => $companyId, 'type' => 'video', 'generation_engine' => 'qa', 'status' => 'ready', 'title' => "Video {$m}",
        ], ['title']);
        $d['formulario'] = $this->crear(LeadForm::class, [
            'company_id' => $companyId, 'slug' => Str::lower('qa1-form-' . Str::random(10)), 'name' => "Form {$m}",
            'fields_schema' => [['key' => 'full_name', 'label' => 'Nombre', 'type' => 'text', 'required' => true]],
            'assign_to_source_id' => $this->fuente->id, 'active' => true, 'auto_scoring' => false,
        ]);
        $d['multivariante'] = $this->crear(MultivariantCampaign::class, [
            'company_id' => $companyId, 'name' => "Multi {$m}", 'campaign_type' => 'multivariant_plan_promo',
            'status' => 'ready', 'input_data' => ['plan_name' => "Plan {$m}"],
        ]);
        $d['piloto'] = $this->crear(PilotCampaign::class, [
            'company_id' => $companyId, 'name' => "Piloto {$m}", 'status' => 'draft',
            'variant_a_subject' => 'A', 'variant_a_body' => 'a', 'variant_b_subject' => 'B', 'variant_b_body' => 'b',
        ]);
        $d['canalPublicacion'] = $this->crear(PublicationChannel::class, [
            'company_id' => $companyId, 'platform' => 'facebook', 'channel_type' => 'page', 'name' => "Pagina {$m}",
            'slug' => Str::lower('qa1-pc-' . Str::random(12)), 'supported_aspect_ratios' => ['1:1'], 'active' => true,
        ]);
        $d['publicacion'] = $this->crear(Publication::class, [
            'company_id' => $companyId, 'channel_id' => $this->canal->id, 'pub_channel_id' => $d['canalPublicacion']->id,
            'content_id' => $d['contenido']->id, 'scheduled_at' => now(), 'status' => 'published', 'published_at' => now(),
            'caption' => "Post {$m}",
        ], ['caption']);

        return $d;
    }

    // ── Rutas del módulo, recorridas desde el router ─────────────────────────

    /**
     * Toda ruta que pertenece al módulo Marketing: por URI, por acción en el namespace del módulo o por
     * closure definido dentro de app/Modules/Addons/Marketing. No es una lista a mano: una ruta nueva entra sola.
     *
     * @return array<int, \Illuminate\Routing\Route>
     */
    protected function rutasDelModulo(): array
    {
        $out = [];
        foreach (app('router')->getRoutes()->getRoutes() as $r) {
            $porUri = (bool) preg_match('#^(api/)?marketing(/|$)|^webhooks/marketing|^public/marketing#', $r->uri());
            $porAccion = str_starts_with(ltrim($r->getActionName(), '\\'), 'App\\Modules\\Addons\\Marketing\\');
            $porClosure = false;
            $uses = $r->getAction('uses');
            if ($uses instanceof \Closure) {
                $archivo = (string) (new \ReflectionFunction($uses))->getFileName();
                $porClosure = str_contains($archivo, '/app/Modules/Addons/Marketing/');
            }
            if ($porUri || $porAccion || $porClosure) {
                $out[] = $r;
            }
        }

        return $out;
    }

    protected function metodosDe(\Illuminate\Routing\Route $r): array
    {
        return array_values(array_diff($r->methods(), ['HEAD']));
    }

    protected function urlDe(\Illuminate\Routing\Route $r): string
    {
        return '/' . preg_replace(
            ['/\{token\}/', '/\{slug\}/', '/\{niche\}/', '/\{[a-zA-Z_]+\??\}/'],
            ['abc123', 'qa-slug', 'gamer', '987654'],
            $r->uri()
        );
    }

    protected function esAutenticada(array $mw): bool
    {
        foreach ($mw as $m) {
            if ($m === 'auth' || str_starts_with($m, 'auth:')) {
                return true;
            }
        }

        return false;
    }
}
