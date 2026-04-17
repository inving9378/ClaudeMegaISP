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
        Schema::create('map_layers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->foreign('project_id')->references('id')->on('map_proyects')->cascadeOnDelete();
            $table->string('type');
            $table->string('color');
            $table->string('route');
            $table->string('dialog');
            $table->string('text');
            $table->string('icon');
            $table->string('icon_color')->nullable();
            $table->integer('weight')->default(4);
            $table->decimal('distance', 12, 2)->default(0);
            $table->string('label');
            $table->json('coords');
            $table->json('data');
            $table->timestamps();
        });

        Schema::table('map_routes', function (Blueprint $table) {
            $table->dropColumn('color', 'coords', 'weight')->nullable()->after('map_proyect_id');
        });

        // Schema::create('map_clients', function (Blueprint $table) {
        //     $table->id();
        //     $table->unsignedBigInteger('client_id');
        //     $table->foreign('client_id')->references('id')->on('client_main_information')->cascadeOnDelete();
        //     $table->timestamps();
        // });

        // Schema::create('map_poles', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('name');
        //     $table->unsignedBigInteger('project_id');
        //     $table->foreign('project_id')->references('id')->on('map_proyects')->cascadeOnDelete();
        //     $table->timestamps();
        // });

        // Schema::create('map_service_boxs', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('name');
        //     $table->unsignedBigInteger('project_id');
        //     $table->foreign('project_id')->references('id')->on('map_proyects')->cascadeOnDelete();
        //     $table->timestamps();
        // });

        // Schema::create('map_junction_boxs', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('name');
        //     $table->unsignedBigInteger('project_id');
        //     $table->foreign('project_id')->references('id')->on('map_proyects')->cascadeOnDelete();
        //     $table->timestamps();
        // });

        // Schema::create('map_packs', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('name');
        //     $table->unsignedBigInteger('project_id');
        //     $table->foreign('project_id')->references('id')->on('map_proyects')->cascadeOnDelete();
        //     $table->timestamps();
        // });

        // Schema::create('map_cupboards', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('name');
        //     $table->unsignedBigInteger('project_id');
        //     $table->foreign('project_id')->references('id')->on('map_proyects')->cascadeOnDelete();
        //     $table->timestamps();
        // });

        // Schema::create('map_sources', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('name');
        //     $table->unsignedBigInteger('project_id');
        //     $table->foreign('project_id')->references('id')->on('map_proyects')->cascadeOnDelete();
        //     $table->timestamps();
        // });

        // Schema::create('map_buildings', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('name');
        //     $table->unsignedBigInteger('project_id');
        //     $table->foreign('project_id')->references('id')->on('map_proyects')->cascadeOnDelete();
        //     $table->timestamps();
        // });

        // Schema::create('map_notes', function (Blueprint $table) {
        //     $table->id();
        //     $table->longText('description');
        //     $table->unsignedBigInteger('project_id');
        //     $table->foreign('project_id')->references('id')->on('map_proyects')->cascadeOnDelete();
        //     $table->timestamps();
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::dropIfExists('map_clients');
        // Schema::dropIfExists('map_poles');
        // Schema::dropIfExists('map_service_boxs');
        // Schema::dropIfExists('map_junction_boxs');
        // Schema::dropIfExists('map_packs');
        // Schema::dropIfExists('map_cupboards');
        // Schema::dropIfExists('map_sources');
        // Schema::dropIfExists('map_buildings');
        // Schema::dropIfExists('map_notes');
        Schema::dropIfExists('map_layers');
        Schema::table('map_routes', function (Blueprint $table) {
            $table->string('color')->nullable();
            $table->json('coords')->nullable();
            $table->integer('weight')->default(4);
        });
    }
};
