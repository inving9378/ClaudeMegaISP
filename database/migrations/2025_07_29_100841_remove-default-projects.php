<?php

use App\Models\MapLayer;
use App\Models\MapProyect;
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
        Schema::table('map_layers', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->unsignedBigInteger('project_id')->nullable()->change();
            $table->foreign('project_id')->references('id')->on('map_proyects')->onDelete('set null');
        });
        Schema::table('map_proyects', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->unsignedBigInteger('parent_id')->nullable()->change();
            $table->foreign('parent_id')->references('id')->on('map_proyects')->onDelete('set null');
        });
        $projects = MapProyect::whereNull('parent_id')->orWhere('classification', 'client')->get();
        foreach ($projects as $p) {
            $p->delete();
        }
        Schema::table('map_layers', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->foreign('project_id')->references('id')->on('map_proyects')->cascadeOnDelete();
        });
        Schema::table('map_proyects', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->foreign('parent_id')->references('id')->on('map_proyects')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
