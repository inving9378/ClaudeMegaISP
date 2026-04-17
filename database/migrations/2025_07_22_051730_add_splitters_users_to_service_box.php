<?php

use App\Models\MapLayer;
use App\Models\MapSplitter;
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
        Schema::table('map_splitters', function (Blueprint $table) {
            $table->boolean('splitter_users')->default(0)->after('box_id');
        });

        Schema::table('map_splitters_ports', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->unsignedBigInteger('client_id')->nullable()->after('splitter_id');
            $table->foreign('client_id')->references('id')->on('client_main_information')->cascadeOnDelete();
        });

        $splitters_users = MapSplitter::where('splitter_users', true)->get()->pluck('box_id');
        $layers = MapLayer::where('dialog', 'service_box')->whereNotIn('id', $splitters_users)->get();
        foreach ($layers as $l) {
            $l->createSplitter(true);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('map_splitters', function (Blueprint $table) {
            $table->dropColumn('splitter_users');
        });

        Schema::table('map_splitters_ports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });
    }
};
