<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \Illuminate\Support\Facades\DB::table('networks')->insert([
            'id' => 27,
            'network' => '10.205.0.0',
            'bm' => 23,
            'rootnet' => null,
            'used' => null,
            'title' => 'principal (1) (2)',
            'network_type' => 'EndNet',
            'network_category' => 'Produccion',
            'comment' => 'CLIENTES',
            'location_id' => null,
            'router_id' => 2,
            'type_of_use' => 'Estatico',
            'allow_usage_network' => 0,
            'parent_id' => null,
            'deployed' => 0,
            'created_at' => null,
            'updated_at' => null,
        ]);

        Schema::table('network_ips', function (Blueprint $table) {
            $table->string('user')->nullable();
            $table->string('password')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('network_ips', function (Blueprint $table) {
            $table->dropColumn('user');
            $table->dropColumn('password');
        });

        \App\Models\Network::where('id', 27)->delete();
    }
};
