<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \App\Models\ColumnDatatableModule::create([
            'module_id' => 12,
            'name' => 'service_user_name',
            'label' => 'Servicio Usuario',
            'active' => 1,
            'order' => 93
        ]);

        Schema::table('client_internet_services', function (Blueprint $table) {
            $table->index('client_id');
            $table->index('user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_internet_services', function (Blueprint $table) {
            $table->dropIndex(['client_id']);
            $table->dropIndex(['user']);
        });

        \App\Models\ColumnDatatableModule::where([
            'name' => 'service_user_name',
        ])->delete();
    }
};
