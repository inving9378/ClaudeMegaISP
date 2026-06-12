<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private array $permissions = [
        'voip.ia-bot.view'   => 'Ver Asistente IA MegaVoz',
        'voip.ia-bot.config' => 'Configurar Asistente IA MegaVoz',
        'voip.ia-bot.leads'  => 'Ver y gestionar leads del bot',
        'voip.ia-bot.kb'     => 'Gestionar base de conocimiento del bot',
    ];

    public function up(): void
    {
        // ── Configuración del bot ─────────────────────────────────────────────
        Schema::create('ia_bot_config', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('voice', 50)->default('nova');
            $table->string('language', 20)->default('es-MX');
            $table->float('temperature')->default(0.7);
            $table->unsignedInteger('max_turns')->default(12);
            $table->unsignedInteger('max_duration_seconds')->default(480);
            $table->unsignedInteger('timeout_seconds')->default(15);
            $table->string('grupo_timbrado_name', 100)->nullable();
            $table->string('greeting_customer', 500)
                  ->default('Hola [nombre], bienvenido a Meganet. ¿En qué puedo ayudarte hoy?');
            $table->string('greeting_lead', 500)
                  ->default('Hola, bienvenido a Meganet Telecomunicaciones. Soy María, tu asistente virtual. ¿En qué puedo ayudarte?');
            $table->longText('system_prompt');
            $table->timestamps();
        });

        // ── Base de conocimiento ──────────────────────────────────────────────
        Schema::create('ia_bot_knowledge_base', function (Blueprint $table) {
            $table->id();
            $table->string('category', 100);
            $table->string('problem', 255);
            $table->string('keywords', 500)->nullable();
            $table->json('solution_steps');
            $table->boolean('escalate_if_fails')->default(true);
            $table->timestamps();
            $table->index('category');
        });

        // ── Conversaciones ────────────────────────────────────────────────────
        Schema::create('ia_bot_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('call_id', 255)->unique();
            $table->string('phone_number', 20)->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('customer_name', 255)->nullable();
            $table->string('customer_email', 255)->nullable();
            $table->enum('conversation_type', ['customer_support', 'sales_lead'])->default('sales_lead');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->json('transcript')->nullable();
            $table->string('problem_category', 100)->nullable();
            $table->boolean('resolved')->default(false);
            $table->boolean('escalated')->default(false);
            $table->string('escalated_to', 50)->nullable();
            $table->dateTime('transferred_at')->nullable();
            $table->string('transferred_extension', 20)->nullable();
            $table->json('lead_data')->nullable();
            $table->decimal('cost_usd', 10, 4)->default(0);
            $table->enum('status', ['completed', 'transferred', 'failed', 'timeout'])->default('completed');
            $table->timestamps();
            $table->index('phone_number');
            $table->index('customer_id');
            $table->index('call_id');
        });

        // ── Leads generados ───────────────────────────────────────────────────
        Schema::create('ia_bot_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('name', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('interest_service', 100)->nullable();
            $table->string('interest_plan', 100)->nullable();
            $table->unsignedBigInteger('assigned_to_user_id')->nullable();
            $table->enum('status', ['new', 'contacted', 'interested', 'converted', 'rejected'])->default('new');
            $table->longText('notes')->nullable();
            $table->timestamps();
            $table->foreign('conversation_id')->references('id')->on('ia_bot_conversations')->nullOnDelete();
        });

        // ── Seed config inicial ───────────────────────────────────────────────
        DB::table('ia_bot_config')->insert([
            'enabled'          => false,
            'voice'            => 'nova',
            'language'         => 'es-MX',
            'temperature'      => 0.7,
            'max_turns'        => 12,
            'max_duration_seconds' => 480,
            'timeout_seconds'  => 15,
            'grupo_timbrado_name' => null,
            'greeting_customer' => 'Hola [nombre], bienvenido a Meganet. ¿En qué puedo ayudarte hoy?',
            'greeting_lead'    => 'Hola, bienvenido a Meganet Telecomunicaciones. Soy María, tu asistente virtual. ¿En qué puedo ayudarte?',
            'system_prompt'    => "Eres María, asistente de ventas y atención a clientes de Meganet Telecomunicaciones.\nTu objetivo es:\n1. Saludar calurosamente al cliente\n2. Preguntar qué servicio necesita (internet, VoIP, etc.)\n3. Ofrecer planes disponibles y precios\n4. Tomar datos: nombre, teléfono, dirección\n5. Si el cliente quiere hablar con un agente, transferir al grupo de timbrado\nSiempre responde en español de México. Sé amable, profesional y persuasivo.\nCuando el cliente pida hablar con un agente, termina tu respuesta con: [TRANSFERIR]",
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // ── Seed base de conocimiento ─────────────────────────────────────────
        DB::table('ia_bot_knowledge_base')->insert([
            [
                'category' => 'internet',
                'problem'  => 'Sin conexión a internet',
                'keywords' => 'sin internet,no funciona,sin datos,desconectado,no conecta',
                'solution_steps' => json_encode([
                    'step1' => '¿Está el módem encendido? ¿Tiene luz verde?',
                    'step2' => '¿El WiFi es visible en tus dispositivos?',
                    'step3' => '¿Estás usando la contraseña correcta del WiFi?',
                    'step4' => 'Apaga el módem 30 segundos y vuelve a encenderlo.',
                    'step5' => 'Espera 3 a 5 minutos a que sincronice.',
                    'step6' => 'Intenta conectarte de nuevo.',
                ]),
                'escalate_if_fails' => true,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'category' => 'internet',
                'problem'  => 'Internet lento',
                'keywords' => 'lento,lentitud,tarda,velocidad baja,poca velocidad',
                'solution_steps' => json_encode([
                    'step1' => '¿Cuántos dispositivos están conectados al WiFi?',
                    'step2' => '¿Hay descargas activas o actualizaciones en progreso?',
                    'step3' => '¿Te conectas por WiFi o por cable ethernet?',
                    'step4' => 'Reinicia el módem apagándolo 30 segundos.',
                    'step5' => 'Coloca el módem en una zona central y despejada.',
                ]),
                'escalate_if_fails' => true,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'category' => 'voip',
                'problem'  => 'No puedo hacer llamadas',
                'keywords' => 'llamadas,no suena,sin audio,no puedo llamar,teléfono no funciona',
                'solution_steps' => json_encode([
                    'step1' => '¿El teléfono IP está registrado? ¿Muestra señal?',
                    'step2' => '¿El micrófono y parlantes están activos?',
                    'step3' => '¿Tienes internet estable?',
                    'step4' => 'Reinicia el teléfono IP desconectándolo de la corriente.',
                    'step5' => 'Verifica que las credenciales estén correctas.',
                ]),
                'escalate_if_fails' => true,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'category' => 'facturacion',
                'problem'  => 'Consulta de factura o pago',
                'keywords' => 'factura,cobro,pago,deuda,estado de cuenta,cuánto debo',
                'solution_steps' => json_encode([
                    'step1' => '¿Qué período te interesa consultar?',
                    'step2' => 'Puedo enviarte la información por correo. ¿Cuál es tu email?',
                    'step3' => '¿Necesitas hablar con el área de pagos?',
                ]),
                'escalate_if_fails' => false,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'category' => 'cobertura',
                'problem'  => 'Consulta de cobertura o instalación nueva',
                'keywords' => 'cobertura,instalación,quiero instalar,tienen servicio,llega el internet',
                'solution_steps' => json_encode([
                    'step1' => '¿En qué colonia o municipio estás?',
                    'step2' => '¿Cuál es tu dirección exacta?',
                    'step3' => 'Voy a verificar disponibilidad en tu zona.',
                    'step4' => '¿Te interesa internet, teléfono VoIP, o ambos?',
                ]),
                'escalate_if_fails' => false,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        // ── Permisos ──────────────────────────────────────────────────────────
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permissions as $name => $description) {
            $perm = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

            foreach (['super-administrator', 'DESARROLLADOR'] as $roleName) {
                $role = Role::where('name', $roleName)->first();
                if ($role && ! $role->hasPermissionTo($perm)) {
                    $role->givePermissionTo($perm);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_bot_leads');
        Schema::dropIfExists('ia_bot_conversations');
        Schema::dropIfExists('ia_bot_knowledge_base');
        Schema::dropIfExists('ia_bot_config');
    }
};
