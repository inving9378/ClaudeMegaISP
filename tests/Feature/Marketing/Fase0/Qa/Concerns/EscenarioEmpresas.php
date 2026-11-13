<?php

namespace Tests\Feature\Marketing\Fase0\Qa\Concerns;

use App\Models\Marketing\Campaign;
use App\Models\Marketing\Channel;
use App\Models\Marketing\Conversation;
use App\Models\Marketing\ContentTemplate;
use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\Lead;
use App\Models\Marketing\LeadActivity;
use App\Models\Marketing\LeadForm;
use App\Models\Marketing\LeadSource;
use App\Models\Marketing\Message;
use App\Models\Marketing\MultivariantCampaign;
use App\Models\Marketing\PilotCampaign;
use App\Models\Marketing\PilotCampaignSend;
use App\Models\Marketing\Pipeline;
use App\Models\Marketing\Publication;
use App\Models\Marketing\PublicationChannel;
use App\Models\User;
use App\Modules\Addons\Marketing\Models\Empresa;
use App\Modules\Addons\Marketing\Support\EmpresaActiva;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Escenario común de QA · Marketing Fase 0 (contrato docs/marketing/fase-0-paquetes.md §5).
 *
 * - Empresa A = la principal (id 1, «Meganet», sembrada por la migración 100000).
 * - Empresa B = una empresa nueva creada aquí (id autoincremental: NO se asume id 2, porque el
 *   script de vista previa de §2.10 puede haber dejado una empresa 2 fuera de transacción).
 * - Usuarios: admin/marketing/ventas de A, admin de B, uno sin empresa y un admin de A SIN el
 *   permiso Spatie `marketing.empresas.gestionar`.
 * - Datos de cada modelo estricto de §2.4 en A y en B, creados SIN contexto y con `company_id`
 *   explícito (§2.9: sin contexto el trait no lo pisa).
 *
 * Todo vive dentro de la transacción de MarketingTestCase (rollback al terminar).
 *
 * Nota de arnés: en pruebas HTTP el contenedor NO se reinicia entre peticiones, así que un
 * `scoped()` (EmpresaActiva) sobrevive de una petición a la siguiente dentro de la misma prueba
 * (en producción cada petición arranca limpia). `como()` hace `limpiar()` sobre la MISMA instancia
 * antes de cada petición. No se usa `forgetScopedInstances()`: Laravel cachea el controlador en la
 * ruta y un controlador que recibió EmpresaActiva por constructor se quedaría con la instancia vieja
 * (artefacto del arnés, ver reporte QA). Las verificaciones de "la fila quedó intacta" se hacen con
 * `DB::table` (nunca lleva scopes).
 */
trait EscenarioEmpresas
{
    protected Empresa $empresaA;
    protected Empresa $empresaB;

    protected User $adminA;
    protected User $marketingA;
    protected User $ventasA;
    protected User $adminB;
    protected User $sinEmpresa;
    protected User $adminASinPermiso;

    /** @var array<string, Model> datos de la empresa A por clave corta */
    protected array $a = [];

    /** @var array<string, Model> datos de la empresa B por clave corta */
    protected array $b = [];

    protected LeadSource $fuente;
    protected Channel $canal;

    /** Permisos Spatie que piden hoy las rutas/controladores de Marketing (ver route:list de la base). */
    protected static array $permisosSpatieMarketing = [
        'marketing.brand_kit.configure', // preexistente roto (§0.8): no existe en la base; se crea aquí para probar la capa por empresa
        'view-video-content', 'generate-video-content', 'delete-video-content',
        'marketing.forms.view', 'marketing.forms.create', 'marketing.forms.update', 'marketing.forms.delete',
        'marketing.leads.view', 'marketing.leads.create', 'marketing.leads.update', 'marketing.leads.delete',
        'marketing.leads.assign', 'marketing.leads.score',
        'view-marketing-campaigns', 'create-marketing-campaigns', 'delete-marketing-campaigns',
        'regenerate-marketing-variants', 'manage-marketing-niches', 'test-voices', 'view-video-templates',
        'marketing.conversation.send', 'marketing.conversation.toggle-ai',
        'marketing.conversation.assign', 'marketing.conversation.close',
    ];

    // ── Montaje ──────────────────────────────────────────────────────────────

    protected function montarEscenario(bool $conDatos = true): void
    {
        $this->contexto()->limpiar();

        $faltan = array_diff(
            array_merge(self::$permisosSpatieMarketing, ['marketing.empresas.gestionar']),
            Permission::query()->where('guard_name', 'web')->pluck('name')->all()
        );
        foreach ($faltan as $p) {
            Permission::query()->create(['name' => $p, 'guard_name' => 'web']);
        }
        // Una sola vez: el catálogo cacheado de Spatie se recarga completo en cada forget (caro).
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->empresaA = Empresa::query()->findOrFail(Empresa::PRINCIPAL_ID);
        $this->empresaB = $this->crearEmpresa('QA Empresa B');

        $this->adminA     = $this->crearUsuario('qa_admin_a', [$this->empresaA->id => 'admin']);
        $this->marketingA = $this->crearUsuario('qa_mkt_a', [$this->empresaA->id => 'marketing']);
        $this->ventasA    = $this->crearUsuario('qa_ventas_a', [$this->empresaA->id => 'ventas']);
        $this->adminB     = $this->crearUsuario('qa_admin_b', [$this->empresaB->id => 'admin']);
        $this->sinEmpresa = $this->crearUsuario('qa_sin_empresa', []);
        $this->adminASinPermiso = $this->crearUsuario('qa_admin_a_sinperm', [$this->empresaA->id => 'admin'], superAdmin: false, conGestionar: false);

        $this->fuente = LeadSource::query()->firstOrCreate(
            ['company_id' => 1, 'code' => 'qa_fase0'],
            ['name' => 'QA Fase 0']
        );
        $this->canal = Channel::query()->firstOrCreate(
            ['company_id' => 1, 'code' => 'whatsapp'],
            ['name' => 'WhatsApp Business', 'active' => true, 'config' => []]
        );

        if ($conDatos) {
            $this->a = $this->sembrarDatos($this->empresaA->id, 'A');
            $this->b = $this->sembrarDatos($this->empresaB->id, 'B');
        }
    }

    protected function contexto(): EmpresaActiva
    {
        return app(EmpresaActiva::class);
    }

    /** Simula una petición nueva (como en producción) y entra como $u, opcionalmente con empresa elegida. */
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

    protected function crearEmpresa(string $nombre, bool $activo = true, ?string $color = '#1F3A93'): Empresa
    {
        $id = DB::table('marketing_empresas')->insertGetId([
            'nombre'           => $nombre . ' ' . Str::upper(Str::random(5)),
            'slug'             => 'qa-' . Str::lower(Str::random(12)),
            'color_primario'   => $color,
            'color_secundario' => null,
            'activo'           => $activo ? 1 : 0,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return Empresa::query()->findOrFail($id);
    }

    /**
     * @param array<int, string> $asignaciones company_id => rol
     */
    protected function crearUsuario(
        string $prefijo,
        array $asignaciones,
        bool $superAdmin = true,
        bool $permisosMarketing = true,
        bool $conGestionar = true,
        array $extra = []
    ): User {
        $u = User::factory()->create(array_merge([
            'name'             => 'QA ' . $prefijo,
            'father_last_name' => 'PRUEBA',
            'login_user'       => $prefijo . '_' . Str::lower(Str::random(8)),
            'estado'           => 'activo',
            'is_system'        => 0,
        ], $extra));

        // super-administrator: solo para pasar check_route_permission de las pantallas viejas (§2.10.2).
        if ($superAdmin) {
            $u->assignRole('super-administrator');
        }
        if ($permisosMarketing) {
            $u->givePermissionTo(self::$permisosSpatieMarketing);
        }
        // Con super-administrator hereda marketing.empresas.gestionar por rol (migración 100200).
        // Sin él, solo lo tiene si se pide explícito (aditivo, nunca sync).
        if (! $superAdmin && $conGestionar) {
            $u->givePermissionTo('marketing.empresas.gestionar');
        }

        foreach ($asignaciones as $companyId => $rol) {
            $this->asignar($u, $companyId, $rol);
        }

        return $u->fresh();
    }

    protected function asignar(User $u, int $companyId, string $rol, bool $activo = true, ?int $createdBy = 0): void
    {
        DB::table('marketing_empresa_usuario')->updateOrInsert(
            ['company_id' => $companyId, 'user_id' => $u->id],
            [
                'rol'        => $rol,
                'activo'     => $activo ? 1 : 0,
                'created_by' => $createdBy === 0 ? $u->id : $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /** Fila tal cual en BD (sin scopes). */
    protected function fila(string $tabla, int $id): ?array
    {
        $r = DB::table($tabla)->where('id', $id)->first();

        return $r ? (array) $r : null;
    }

    // ── Datos de negocio por empresa ─────────────────────────────────────────

    /**
     * Crea una fila de cada modelo estricto (§2.4) + hijos, SIN contexto, con company_id explícito.
     *
     * @return array<string, Model>
     */
    protected function sembrarDatos(int $companyId, string $etiqueta): array
    {
        $this->contexto()->limpiar();
        $m = 'QA-' . $etiqueta . '-' . Str::upper(Str::random(6)); // marca única para buscar en respuestas
        $d = ['marca' => $m];

        $d['lead'] = $this->crear(Lead::class, [
            'company_id' => $companyId, 'source_id' => $this->fuente->id, 'full_name' => "Lead {$m}",
            'phone' => '55' . random_int(10000000, 99999999), 'status' => 'new', 'captured_at' => now(),
        ]);
        $d['actividad'] = $this->crear(LeadActivity::class, [
            'lead_id' => $d['lead']->id, 'type' => 'note', 'description' => "Nota {$m}", 'happened_at' => now(),
        ]);
        $d['conversacion'] = $this->crear(Conversation::class, [
            'company_id' => $companyId, 'lead_id' => $d['lead']->id, 'channel_id' => $this->canal->id,
            'external_thread_id' => Str::random(12) . '@s.whatsapp.net', 'ai_handled' => false,
            'status' => 'open', 'unread_count' => 2, 'last_message_at' => now(),
        ]);
        $d['mensaje'] = $this->crear(Message::class, [
            'conversation_id' => $d['conversacion']->id, 'direction' => 'inbound', 'content' => "Hola {$m}",
            'content_type' => 'text', 'sender' => 'lead', 'sent_at' => now(),
        ]);
        $d['campana'] = $this->crear(Campaign::class, [
            'company_id' => $companyId, 'name' => "Campaña {$m}", 'slug' => Str::slug("campana-{$m}"),
            'type' => 'manual', 'status' => 'draft',
        ]);
        $d['contenido'] = $this->crear(GeneratedContent::class, [
            'company_id' => $companyId, 'type' => 'video', 'generation_engine' => 'qa', 'status' => 'ready',
            'title' => "Video {$m}",
        ], ['title']);
        $d['formulario'] = $this->crear(LeadForm::class, [
            'company_id' => $companyId, 'slug' => Str::lower('qa-form-' . Str::random(10)), 'name' => "Form {$m}",
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
        $d['envio'] = $this->crear(PilotCampaignSend::class, [
            'pilot_campaign_id' => $d['piloto']->id, 'email' => Str::lower($etiqueta) . '-' . Str::random(6) . '@qa.local',
            'variant' => 'a', 'token' => Str::random(40),
        ]);
        $d['pipeline'] = $this->crear(Pipeline::class, [
            'company_id' => $companyId, 'name' => "Pipe {$m}", 'slug' => Str::slug("pipe-{$m}"),
        ]);
        $d['canalPublicacion'] = $this->crear(PublicationChannel::class, [
            'company_id' => $companyId, 'platform' => 'facebook', 'channel_type' => 'page', 'name' => "Pagina {$m}",
            'slug' => Str::lower('qa-pc-' . Str::random(12)), 'supported_aspect_ratios' => ['1:1'], 'active' => true,
        ]);
        $d['publicacion'] = $this->crear(Publication::class, [
            'company_id' => $companyId, 'channel_id' => $this->canal->id, 'pub_channel_id' => $d['canalPublicacion']->id,
            'content_id' => $d['contenido']->id, 'scheduled_at' => now(), 'status' => 'published', 'published_at' => now(),
            'caption' => "Post {$m}",
        ], ['caption']);
        $d['plantilla'] = $this->crear(ContentTemplate::class, [
            'company_id' => $companyId, 'name' => "Plantilla {$m}", 'category' => 'copy_post', 'template_text' => "Texto {$m}",
        ]);

        return $d;
    }

    /**
     * Crea un modelo saltando $fillable. $opcionales: columnas que se quitan si la tabla no las tiene.
     */
    protected function crear(string $clase, array $attrs, array $opcionales = []): Model
    {
        /** @var Model $modelo */
        $modelo = new $clase();
        $tabla = $modelo->getTable();
        foreach ($opcionales as $col) {
            if (! \Illuminate\Support\Facades\Schema::hasColumn($tabla, $col)) {
                unset($attrs[$col]);
            }
        }
        $modelo->forceFill($attrs);
        $modelo->save();

        return $modelo;
    }

    /** Busca la marca de texto en el cuerpo de la respuesta (cualquier forma JSON o HTML). */
    protected function contieneMarca($respuesta, string $marca): bool
    {
        return str_contains((string) $respuesta->getContent(), $marca);
    }
}
