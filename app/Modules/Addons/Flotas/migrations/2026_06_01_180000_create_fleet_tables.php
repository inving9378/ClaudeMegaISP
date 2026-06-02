<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Proveedores (no depende de vehículos)
        Schema::create('fleet_providers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable()->index(); // NULL = Meganet interno
            $table->string('name');
            $table->enum('type', ['workshop', 'dealer', 'parts', 'other'])->default('workshop');
            $table->string('contact_name')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Vehículos
        Schema::create('fleet_vehicles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable()->index(); // NULL = Meganet interno
            $table->string('plates', 20)->nullable();
            $table->string('brand');
            $table->string('model');
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('color')->nullable();
            $table->string('vin', 50)->nullable();
            $table->string('motor_number', 50)->nullable();
            $table->enum('vehicle_type', ['car', 'pickup', 'truck', 'motorcycle', 'other'])->default('car');
            $table->enum('fuel_type', ['gasoline', 'diesel', 'electric', 'hybrid'])->default('gasoline');
            $table->decimal('tank_capacity_liters', 6, 1)->nullable();
            $table->unsignedInteger('current_km')->default(0);
            $table->enum('status', ['active', 'in_workshop', 'inactive'])->default('active');
            $table->string('habitual_location')->nullable();
            $table->text('notes')->nullable();
            // GPS — campos registrales para Fase 2
            $table->boolean('has_gps')->default(false);
            $table->string('gps_brand')->nullable();
            $table->string('gps_model')->nullable();
            $table->string('gps_imei', 20)->nullable();
            $table->string('gps_sim', 20)->nullable();
            $table->string('gps_carrier')->nullable();
            // Auditoría
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
        });

        // 3. Asignaciones
        Schema::create('fleet_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('fleet_vehicles')->onDelete('cascade');
            $table->unsignedBigInteger('user_id');
            $table->string('department')->nullable();
            $table->date('since');
            $table->date('until')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'until']); // since until=NULL = activo
        });

        // 4. Mantenimientos
        Schema::create('fleet_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('fleet_vehicles')->onDelete('cascade');
            $table->enum('type', ['preventive', 'corrective', 'emergency', 'verification'])->default('preventive');
            $table->date('service_date');
            $table->unsignedInteger('service_km')->nullable();
            $table->json('works')->nullable();          // array de trabajos realizados
            $table->text('description')->nullable();
            $table->foreignId('provider_id')->nullable()->constrained('fleet_providers')->onDelete('set null');
            $table->string('mechanic_name')->nullable();
            $table->decimal('labor_cost', 10, 2)->default(0);
            $table->decimal('parts_cost', 10, 2)->default(0);
            $table->decimal('other_cost', 10, 2)->default(0);
            $table->decimal('total_cost', 10, 2)->storedAs('labor_cost + parts_cost + other_cost');
            $table->date('next_service_date')->nullable();
            $table->unsignedInteger('next_service_km')->nullable();
            $table->boolean('is_draft')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'service_date']);
        });

        // 5. Archivos de mantenimiento
        Schema::create('fleet_maintenance_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_id')->constrained('fleet_maintenances')->onDelete('cascade');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type', 50)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();
        });

        // 6. Documentos
        Schema::create('fleet_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('fleet_vehicles')->onDelete('cascade');
            $table->enum('document_type', [
                'circulation_card',
                'insurance_policy',
                'tenencia',
                'verification',
                'operator_license',
                'special_permit',
                'other',
            ]);
            $table->string('folio_number')->nullable();
            $table->string('issued_by')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->string('file_path')->nullable();
            // Alertas
            $table->boolean('alert_30_days')->default(true);
            $table->boolean('alert_7_days')->default(true);
            $table->boolean('alert_1_day')->default(true);
            $table->boolean('alert_same_day')->default(false);
            $table->json('alert_channels')->nullable(); // ['email','whatsapp','push','sms']
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'document_type']);
            $table->index('expiration_date'); // para cron de alertas
        });

        // 7. Combustible
        Schema::create('fleet_fuel_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('fleet_vehicles')->onDelete('cascade');
            $table->date('refuel_date');
            $table->decimal('liters', 7, 2);
            $table->decimal('cost', 10, 2);
            $table->unsignedInteger('km_at_refuel')->nullable();
            $table->string('octane', 10)->nullable();
            $table->string('station_name')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'refuel_date']);
        });

        // 8. Fotos
        Schema::create('fleet_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('fleet_vehicles')->onDelete('cascade');
            $table->enum('photo_type', ['front', 'side', 'rear', 'dashboard', 'general'])->default('general');
            $table->string('file_path');
            $table->timestamp('taken_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_photos');
        Schema::dropIfExists('fleet_fuel_log');
        Schema::dropIfExists('fleet_documents');
        Schema::dropIfExists('fleet_maintenance_files');
        Schema::dropIfExists('fleet_maintenances');
        Schema::dropIfExists('fleet_assignments');
        Schema::dropIfExists('fleet_vehicles');
        Schema::dropIfExists('fleet_providers');
    }
};
